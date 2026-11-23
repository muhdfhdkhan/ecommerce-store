<?php
// admin/settings.php - store identity, brand logo, header ticker, PostEx courier.
declare(strict_types=1);

require_once BASE_PATH . '/includes/admin_guard.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();
    admin_require_permission($ADMIN, 'settings.edit');

    $do = (string)($_POST['do'] ?? 'save');
    $up = db()->prepare(
        'INSERT INTO store_settings (setting_key, setting_value) VALUES (:k, :v)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );

    if ($do === 'logo_upload') {
        if (!empty($_FILES['logo']['name'])) {
            try {
                $rel = image_upload_store($_FILES['logo'], 'brand');
                $st = db()->prepare("SELECT setting_value FROM store_settings WHERE setting_key = 'logo_path'");
                $st->execute();
                $old = $st->fetchColumn();
                if ($old) { image_upload_delete((string)$old); }
                $up->execute([':k' => 'logo_path', ':v' => $rel]);
                audit('brand_logo_updated', 'store_settings');
                $_SESSION['flash_success'] = 'Logo updated - it now appears in the storefront header and as the favicon.';
            } catch (RuntimeException $e) {
                $_SESSION['flash_error'] = $e->getMessage();
            }
        } else {
            $_SESSION['flash_error'] = 'Choose a logo image first (square works best).';
        }
        safe_redirect('/admin/settings');
    }

    if ($do === 'logo_remove') {
        $st = db()->prepare("SELECT setting_value FROM store_settings WHERE setting_key = 'logo_path'");
        $st->execute();
        $old = $st->fetchColumn();
        if ($old) { image_upload_delete((string)$old); }
        $up->execute([':k' => 'logo_path', ':v' => '']);
        audit('brand_logo_removed', 'store_settings');
        $_SESSION['flash_success'] = 'Logo removed.';
        safe_redirect('/admin/settings');
    }

    // Custom size range for made-to-order patches/motifs.
    if ($do === 'save_sizing') {
        $sizeMin = $_POST['size_min_inches'] ?? '';
        $sizeMax = $_POST['size_max_inches'] ?? '';
        if (!is_numeric($sizeMin) || !is_numeric($sizeMax)) {
            $_SESSION['flash_error'] = 'Enter both size limits as numbers.';
            safe_redirect('/admin/settings');
        }
        $sizeMin = (float)$sizeMin;
        $sizeMax = (float)$sizeMax;
        if ($sizeMin < 0.25 || $sizeMax > 100 || $sizeMax <= $sizeMin) {
            $_SESSION['flash_error'] = 'Size limits must be between 0.25 and 100 inches, with the maximum above the minimum.';
            safe_redirect('/admin/settings');
        }
        $up->execute([':k' => 'size_min_inches', ':v' => number_format($sizeMin, 2, '.', '')]);
        $up->execute([':k' => 'size_max_inches', ':v' => number_format($sizeMax, 2, '.', '')]);
        audit('settings_updated', 'store_settings', null, null,
            ['keys' => ['size_min_inches', 'size_max_inches']]);
        $_SESSION['flash_success'] = 'Custom size range saved.';
        safe_redirect('/admin/settings');
    }

    // Social media links + WhatsApp number (storefront footer).
    if ($do === 'save_social') {
        $facebook = trim(mb_substr((string)($_POST['social_facebook_url'] ?? ''), 0, 300));
        $instagram = trim(mb_substr((string)($_POST['social_instagram_url'] ?? ''), 0, 300));
        $whatsapp = trim(mb_substr((string)($_POST['whatsapp_number'] ?? ''), 0, 24));

        foreach (['Facebook' => $facebook, 'Instagram' => $instagram] as $label => $url) {
            if ($url === '') {
                continue;
            }
            $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
            if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array($scheme, ['http', 'https'], true)) {
                $_SESSION['flash_error'] = $label . ' link must be a full http(s) URL.';
                safe_redirect('/admin/settings');
            }
        }

        if ($whatsapp !== '') {
            if (preg_match('/[^0-9+()\-\s]/', $whatsapp) === 1) {
                $_SESSION['flash_error'] = 'WhatsApp number can only contain digits, spaces, +, - and brackets.';
                safe_redirect('/admin/settings');
            }
            $digits = (string)preg_replace('/\D+/', '', $whatsapp);
            if (strlen($digits) < 8 || strlen($digits) > 15) {
                $_SESSION['flash_error'] = 'WhatsApp number must contain 8-15 digits including the country code (e.g. +92 300 1234567).';
                safe_redirect('/admin/settings');
            }
        }

        $up->execute([':k' => 'social_facebook_url', ':v' => $facebook]);
        $up->execute([':k' => 'social_instagram_url', ':v' => $instagram]);
        $up->execute([':k' => 'whatsapp_number', ':v' => $whatsapp]);
        audit('settings_updated', 'store_settings', null, null,
            ['keys' => ['social_facebook_url', 'social_instagram_url', 'whatsapp_number']]);
        $_SESSION['flash_success'] = 'Social links saved.';
        safe_redirect('/admin/settings');
    }

    // save = one section at a time. Each form posts only its own keys, so map
    // them explicitly — otherwise saving the ticker would blank PostEx, etc.
    $formKeys = [
        'save_store'  => ['store_name', 'store_email', 'store_phone', 'store_address', 'bank_details', 'cod_fee'],
        'save_ticker' => ['ticker_text', 'ticker_active'],
        'save_postex' => ['postex_api_token', 'postex_api_base', 'postex_service_type', 'postex_default_weight'],
        'save_google' => ['google_client_id', 'google_client_secret'],
    ];
    if (isset($formKeys[$do])) {
        $keys = $formKeys[$do];
        foreach ($keys as $k) {
            $val = trim(mb_substr((string)($_POST[$k] ?? ''), 0, 500));
            // Never overwrite a stored secret with blank - blank means "keep existing".
            if (in_array($k, ['postex_api_token', 'google_client_secret'], true) && $val === '') {
                continue;
            }
            $up->execute([':k' => $k, ':v' => $val]);
        }
        audit('settings_updated', 'store_settings', null, null, ['keys' => $keys]);
        $_SESSION['flash_success'] = 'Settings saved.';
        safe_redirect('/admin/settings');
    }
}

admin_layout('Settings', function () use ($ADMIN) {
    $canEdit = admin_user_can($ADMIN, 'settings.edit');

    $st = db()->prepare('SELECT setting_key, setting_value FROM store_settings');
    $st->execute();
    $settings = [];
    foreach ($st->fetchAll() as $row) {
        $settings[$row['setting_key']] = (string)$row['setting_value'];
    }
    $logo = $settings['logo_path'] ?? '';
    $tokenStored = ($settings['postex_api_token'] ?? '') !== '';
    $googleSecretStored = ($settings['google_client_secret'] ?? '') !== '';

    admin_flash();
    ?>
    <h1 class="text-xl font-bold tracking-tight sm:text-2xl">Store settings</h1>

    <div class="mt-6 max-w-2xl space-y-6">
        <!-- Brand logo -->
        <section class="rounded-2xl border border-line bg-surface p-5 sm:p-6">
            <h2 class="font-bold">Brand logo</h2>
            <p class="mt-1 text-xs text-muted-ink">Shown in the storefront header and as the browser favicon (JPG/PNG/WebP, up to 5 MB; square works best).</p>
            <div class="mt-4 flex items-center gap-4">
                <div class="grid h-16 w-16 shrink-0 place-items-center overflow-hidden rounded-full border border-line bg-canvas">
                    <?php if ($logo !== ''): ?>
                        <img src="/uploads/<?= e($logo) ?>" alt="Store logo" class="h-full w-full object-cover">
                    <?php else: ?>
                        <span aria-hidden="true" class="text-[13px] font-bold text-muted-ink">P&amp;M</span>
                    <?php endif; ?>
                </div>
                <?php if ($canEdit): ?>
                <form method="post" enctype="multipart/form-data" class="flex flex-wrap items-center gap-3">
                    <input type="hidden" name="do" value="logo_upload">
                    <?= csrf_field() ?>
                    <input type="file" name="logo" accept="image/jpeg,image/png,image/webp" required class="max-w-[220px] text-sm">
                    <button class="inline-flex h-11 items-center rounded-xl bg-accent px-5 text-sm font-semibold text-white hover:bg-accent/90">Upload logo</button>
                </form>
                <?php if ($logo !== ''): ?>
                <form method="post" data-confirm>
                    <input type="hidden" name="do" value="logo_remove">
                    <?= csrf_field() ?>
                    <button class="inline-flex h-11 items-center rounded-xl border border-danger/40 px-4 text-sm font-semibold text-danger hover:bg-danger/10">Remove</button>
                </form>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </section>

        <!-- Header ticker -->
        <section class="rounded-2xl border border-line bg-surface p-5 sm:p-6">
            <h2 class="font-bold">Header ticker <span class="font-normal text-muted-ink">(sliding message on every store page)</span></h2>
            <form method="post" class="mt-4 space-y-4">
                <input type="hidden" name="do" value="save_ticker">
                <?= csrf_field() ?>
                <div>
                    <label for="ticker_text" class="block text-sm font-semibold">Ticker message</label>
                    <input id="ticker_text" name="ticker_text" maxlength="300" value="<?= e($settings['ticker_text'] ?? 'Delivery All Over Pakistan - Free Delivery in Faisalabad') ?>" <?= $canEdit ? '' : 'disabled' ?>
                           class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4">
                    <p class="mt-1 text-xs text-muted-ink">Separate messages with <code class="font-mono">|</code> - they slide across the bar.</p>
                </div>
                <label class="flex items-center gap-2 text-sm font-semibold">
                    <input type="checkbox" name="ticker_active" value="1" <?= ($settings['ticker_active'] ?? '1') === '1' ? 'checked' : '' ?> <?= $canEdit ? '' : 'disabled' ?> class="h-4 w-4 rounded border-line">
                    Show the ticker bar
                </label>
                <button type="submit" class="inline-flex h-11 items-center rounded-xl bg-ink px-6 text-sm font-semibold text-white">Save ticker</button>
            </form>
        </section>

        <!-- Social media & WhatsApp -->
        <section class="rounded-2xl border border-line bg-surface p-5 sm:p-6">
            <h2 class="font-bold">Social media &amp; WhatsApp</h2>
            <p class="mt-1 text-xs text-muted-ink">
                Facebook and Instagram icons plus a green WhatsApp button in the storefront footer.
                Leave a field blank to hide that icon.
            </p>
            <form method="post" class="mt-4 space-y-4">
                <input type="hidden" name="do" value="save_social">
                <?= csrf_field() ?>
                <div>
                    <label for="social_facebook_url" class="block text-sm font-semibold">Facebook page URL</label>
                    <input id="social_facebook_url" name="social_facebook_url" maxlength="300" placeholder="https://facebook.com/yourpage" value="<?= e($settings['social_facebook_url'] ?? '') ?>" <?= $canEdit ? '' : 'disabled' ?>
                           class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4 text-sm">
                </div>
                <div>
                    <label for="social_instagram_url" class="block text-sm font-semibold">Instagram profile URL</label>
                    <input id="social_instagram_url" name="social_instagram_url" maxlength="300" placeholder="https://instagram.com/yourprofile" value="<?= e($settings['social_instagram_url'] ?? '') ?>" <?= $canEdit ? '' : 'disabled' ?>
                           class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4 text-sm">
                </div>
                <div>
                    <label for="whatsapp_number" class="block text-sm font-semibold">WhatsApp number <span class="font-normal text-muted-ink">(with country code)</span></label>
                    <input id="whatsapp_number" name="whatsapp_number" maxlength="24" inputmode="tel" placeholder="+92 300 1234567" value="<?= e($settings['whatsapp_number'] ?? '') ?>" <?= $canEdit ? '' : 'disabled' ?>
                           class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4 tabular-nums">
                    <p class="mt-1 text-xs text-muted-ink">Shown in a green-bordered WhatsApp button with the number. If blank, the store contact phone is used when available.</p>
                </div>
                <?php if ($canEdit): ?>
                <button type="submit" class="inline-flex h-11 items-center rounded-xl bg-ink px-6 text-sm font-semibold text-white">Save social links</button>
                <?php endif; ?>
            </form>
        </section>

        <!-- Custom size range -->
        <section class="rounded-2xl border border-line bg-surface p-5 sm:p-6">
            <h2 class="font-bold">Patch &amp; motif sizing</h2>
            <p class="mt-1 text-xs text-muted-ink">
                Made-to-order product pages show a size slider and a typed size. Shoppers can choose any
                size in this range (inches); the slider moves in 0.5&quot; steps.
            </p>
            <?php [$sizeMin, $sizeMax] = product_size_bounds(); ?>
            <form method="post" class="mt-4 space-y-4">
                <input type="hidden" name="do" value="save_sizing">
                <?= csrf_field() ?>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="size_min_inches" class="block text-sm font-semibold">Minimum size (inches)</label>
                        <input id="size_min_inches" name="size_min_inches" type="number" inputmode="decimal"
                               min="0.25" max="99.75" step="0.25" value="<?= e(product_size_display($sizeMin)) ?>" <?= $canEdit ? '' : 'disabled' ?>
                               class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4 tabular-nums">
                    </div>
                    <div>
                        <label for="size_max_inches" class="block text-sm font-semibold">Maximum size (inches)</label>
                        <input id="size_max_inches" name="size_max_inches" type="number" inputmode="decimal"
                               min="0.5" max="100" step="0.25" value="<?= e(product_size_display($sizeMax)) ?>" <?= $canEdit ? '' : 'disabled' ?>
                               class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4 tabular-nums">
                    </div>
                </div>
                <?php if ($canEdit): ?>
                <button type="submit" class="inline-flex h-11 items-center rounded-xl bg-ink px-6 text-sm font-semibold text-white">Save size range</button>
                <?php endif; ?>
            </form>
        </section>

        <!-- PostEx -->
        <section class="rounded-2xl border border-line bg-surface p-5 sm:p-6">
            <h2 class="font-bold">PostEx courier integration</h2>
            <p class="mt-1 text-xs text-muted-ink">Used by the "Book with PostEx" buttons on orders. Get your API token from the PostEx merchant portal.</p>
            <form method="post" class="mt-4 space-y-4">
                <input type="hidden" name="do" value="save_postex">
                <?= csrf_field() ?>
                <div>
                    <label for="postex_api_token" class="block text-sm font-semibold">API token</label>
                    <input id="postex_api_token" name="postex_api_token" type="password" maxlength="200" placeholder="<?= $tokenStored ? 'Stored - leave blank to keep' : 'Paste your PostEx token' ?>" <?= $canEdit ? '' : 'disabled' ?>
                           class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4 font-mono text-sm">
                    <p class="mt-1 text-xs <?= $tokenStored ? 'text-success' : 'text-warning' ?>"><?= $tokenStored ? 'A token is stored.' : 'No token stored yet - PostEx booking is disabled until you add one.' ?></p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="postex_api_base" class="block text-sm font-semibold">API base URL</label>
                        <input id="postex_api_base" name="postex_api_base" maxlength="200" value="<?= e($settings['postex_api_base'] ?? 'https://api.postex.pk/services/integration/api/order') ?>" <?= $canEdit ? '' : 'disabled' ?>
                               class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4 font-mono text-xs">
                    </div>
                    <div>
                        <label for="postex_service_type" class="block text-sm font-semibold">Service type</label>
                        <input id="postex_service_type" name="postex_service_type" maxlength="60" value="<?= e($settings['postex_service_type'] ?? 'Overnight Service') ?>" <?= $canEdit ? '' : 'disabled' ?>
                               class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4 text-sm">
                    </div>
                    <div>
                        <label for="postex_default_weight" class="block text-sm font-semibold">Default parcel weight (grams)</label>
                        <input id="postex_default_weight" name="postex_default_weight" type="number" min="100" max="50000" value="<?= e($settings['postex_default_weight'] ?? '500') ?>" <?= $canEdit ? '' : 'disabled' ?>
                               class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4 tabular-nums">
                    </div>
                </div>
                <button type="submit" class="inline-flex h-11 items-center rounded-xl bg-ink px-6 text-sm font-semibold text-white">Save PostEx settings</button>
            </form>
        </section>

        <!-- Google Sign-In -->
        <section class="rounded-2xl border border-line bg-surface p-5 sm:p-6">
            <h2 class="font-bold">Google Sign-In for customers</h2>
            <p class="mt-1 text-xs text-muted-ink">
                Lets shoppers sign in with their Google account on the login and register pages.
                Create OAuth credentials in the
                <a href="https://console.cloud.google.com/apis/credentials" target="_blank" rel="noopener" class="font-semibold text-accent hover:underline">Google Cloud console</a>
                and add this exact redirect URI:
            </p>
            <p class="mt-2 break-all rounded-xl border border-line bg-canvas/60 px-4 py-3 font-mono text-xs"><?= e(rtrim((string)config('url'), '/') . '/account/google/callback') ?></p>
            <form method="post" class="mt-4 space-y-4">
                <input type="hidden" name="do" value="save_google">
                <?= csrf_field() ?>
                <div>
                    <label for="google_client_id" class="block text-sm font-semibold">Client ID</label>
                    <input id="google_client_id" name="google_client_id" maxlength="500" value="<?= e($settings['google_client_id'] ?? '') ?>" <?= $canEdit ? '' : 'disabled' ?>
                           placeholder="xxxxxxxx.apps.googleusercontent.com"
                           class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4 font-mono text-xs">
                </div>
                <div>
                    <label for="google_client_secret" class="block text-sm font-semibold">Client secret</label>
                    <input id="google_client_secret" name="google_client_secret" type="password" maxlength="500" placeholder="<?= $googleSecretStored ? 'Stored - leave blank to keep' : 'Paste the client secret' ?>" <?= $canEdit ? '' : 'disabled' ?>
                           class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4 font-mono text-sm">
                    <p class="mt-1 text-xs <?= $googleSecretStored ? 'text-success' : 'text-warning' ?>"><?= $googleSecretStored ? 'A client secret is stored.' : 'No secret stored yet - Google sign-in stays hidden until you add one.' ?></p>
                </div>
                <?php if ($canEdit): ?>
                <button type="submit" class="inline-flex h-11 items-center rounded-xl bg-ink px-6 text-sm font-semibold text-white">Save Google settings</button>
                <?php endif; ?>
            </form>
        </section>

        <!-- Store identity -->
        <form method="post" class="rounded-2xl border border-line bg-surface p-5 sm:p-6">
            <input type="hidden" name="do" value="save_store">
            <?= csrf_field() ?>
            <h2 class="font-bold">Store identity</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="store_name" class="block text-sm font-semibold">Store name</label>
                    <input id="store_name" name="store_name" maxlength="120" value="<?= e($settings['store_name'] ?? 'Patches&Motifs') ?>" <?= $canEdit ? '' : 'disabled' ?>
                           class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4">
                </div>
                <div>
                    <label for="store_email" class="block text-sm font-semibold">Contact email</label>
                    <input id="store_email" name="store_email" type="email" maxlength="254" value="<?= e($settings['store_email'] ?? '') ?>" <?= $canEdit ? '' : 'disabled' ?>
                           class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4">
                </div>
                <div>
                    <label for="store_phone" class="block text-sm font-semibold">Contact phone</label>
                    <input id="store_phone" name="store_phone" maxlength="24" value="<?= e($settings['store_phone'] ?? '') ?>" <?= $canEdit ? '' : 'disabled' ?>
                           class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4">
                </div>
                <div class="sm:col-span-2">
                    <label for="store_address" class="block text-sm font-semibold">Business address</label>
                    <input id="store_address" name="store_address" maxlength="500" value="<?= e($settings['store_address'] ?? '') ?>" <?= $canEdit ? '' : 'disabled' ?>
                           class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4">
                </div>
                <div class="sm:col-span-2">
                    <label for="bank_details" class="block text-sm font-semibold">Bank transfer instructions <span class="font-normal text-muted-ink">(shown at checkout)</span></label>
                    <textarea id="bank_details" name="bank_details" rows="3" maxlength="500" <?= $canEdit ? '' : 'disabled' ?>
                              class="mt-1.5 w-full rounded-xl border border-line bg-canvas/60 px-4 py-3"><?= e($settings['bank_details'] ?? '') ?></textarea>
                </div>
                <div>
                    <label for="cod_fee" class="block text-sm font-semibold">COD fee (PKR, 0 = none)</label>
                    <input id="cod_fee" name="cod_fee" inputmode="decimal" value="<?= e($settings['cod_fee'] ?? '0') ?>" <?= $canEdit ? '' : 'disabled' ?>
                           class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4 tabular-nums">
                </div>
            </div>
            <?php if ($canEdit): ?>
            <button type="submit" class="mt-5 inline-flex h-12 items-center rounded-full bg-accent px-7 font-semibold text-white hover:bg-accent/90">Save settings</button>
            <?php endif; ?>
        </form>
    </div>
<?php });
