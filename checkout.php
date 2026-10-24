<?php
declare(strict_types=1);
require_once __DIR__ . '/config/init.php';
require_login('checkout.php');
$user = current_user();
$pdo = db();
$cartId = get_or_create_cart_id($pdo, (int) $user['id']);
$error = '';
$old = ['recipient_name' => (string) $user['name'], 'phone' => '', 'address_line' => '', 'district' => '', 'city' => '', 'province' => '', 'postal_code' => '', 'payment_method' => 'bank_transfer', 'customer_notes' => ''];

function checkout_load_items(PDO $pdo, int $cartId): array {
    $stmt = $pdo->prepare('SELECT p.id AS product_id, p.name, p.price, p.sale_price, p.stock, p.is_active, p.image_url, ci.quantity, CASE WHEN p.sale_price IS NOT NULL AND p.sale_price > 0 AND p.sale_price < p.price THEN p.sale_price ELSE p.price END AS current_price FROM carts c INNER JOIN cart_items ci ON ci.cart_id = c.id INNER JOIN products p ON p.id = ci.product_id WHERE c.id = :cart ORDER BY ci.created_at');
    $stmt->execute(['cart' => $cartId]);
    return $stmt->fetchAll();
}

$items = checkout_load_items($pdo, $cartId);
if (!$items) {
    flash_set('warning', 'Keranjang masih kosong. Tambahkan produk sebelum checkout.');
    redirect('keranjang.php');
}
$subtotal = 0.0;
foreach ($items as $item) $subtotal += (float) $item['current_price'] * (int) $item['quantity'];
$shipping = $subtotal >= 500000 ? 0.0 : 15000.0;
$total = $subtotal + $shipping;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($old as $field => $value) $old[$field] = trim((string) ($_POST[$field] ?? $value));
    if (!csrf_is_valid()) {
        $error = 'Sesi formulir tidak valid. Muat ulang halaman dan coba lagi.';
    } elseif (strlen($old['recipient_name']) < 2 || strlen($old['recipient_name']) > 120) {
        $error = 'Nama penerima harus berisi 2–120 karakter.';
    } elseif (strlen($old['phone']) < 8 || strlen($old['phone']) > 30) {
        $error = 'Masukkan nomor telepon yang bisa dihubungi.';
    } elseif (strlen($old['address_line']) < 8 || strlen($old['address_line']) > 255) {
        $error = 'Alamat lengkap minimal 8 karakter dan maksimal 255 karakter.';
    } elseif ($old['city'] === '' || $old['province'] === '') {
        $error = 'Kota dan provinsi wajib diisi.';
    } elseif (!in_array($old['payment_method'], ['bank_transfer', 'cod'], true)) {
        $error = 'Metode pembayaran tidak valid.';
    } elseif (strlen($old['customer_notes']) > 1000) {
        $error = 'Catatan pesanan maksimal 1000 karakter.';
    } else {
        try {
            $pdo->beginTransaction();
            // Kunci setiap row produk untuk mencegah checkout melampaui stok.
            $lockedItems = checkout_load_items_for_update($pdo, $cartId);
            if (!$lockedItems) throw new RuntimeException('Keranjang kosong. Silakan pilih produk kembali.');
            $subtotalLocked = 0.0;
            foreach ($lockedItems as $item) {
                if (!(bool) $item['is_active']) throw new RuntimeException('Salah satu produk sudah tidak tersedia. Hapus dari keranjang.');
                if ((int) $item['quantity'] < 1 || (int) $item['quantity'] > (int) $item['stock']) throw new RuntimeException('Stok untuk ' . $item['name'] . ' tidak mencukupi. Perbarui keranjang terlebih dahulu.');
                $subtotalLocked += (float) $item['current_price'] * (int) $item['quantity'];
            }
            $shippingLocked = $subtotalLocked >= 500000 ? 0.0 : 15000.0;
            $totalLocked = $subtotalLocked + $shippingLocked;

            $stmt = $pdo->prepare('INSERT INTO addresses (user_id, recipient_name, phone, address_line, district, city, province, postal_code, is_default) VALUES (:user_id, :recipient_name, :phone, :address_line, :district, :city, :province, :postal_code, 0)');
            $stmt->execute([
                'user_id' => (int) $user['id'], 'recipient_name' => $old['recipient_name'],
                'phone' => $old['phone'], 'address_line' => $old['address_line'],
                'district' => $old['district'] !== '' ? $old['district'] : null,
                'city' => $old['city'], 'province' => $old['province'],
                'postal_code' => $old['postal_code'] !== '' ? $old['postal_code'] : null,
            ]);
            $addressId = (int) $pdo->lastInsertId();
            $orderNumber = 'NM' . date('ymd') . strtoupper(bin2hex(random_bytes(4)));
            $methodLabel = $old['payment_method'] === 'cod' ? 'COD (Bayar di Tempat)' : 'Transfer Bank (simulasi)';
            $stmt = $pdo->prepare('INSERT INTO orders (user_id, address_id, order_number, status, subtotal, shipping_cost, discount, total, payment_method, customer_notes) VALUES (:user_id, :address_id, :order_number, :status, :subtotal, :shipping_cost, 0, :total, :payment_method, :customer_notes)');
            $stmt->execute([
                'user_id' => (int) $user['id'], 'address_id' => $addressId,
                'order_number' => $orderNumber, 'status' => 'pending',
                'subtotal' => $subtotalLocked, 'shipping_cost' => $shippingLocked,
                'total' => $totalLocked, 'payment_method' => $methodLabel,
                'customer_notes' => $old['customer_notes'] !== '' ? $old['customer_notes'] : null,
            ]);
            $orderId = (int) $pdo->lastInsertId();
            $insertItem = $pdo->prepare('INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity, line_total) VALUES (:order_id, :product_id, :product_name, :unit_price, :quantity, :line_total)');
            $reduceStock = $pdo->prepare(
                'UPDATE products
                SET stock = stock - :quantity
                WHERE id = :id AND stock >= :quantity_check'
            );
            foreach ($lockedItems as $item) {
                $lineTotal = (float) $item['current_price'] * (int) $item['quantity'];
                $insertItem->execute(['order_id' => $orderId, 'product_id' => (int) $item['product_id'], 'product_name' => $item['name'], 'unit_price' => $item['current_price'], 'quantity' => (int) $item['quantity'], 'line_total' => $lineTotal]);
                $reduceStock->execute([
                    'quantity' => (int) $item['quantity'],
                    'quantity_check' => (int) $item['quantity'],
                    'id' => (int) $item['product_id']
                ]);
                if ($reduceStock->rowCount() !== 1) throw new RuntimeException('Stok berubah saat proses checkout. Silakan ulangi.');
            }
            $stmt = $pdo->prepare('INSERT INTO payments (order_id, method, amount, status) VALUES (:order_id, :method, :amount, :status)');
            $stmt->execute(['order_id' => $orderId, 'method' => $methodLabel, 'amount' => $totalLocked, 'status' => 'pending']);
            $stmt = $pdo->prepare('DELETE FROM cart_items WHERE cart_id = :cart');
            $stmt->execute(['cart' => $cartId]);
            $pdo->commit();
            redirect('pesanan-sukses.php?id=' . $orderId);
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = $exception instanceof RuntimeException ? $exception->getMessage() : 'Pesanan belum berhasil dibuat. Periksa database dan coba lagi.';
        }
    }
}

function checkout_load_items_for_update(PDO $pdo, int $cartId): array {
    $stmt = $pdo->prepare('SELECT p.id AS product_id, p.name, p.price, p.sale_price, p.stock, p.is_active, ci.quantity, CASE WHEN p.sale_price IS NOT NULL AND p.sale_price > 0 AND p.sale_price < p.price THEN p.sale_price ELSE p.price END AS current_price FROM carts c INNER JOIN cart_items ci ON ci.cart_id = c.id INNER JOIN products p ON p.id = ci.product_id WHERE c.id = :cart ORDER BY p.id FOR UPDATE');
    $stmt->execute(['cart' => $cartId]);
    return $stmt->fetchAll();
}
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#f7f8f4"><title>Checkout | NusaMart</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet"><link rel="stylesheet" href="assets/css/style.css"></head><body>
<?php require __DIR__ . '/includes/site-header.php'; ?>
<main class="container commerce-page checkout-page"><div class="page-title-row"><div><span class="eyebrow">LANGKAH TERAKHIR</span><h1>Checkout <span>pesanan.</span></h1><p>Isi alamat pengiriman dan pilih metode pembayaran.</p></div><a href="keranjang.php" class="text-link">← Kembali ke keranjang</a></div>
<?php if ($error !== ''): ?><div class="form-alert form-alert-error checkout-error" role="alert"><span>!</span><?= h($error) ?></div><?php endif; ?>
<form method="post" action="checkout.php" class="checkout-layout"><?= csrf_field() ?><section class="checkout-form-panel"><div class="checkout-step-heading"><span>01</span><div><h2>Alamat pengiriman</h2><p>Pastikan alamat mudah ditemukan oleh kurir.</p></div></div>
<div class="form-grid"><div class="form-field"><label for="recipient_name">Nama penerima *</label><input id="recipient_name" name="recipient_name" value="<?= h($old['recipient_name']) ?>" maxlength="120" autocomplete="name" required></div><div class="form-field"><label for="phone">Nomor telepon *</label><input id="phone" name="phone" value="<?= h($old['phone']) ?>" maxlength="30" autocomplete="tel" placeholder="08xxxxxxxxxx" required></div><div class="form-field form-field-wide"><label for="address_line">Alamat lengkap *</label><textarea id="address_line" name="address_line" rows="3" maxlength="255" placeholder="Nama jalan, nomor rumah, RT/RW, patokan" required><?= h($old['address_line']) ?></textarea></div><div class="form-field"><label for="district">Kecamatan</label><input id="district" name="district" value="<?= h($old['district']) ?>" maxlength="100"></div><div class="form-field"><label for="city">Kota/Kabupaten *</label><input id="city" name="city" value="<?= h($old['city']) ?>" maxlength="100" required></div><div class="form-field"><label for="province">Provinsi *</label><input id="province" name="province" value="<?= h($old['province']) ?>" maxlength="100" required></div><div class="form-field"><label for="postal_code">Kode pos</label><input id="postal_code" name="postal_code" value="<?= h($old['postal_code']) ?>" maxlength="10" inputmode="numeric"></div></div>
<div class="checkout-step-heading payment-heading"><span>02</span><div><h2>Metode pembayaran</h2><p>Pilih salah satu cara pembayaran untuk pesanan ini.</p></div></div>
<div class="payment-options"><label class="payment-option"><input type="radio" name="payment_method" value="bank_transfer" <?= $old['payment_method'] === 'bank_transfer' ? 'checked' : '' ?>><span class="payment-radio"></span><span class="payment-icon">↗</span><span class="payment-copy"><strong>Transfer bank</strong><small>Simulasi pembayaran untuk keperluan demo.</small></span></label><label class="payment-option"><input type="radio" name="payment_method" value="cod" <?= $old['payment_method'] === 'cod' ? 'checked' : '' ?>><span class="payment-radio"></span><span class="payment-icon">⌂</span><span class="payment-copy"><strong>Bayar di tempat (COD)</strong><small>Bayar kepada kurir saat pesanan diterima.</small></span></label></div>
<div class="form-field notes-field"><label for="customer_notes">Catatan pesanan (opsional)</label><textarea id="customer_notes" name="customer_notes" rows="2" maxlength="1000" placeholder="Contoh: titip ke satpam jika tidak ada orang di rumah."><?= h($old['customer_notes']) ?></textarea></div>
</section><aside class="summary-card checkout-summary"><span class="eyebrow">RINGKASAN PESANAN</span><h2>Detail belanja</h2><div class="checkout-item-list"><?php foreach ($items as $item): ?><div class="checkout-item"><img src="<?= h($item['image_url'] ?: 'assets/img/product-placeholder.svg') ?>" alt=""><div><strong><?= h($item['name']) ?></strong><small><?= (int) $item['quantity'] ?> × <?= h(money_idr($item['current_price'])) ?></small></div><b><?= h(money_idr((float) $item['current_price'] * (int) $item['quantity'])) ?></b></div><?php endforeach; ?></div><div class="summary-line"><span>Subtotal</span><strong><?= h(money_idr($subtotal)) ?></strong></div><div class="summary-line"><span>Pengiriman</span><strong><?= $shipping === 0.0 ? 'Gratis' : h(money_idr($shipping)) ?></strong></div><div class="summary-total"><span>Total pembayaran</span><strong><?= h(money_idr($total)) ?></strong></div><p class="checkout-note">Metode transfer pada proyek ini hanya simulasi dan belum terhubung ke payment gateway.</p><button class="button button-primary button-full checkout-submit" type="submit">Buat pesanan <span aria-hidden="true">↗</span></button><p class="checkout-terms">Dengan membuat pesanan, kamu mengonfirmasi alamat dan rincian produk di atas.</p></aside></form></main>
<?php require __DIR__ . '/includes/site-footer.php'; ?><div class="toast" id="toast" role="status" aria-live="polite"></div><script src="assets/js/app.js" defer></script></body></html>
