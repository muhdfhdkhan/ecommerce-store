<?php
// modules/store/api/cart.php — JSON cart endpoints (session + CSRF cookie auth, SECURITY.md §22).
declare(strict_types=1);

require_once BASE_PATH . '/modules/orders/cart_service.php';

header('Content-Type: application/json; charset=utf-8');

/** End with a consistent JSON envelope. */
function json_out(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$raw = file_get_contents('php://input') ?: '';
// 1 MB body cap (SECURITY.md §18).
if (strlen($raw) > 1048576) {
    json_out(['ok' => false, 'error' => 'Payload too large.'], 413);
}

$action = (string)($_GET['action'] ?? '');

if ($method === 'POST') {
    csrf_verify_header_only(true);

    $body = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
    if (!is_array($body)) {
        json_out(['ok' => false, 'error' => 'Invalid request body.'], 400);
    }

    switch ($action) {
        case 'add':
            $variantId = int_in($body['variant_id'] ?? null);
            $qty = int_in($body['qty'] ?? 1, 1, 99);
            if ($variantId === null || $qty === null) {
                json_out(['ok' => false, 'error' => 'Invalid item.'], 422);
            }
            $customSize = is_scalar($body['custom_size'] ?? null) ? (string)$body['custom_size'] : '';
            [$ok, $msg] = cart_add_item($variantId, $qty, $customSize);
            if (!$ok) {
                json_out(['ok' => false, 'error' => $msg], 409);
            }
            $view = cart_view();
            json_out(['ok' => true, 'count' => $view['count'], 'subtotal' => money_fmt($view['subtotal_cents'])]);

        case 'update':
            $itemId = int_in($body['cart_item_id'] ?? null);
            $qty = int_in($body['qty'] ?? null, 1, 99);
            if ($itemId === null || $qty === null) {
                json_out(['ok' => false, 'error' => 'Invalid item.'], 422);
            }
            [$ok, $msg] = cart_update_quantity($itemId, $qty);
            json_out($ok ? ['ok' => true] + cart_view_summary() : ['ok' => false, 'error' => $msg], $ok ? 200 : 409);

        case 'remove':
            $itemId = int_in($body['cart_item_id'] ?? null);
            if ($itemId === null) {
                json_out(['ok' => false, 'error' => 'Invalid item.'], 422);
            }
            cart_remove_item($itemId);
            json_out(['ok' => true] + cart_view_summary());

        default:
            json_out(['ok' => false, 'error' => 'Unknown action.'], 404);
    }
}

if ($method === 'GET') {
    json_out(['ok' => true] + cart_view_summary());
}

json_out(['ok' => false, 'error' => 'Method not allowed.'], 405);

function cart_view_summary(): array
{
    $view = cart_view();
    return ['count' => $view['count'], 'subtotal' => money_fmt($view['subtotal_cents'])];
}
