<?php
// admin/payments.php — payment list, bank transfer verification (FR-PAY-005).
declare(strict_types=1);

require_once BASE_PATH . '/includes/admin_guard.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();
    admin_require_permission($ADMIN, 'payments.verify');

    $orderId = int_in($_POST['order_id'] ?? null);
    $do = (string)($_POST['do'] ?? '');
    $reference = trim(mb_substr((string)($_POST['reference'] ?? ''), 0, 64));

    if ($orderId && in_array($do, ['approve', 'reject'], true) && $reference !== '') {
        $newStatus = $do === 'approve' ? 'paid' : 'failed';
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $up = $pdo->prepare(
                'UPDATE payments SET status = :s, reference = :r, verified_by = :u, verified_at = UTC_TIMESTAMP()
                 WHERE order_id = :o'
            );
            $up->execute([':s' => $newStatus, ':r' => $reference, ':u' => (int)$ADMIN['id'], ':o' => $orderId]);
            $uo = $pdo->prepare('UPDATE orders SET payment_status = :s WHERE id = :o');
            $uo->execute([':s' => $newStatus, ':o' => $orderId]);
            $pdo->commit();
            audit('payment_' . $do . 'ed', 'order', $orderId, null, ['reference' => $reference]);
            $_SESSION['flash_success'] = 'Payment ' . $do . 'ed.';
        } catch (Throwable $e) {
            $pdo->rollBack();
            $_SESSION['flash_error'] = 'Could not update the payment.';
        }
    } else {
        $_SESSION['flash_error'] = 'A reference is required.';
    }
    safe_redirect('/admin/payments');
}

admin_layout('Payments', function () use ($ADMIN) {
    $canVerify = admin_user_can($ADMIN, 'payments.verify');

    $rows = db()->query(
        'SELECT p.*, o.order_number, o.full_name, o.currency
         FROM payments p JOIN orders o ON o.id = p.order_id
         ORDER BY FIELD(p.status, "awaiting_verification", "pending", "paid", "failed"), p.id DESC
         LIMIT 100'
    )->fetchAll();

    admin_flash();
    ?>
    <h1 class="text-xl font-bold tracking-tight sm:text-2xl">Payments</h1>
    <p class="mt-1 text-sm text-muted-ink">Bank transfers need verification against the bank statement — a receipt upload alone is not proof of cleared funds.</p>

    <div class="mt-6 space-y-4">
        <?php foreach ($rows as $p):
            $cls = ['awaiting_verification' => 'bg-warning/10 text-warning', 'pending' => 'bg-line text-muted-ink', 'paid' => 'bg-success/10 text-success', 'failed' => 'bg-danger/10 text-danger', 'refunded' => 'bg-danger/10 text-danger'][(string)$p['status']] ?? 'bg-line'; ?>
        <div class="rounded-2xl border border-line bg-surface p-4">
            <div class="flex flex-wrap items-center gap-3">
                <a href="/admin/orders?view=detail&id=<?= (int)$p['order_id'] ?>" class="font-semibold tabular-nums underline-offset-4 hover:underline"><?= e($p['order_number']) ?></a>
                <span class="text-sm text-muted-ink"><?= e($p['full_name']) ?></span>
                <span class="rounded-full px-3 py-1 text-xs font-semibold <?= $cls ?>"><?= e(str_replace('_', ' ', (string)$p['status'])) ?></span>
                <span class="ml-auto font-bold tabular-nums"><?= e($p['method']) ?> · <?= money_fmt(money_to_cents((string)$p['amount'])) ?></span>
            </div>
            <?php if ($canVerify && in_array($p['status'], ['awaiting_verification', 'pending'], true) && $p['method'] === 'bank_transfer'): ?>
            <form method="post" class="mt-3 flex flex-wrap items-center gap-2 border-t border-line pt-3">
                <?= csrf_field() ?>
                <input type="hidden" name="order_id" value="<?= (int)$p['order_id'] ?>">
                <input name="reference" placeholder="Bank reference / txn id" required maxlength="64" class="h-10 w-56 rounded-xl border border-line bg-canvas px-3 text-sm">
                <button name="do" value="approve" class="h-10 rounded-xl border border-success/40 px-4 text-sm font-semibold text-success hover:bg-success/10">Approve</button>
                <button name="do" value="reject" class="h-10 rounded-xl border border-danger/40 px-4 text-sm font-semibold text-danger hover:bg-danger/10" data-confirm>Reject</button>
            </form>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php if (!$rows): ?><p class="rounded-2xl border border-line bg-surface p-8 text-center text-sm text-muted-ink">No payments yet.</p><?php endif; ?>
    </div>
<?php });
