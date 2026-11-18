<?php
// includes/mailer.php — transactional notifications (SECURITY.md §28: never log secrets).
// Emails are recorded in email_log; delivery via PHP mail() when available (XAMPP dev
// usually has no MTA — the log is the source of truth and can be replayed later).
declare(strict_types=1);

/**
 * Queue + attempt to send a notification email.
 * All content is escaped on render; body is plain text.
 */
function mailer_send(string $toEmail, string $subject, string $body, string $event, ?int $orderId = null): void
{
    $toEmail = strtolower(trim(mb_substr($toEmail, 0, 254)));
    if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return;
    }
    $subject = mb_substr($subject, 0, 200);
    $fromEmail = (string)config('mail.from_email', 'store@patchesandmotifs.test');
    $fromName  = (string)config('mail.from_name', 'Patches&Motifs');

    $headers = implode("\r\n", [
        'From: ' . $fromName . ' <' . $fromEmail . '>',
        'X-Mailer: PatchesMotifs',
        'Content-Type: text/plain; charset=UTF-8',
    ]);

    $sent = false;
    $error = null;
    // @ suppression: mail() failures must never break checkout (SECURITY.md §3.3).
    $sent = @mail($toEmail, $subject, $body, $headers);
    if (!$sent) {
        $error = 'mail() unavailable (no MTA configured)';
    }

    $st = db()->prepare(
        'INSERT INTO email_log (to_email, subject, body, event, status, error, order_id, sent_at)
         VALUES (:to, :s, :b, :e, :st, :er, :o, :sent)'
    );
    $st->execute([
        ':to' => $toEmail, ':s' => $subject, ':b' => $body, ':e' => $event,
        ':st' => $sent ? 'sent' : 'failed', ':er' => $error, ':o' => $orderId,
        ':sent' => $sent ? UTC_NOW() : null,
    ]);
}

/** Small SQL fragment helper — DB stores UTC; PHP is set to UTC in bootstrap. */
function UTC_NOW(): string
{
    return gmdate('Y-m-d H:i:s');
}

/** Order-confirmation email for the customer (plain text, no prices-only trust issues). */
function mailer_order_confirmation(array $order, array $items): void
{
    $lines = [];
    foreach ($items as $it) {
        $lines[] = sprintf('- %s (%s) x%d — Rs %s', $it['product_name'], $it['variant_label'] ?: $it['sku'], (int)$it['quantity'], number_format((float)$it['line_total'], 0));
    }
    $body = "Assalam-o-Alaikum {$order['full_name']},\n\n"
        . "Thank you for your order {$order['order_number']}.\n\n"
        . implode("\n", $lines) . "\n\n"
        . "Shipping: Rs " . number_format((float)$order['shipping_total'], 0) . "\n"
        . ($order['discount_total'] > 0 ? "Discount: -Rs " . number_format((float)$order['discount_total'], 0) . "\n" : '')
        . "Total: Rs " . number_format((float)$order['grand_total'], 0) . " ({$order['payment_method']})\n\n"
        . "We will contact you shortly to confirm. Track your order any time from your account.\n\n"
        . "— Patches&Motifs";

    mailer_send((string)$order['email'], 'Your order ' . $order['order_number'] . ' is received', $body, 'order_placed', (int)$order['id']);
}

/** Status-change notification to the customer. */
function mailer_order_status(string $toEmail, string $orderNumber, int $orderId, string $status): void
{
    $friendly = [
        'confirmed' => 'is confirmed and moving to production',
        'processing' => 'is being prepared',
        'dispatched' => 'has been dispatched',
        'delivered' => 'has been delivered',
        'cancelled' => 'has been cancelled',
        'on_hold' => 'is temporarily on hold',
        'returned' => 'has been returned - we are processing it',
    ];
    $msg = $friendly[$status] ?? 'was updated';
    $body = "Your order {$orderNumber} {$msg}.\n\nTrack it from your account.\n\n— Patches&Motifs";
    mailer_send($toEmail, "Order {$orderNumber} update", $body, 'order_status', $orderId);
}
