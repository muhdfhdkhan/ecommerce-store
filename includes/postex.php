<?php
// includes/postex.php - PostEx courier booking (COD logistics, Pakistan).
// API: POST {base}/v3/create-order, auth via "token" header.
// Endpoint + token come from store_settings (postex_api_base, postex_api_token).
// SECURITY.md: token is never logged; failures never break order state (§3.3).
declare(strict_types=1);

/** Read PostEx-related settings as a key=>value array. */
function postex_settings(): array
{
    $st = db()->prepare(
        "SELECT setting_key, setting_value FROM store_settings
         WHERE setting_key IN ('postex_api_token','postex_api_base','postex_service_type','postex_default_weight')"
    );
    $st->execute();
    return $st->fetchAll(PDO::FETCH_KEY_PAIR);
}

/**
 * Book one order with PostEx.
 * Returns [ok(bool), value] where value is the tracking number on success
 * or a safe error message on failure.
 */
function postex_create_booking(array $order, array $items): array
{
    $cfg = postex_settings();
    $token = trim((string)($cfg['postex_api_token'] ?? ''));
    if ($token === '') {
        return [false, 'PostEx API token is not configured. Add it under Settings.'];
    }
    $base = rtrim(trim((string)($cfg['postex_api_base'] ?? 'https://api.postex.pk/services/integration/api/order')), '/');
    $service = trim((string)($cfg['postex_service_type'] ?? 'Overnight Service'));
    $weight = (int)($cfg['postex_default_weight'] ?? 500);
    if ($weight < 100 || $weight > 50000) {
        $weight = 500;
    }

    $address = json_decode((string)$order['shipping_address'], true) ?: [];

    $payload = [
        // PostEx expects the plain city name it has on file operationally.
        'city_name'             => mb_substr(trim((string)($address['city'] ?? '')), 0, 100),
        'customer_name'         => mb_substr(trim((string)$order['full_name']), 0, 100),
        'customer_email'        => mb_substr(trim((string)$order['email']), 0, 100),
        'customer_contact'      => preg_replace('/[^0-9+]/', '', (string)$order['phone']) ?: '0000000000',
        'customer_address'      => mb_substr(trim(($address['line1'] ?? '') . ' ' . ($address['line2'] ?? '')), 0, 300),
        'order_type'            => $service,
        'order_amount'          => (int)round((float)$order['grand_total']),
        'order_reference_number'=> mb_substr((string)$order['order_number'], 0, 50),
        'invoice_number'        => mb_substr((string)$order['order_number'], 0, 50),
        'order_description'     => mb_substr('Patches & Motifs order ' . $order['order_number'], 0, 200),
        'payment_type'          => $order['payment_method'] === 'cod' ? 'COD' : 'Prepaid',
        'weight'                => $weight,
        'order_date'            => gmdate('Y-m-d'),
        'piece_type'            => 'Parcel',
        'product_detail'        => array_map(static fn($it) => [
            'product_name'     => mb_substr((string)$it['product_name'], 0, 100),
            'product_code'     => mb_substr((string)$it['sku'], 0, 50),
            'product_quantity' => (int)$it['quantity'],
            'product_price'    => (int)round((float)$it['line_total'] / max(1, (int)$it['quantity'])),
        ], $items),
    ];

    $ch = curl_init($base . '/v3/create-order');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'token: ' . $token,
        ],
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    $body = curl_exec($ch);
    $err = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($body === false || $body === '') {
        app_log('error', 'postex booking transport failure: ' . $err);
        return [false, 'Could not reach PostEx. Check your connection and try again.'];
    }

    $resp = json_decode((string)$body, true);
    if (!is_array($resp)) {
        app_log('error', "postex booking unparseable response (HTTP $status)");
        return [false, 'PostEx returned an unexpected response. No booking was made.'];
    }

    $statusCode = (int)($resp['status_code'] ?? $status);
    if ($statusCode !== 200) {
        $msg = (string)($resp['status_message'] ?? $resp['message'] ?? 'Unknown PostEx error');
        app_log('error', "postex booking rejected (HTTP $status): $msg");
        return [false, 'PostEx rejected the booking: ' . mb_substr($msg, 0, 180)];
    }

    // Tracking id appears under different keys across API versions.
    $tracking = (string)($resp['tracking_number'] ?? $resp['dynamic_order_id'] ?? $resp['trackingNumber'] ?? '');
    if ($tracking === '') {
        app_log('error', 'postex booking success without tracking id');
        return [false, 'PostEx accepted the order but returned no tracking number.'];
    }
    return [true, $tracking];
}
