<?php
declare(strict_types=1);
require_once __DIR__ . '/config/init.php';
require_login('keranjang.php');
$user = current_user();
$orderId = max(0, (int) ($_GET['id'] ?? 0));
$pdo = db();
$stmt = $pdo->prepare('SELECT o.*, a.recipient_name, a.phone, a.address_line, a.district, a.city, a.province, a.postal_code FROM orders o LEFT JOIN addresses a ON a.id = o.address_id WHERE o.id = :id AND o.user_id = :user_id LIMIT 1');
$stmt->execute(['id' => $orderId, 'user_id' => (int) $user['id']]);
$order = $stmt->fetch();
$items = [];
if ($order) {
    $stmt = $pdo->prepare('SELECT product_name, unit_price, quantity, line_total FROM order_items WHERE order_id = :id ORDER BY id');
    $stmt->execute(['id' => $orderId]);
    $items = $stmt->fetchAll();
}
if (!$order) http_response_code(404);
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#f7f8f4"><title>Status Pesanan | NusaMart</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet"><link rel="stylesheet" href="assets/css/style.css"></head><body>
<?php require __DIR__ . '/includes/site-header.php'; ?><main class="container commerce-page order-success-page">
<?php if (!$order): ?><div class="empty-state"><span>!</span><h3>Pesanan tidak ditemukan.</h3><p>Pesanan tersebut tidak tersedia di akunmu.</p><a class="button button-dark" href="katalog.php">Kembali ke katalog</a></div>
<?php else: ?><section class="order-success-card"><div class="success-check">✓</div><span class="eyebrow">PESANAN BERHASIL DIBUAT</span><h1>Terima kasih sudah <span>belanja.</span></h1><p>Pesananmu sudah tercatat. Simpan nomor pesanan ini untuk referensi.</p><div class="order-number-box"><small>NOMOR PESANAN</small><strong><?= h($order['order_number']) ?></strong><button type="button" class="copy-order-number" data-copy-text="<?= h($order['order_number']) ?>">Salin</button></div><div class="order-status-line"><span>Status</span><b class="status-pill status-pending">Menunggu konfirmasi</b></div><p class="payment-explainer">Metode pembayaran: <strong><?= h($order['payment_method'] ?? '-') ?></strong>. Ini merupakan prototipe; pembayaran transfer belum diproses otomatis.</p></section>
<div class="order-details-grid"><section class="cart-panel"><div class="cart-panel-heading"><h2>Ringkasan produk</h2><span><?= count($items) ?> produk</span></div><?php foreach ($items as $item): ?><div class="order-line-item"><div><strong><?= h($item['product_name']) ?></strong><small><?= (int) $item['quantity'] ?> × <?= h(money_idr($item['unit_price'])) ?></small></div><b><?= h(money_idr($item['line_total'])) ?></b></div><?php endforeach; ?><div class="summary-line"><span>Subtotal</span><strong><?= h(money_idr($order['subtotal'])) ?></strong></div><div class="summary-line"><span>Pengiriman</span><strong><?= (float) $order['shipping_cost'] === 0.0 ? 'Gratis' : h(money_idr($order['shipping_cost'])) ?></strong></div><div class="summary-total"><span>Total pesanan</span><strong><?= h(money_idr($order['total'])) ?></strong></div></section>
<aside class="summary-card"><span class="eyebrow">ALAMAT TUJUAN</span><h2><?= h($order['recipient_name'] ?? '-') ?></h2><p><?= h($order['phone'] ?? '-') ?></p><p><?= h($order['address_line'] ?? '-') ?><?= !empty($order['district']) ? ', ' . h($order['district']) : '' ?><br><?= h($order['city'] ?? '-') ?>, <?= h($order['province'] ?? '-') ?> <?= h($order['postal_code'] ?? '') ?></p><a class="button button-primary button-full" href="katalog.php">Belanja lagi ↗</a></aside></div><?php endif; ?></main>
<?php require __DIR__ . '/includes/site-footer.php'; ?><div class="toast" id="toast" role="status" aria-live="polite"></div><script src="assets/js/app.js" defer></script></body></html>
