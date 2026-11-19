<?php
// includes/google_oauth.php — "Sign in with Google" for storefront customers.
// Standard OAuth 2.0 / OpenID Connect authorization-code flow:
//   1) /account/google        -> redirect to accounts.google.com with a state token
//   2) /account/google/callback -> verify state, exchange the code, read the
//      verified profile, then link/create the local customer and start a session.
// Credentials live in store_settings (admin: Settings -> Google Sign-In).
// SECURITY.md: state CSRF, email_verified required, tokens never logged.
declare(strict_types=1);

/** Google credential settings as setting_key => setting_value. */
function google_oauth_settings(): array
{
    $st = db()->prepare(
        "SELECT setting_key, setting_value FROM store_settings
         WHERE setting_key IN ('google_client_id','google_client_secret')"
    );
    $st->execute();
    return $st->fetchAll(PDO::FETCH_KEY_PAIR);
}

function google_oauth_configured(): bool
{
    $c = google_oauth_settings();
    return trim((string)($c['google_client_id'] ?? '')) !== ''
        && trim((string)($c['google_client_secret'] ?? '')) !== '';
}

/** Must match exactly what is registered in the Google Cloud console. */
function google_redirect_uri(): string
{
    return rtrim((string)config('url'), '/') . '/account/google/callback';
}

/** Google authorization endpoint URL. */
function google_auth_url(string $state): string
{
    $c = google_oauth_settings();
    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'client_id'     => trim((string)($c['google_client_id'] ?? '')),
        'redirect_uri'  => google_redirect_uri(),
        'response_type' => 'code',
        'scope'         => 'openid email profile',
        'state'         => $state,
        'prompt'        => 'select_account',
        'access_type'   => 'online',
        'include_granted_scopes' => 'true',
    ]);
}

/**
 * Small JSON HTTP helper for the OAuth endpoints.
 * Returns the decoded body or null; failures are logged without secrets.
 */
function google_http_json(string $url, ?array $postFields = null, ?string $bearer = null): ?array
{
    $ch = curl_init($url);
    $headers = ['Accept: application/json'];
    if ($postFields !== null) {
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
    }
    if ($bearer !== null) {
        $headers[] = 'Authorization: Bearer ' . $bearer;
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    $body = curl_exec($ch);
    $err = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($body === false || $body === '') {
        app_log('error', 'google oauth transport failure: ' . $err);
        return null;
    }
    $data = json_decode((string)$body, true);
    if (!is_array($data)) {
        app_log('error', "google oauth unparseable response (HTTP $status)");
        return null;
    }
    if ($status !== 200) {
        // error/error_description are safe to log; never log the code or tokens.
        app_log('error', sprintf(
            'google oauth HTTP %d: %s',
            $status,
            (string)($data['error'] ?? $data['error_description'] ?? 'unknown')
        ));
        return null;
    }
    return $data;
}

/** Exchange an authorization code for an access token. */
function google_exchange_code(string $code): ?string
{
    $c = google_oauth_settings();
    $data = google_http_json('https://oauth2.googleapis.com/token', [
        'code'          => $code,
        'client_id'     => trim((string)($c['google_client_id'] ?? '')),
        'client_secret' => trim((string)($c['google_client_secret'] ?? '')),
        'redirect_uri'  => google_redirect_uri(),
        'grant_type'    => 'authorization_code',
    ]);
    $token = (string)($data['access_token'] ?? '');
    return $token !== '' ? $token : null;
}

/** Verified profile from the OpenID userinfo endpoint. */
function google_profile(string $accessToken): ?array
{
    $data = google_http_json('https://openidconnect.googleapis.com/v1/userinfo', null, $accessToken);
    if (!is_array($data)) {
        return null;
    }
    $email = strtolower(trim((string)($data['email'] ?? '')));
    if ($email === '' || ($data['email_verified'] ?? false) !== true) {
        return null; // only verified addresses may sign in / link
    }
    return [
        'sub'   => (string)($data['sub'] ?? ''),
        'email' => $email,
        'name'  => trim((string)($data['name'] ?? '')) ?: $email,
    ];
}

/**
 * Find, link or create the local customer for a Google profile.
 * Returns [ok(bool), customer|null, error|null].
 */
function google_customer_sign_in(array $profile): array
{
    $googleId = mb_substr($profile['sub'], 0, 64);
    $email    = mb_substr($profile['email'], 0, 254);
    $name     = mb_substr($profile['name'], 0, 120);
    if ($googleId === '' || $email === '') {
        return [false, null, 'Google did not return a usable account.'];
    }

    $pdo = db();

    // 1) Already linked Google account.
    $st = $pdo->prepare(
        'SELECT id, name, status FROM customers
         WHERE google_id = :g AND deleted_at IS NULL LIMIT 1'
    );
    $st->execute([':g' => $googleId]);
    $row = $st->fetch();
    if ($row) {
        if ($row['status'] !== 'active') {
            return [false, null, 'This account is unavailable.'];
        }
        return [true, ['id' => (int)$row['id'], 'name' => (string)$row['name']], null];
    }

    // 2) Existing email account: link the Google id on first Google sign-in.
    $st = $pdo->prepare(
        'SELECT id, name, status, google_id FROM customers
         WHERE email = :e AND deleted_at IS NULL LIMIT 1'
    );
    $st->execute([':e' => $email]);
    $row = $st->fetch();
    if ($row) {
        if ($row['status'] !== 'active') {
            return [false, null, 'This account is unavailable.'];
        }
        if ((string)($row['google_id'] ?? '') === '') {
            try {
                $pdo->prepare('UPDATE customers SET google_id = :g WHERE id = :id')
                    ->execute([':g' => $googleId, ':id' => (int)$row['id']]);
            } catch (PDOException $e) {
                // Unique key race: another request linked this Google id first.
                app_log('error', 'google link race: ' . $e->getMessage());
                return [false, null, 'Could not link your Google account. Please try again.'];
            }
        }
        return [true, ['id' => (int)$row['id'], 'name' => (string)$row['name']], null];
    }

    // 3) New customer. Random password hash: Google-only accounts cannot use
    //    password login until they set one via "forgot password".
    try {
        $pdo->prepare(
            'INSERT INTO customers (name, email, phone, password_hash, google_id, status)
             VALUES (:n, :e, NULL, :h, :g, :s)'
        )->execute([
            ':n' => $name,
            ':e' => $email,
            ':h' => password_hash(bin2hex(random_bytes(32)), PASSWORD_BCRYPT, ['cost' => 12]),
            ':g' => $googleId,
            ':s' => 'active',
        ]);
    } catch (PDOException $e) {
        app_log('error', 'google signup failed: ' . $e->getMessage());
        return [false, null, 'Could not create your account. Please try again.'];
    }
    return [true, ['id' => (int)$pdo->lastInsertId(), 'name' => $name], null];
}
