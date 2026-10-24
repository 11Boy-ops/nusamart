<?php
declare(strict_types=1);
require_once __DIR__ . '/config/init.php';

// Simpan permintaan tambah produk jika pengunjung belum masuk.
if (!current_user() && $_SERVER['REQUEST_METHOD'] === 'POST'
    && (string) ($_POST['action'] ?? '') === 'add') {
    if (!csrf_is_valid()) {
        flash_set('error', 'Sesi formulir tidak valid. Muat ulang halaman dan coba lagi.');
        redirect('detail.php?id=' . max(0, (int) ($_POST['product_id'] ?? 0)));
    }
    $_SESSION['pending_cart_add'] = [
        'product_id' => max(0, (int) ($_POST['product_id'] ?? 0)),
        'quantity' => max(1, min(99, (int) ($_POST['quantity'] ?? 1))),
    ];
    $_SESSION['after_login'] = 'keranjang.php';
    flash_set('warning', 'Masuk terlebih dahulu. Produk akan ditambahkan setelah kamu berhasil masuk.');
    redirect('login.php');
}

require_login('keranjang.php');
$user = current_user();
$pdo = db();
$cartId = get_or_create_cart_id($pdo, (int) $user['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        flash_set('error', 'Sesi formulir tidak valid. Muat ulang halaman dan coba lagi.');
        redirect('keranjang.php');
    }
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'add') {
            $productId = max(0, (int) ($_POST['product_id'] ?? 0));
            $quantity = max(1, min(99, (int) ($_POST['quantity'] ?? 1)));
            $stmt = $pdo->prepare('SELECT id, name, stock, is_active FROM products WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $productId]);
            $product = $stmt->fetch();
            if (!$product || !(bool) $product['is_active']) {
                flash_set('error', 'Produk tidak tersedia.');
            } elseif ((int) $product['stock'] < 1) {
                flash_set('error', 'Stok produk sedang habis.');
            } else {
                $stmt = $pdo->prepare('SELECT quantity FROM cart_items WHERE cart_id = :cart AND product_id = :product LIMIT 1');
                $stmt->execute(['cart' => $cartId, 'product' => $productId]);
                $existing = $stmt->fetchColumn();
                $newQuantity = ($existing === false ? 0 : (int) $existing) + $quantity;
                if ($newQuantity > (int) $product['stock']) {
                    flash_set('error', 'Jumlah melebihi stok yang tersedia (' . (int) $product['stock'] . ' unit).');
                } else {
                    $stmt = $pdo->prepare('INSERT INTO cart_items (cart_id, product_id, quantity) VALUES (:cart, :product, :quantity) ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)');
                    $stmt->execute(['cart' => $cartId, 'product' => $productId, 'quantity' => $quantity]);
                    flash_set('success', $product['name'] . ' berhasil ditambahkan ke keranjang.');
                }
            }
        } elseif ($action === 'update') {
            $quantities = $_POST['quantity'] ?? [];
            if (!is_array($quantities)) throw new RuntimeException('Data jumlah produk tidak valid.');
            $pdo->beginTransaction();
            foreach ($quantities as $productIdRaw => $quantityRaw) {
                $productId = (int) $productIdRaw;
                $quantity = (int) $quantityRaw;
                $stmt = $pdo->prepare('SELECT p.stock, p.is_active FROM cart_items ci INNER JOIN products p ON p.id = ci.product_id WHERE ci.cart_id = :cart AND ci.product_id = :product FOR UPDATE');
                $stmt->execute(['cart' => $cartId, 'product' => $productId]);
                $item = $stmt->fetch();
                if (!$item) continue;
                if ($quantity < 1) {
                    $stmt = $pdo->prepare('DELETE FROM cart_items WHERE cart_id = :cart AND product_id = :product');
                    $stmt->execute(['cart' => $cartId, 'product' => $productId]);
                } elseif (!(bool) $item['is_active'] || $quantity > (int) $item['stock']) {
                    throw new RuntimeException('Jumlah salah satu produk melebihi stok tersedia. Periksa jumlah kembali.');
                } else {
                    $stmt = $pdo->prepare('UPDATE cart_items SET quantity = :quantity WHERE cart_id = :cart AND product_id = :product');
                    $stmt->execute(['quantity' => $quantity, 'cart' => $cartId, 'product' => $productId]);
                }
            }
            $pdo->commit();
            flash_set('success', 'Keranjang berhasil diperbarui.');
        } elseif ($action === 'remove') {
            $productId = max(0, (int) ($_POST['product_id'] ?? 0));
            $stmt = $pdo->prepare('DELETE FROM cart_items WHERE cart_id = :cart AND product_id = :product');
            $stmt->execute(['cart' => $cartId, 'product' => $productId]);
            flash_set('success', 'Produk dihapus dari keranjang.');
        } else {
            flash_set('error', 'Aksi keranjang tidak dikenal.');
        }
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash_set('error', $exception instanceof RuntimeException ? $exception->getMessage() : 'Keranjang belum berhasil diperbarui. Coba lagi.');
    }
    redirect('keranjang.php');
}

$stmt = $pdo->prepare('SELECT p.id AS product_id, p.name, p.short_description, p.price, p.sale_price, p.stock, p.is_active, p.image_url, c.name AS category_name, ci.quantity, CASE WHEN p.sale_price IS NOT NULL AND p.sale_price > 0 AND p.sale_price < p.price THEN p.sale_price ELSE p.price END AS current_price FROM carts c0 INNER JOIN cart_items ci ON ci.cart_id = c0.id INNER JOIN products p ON p.id = ci.product_id INNER JOIN categories c ON c.id = p.category_id WHERE c0.id = :cart ORDER BY ci.created_at DESC');
$stmt->execute(['cart' => $cartId]);
$items = $stmt->fetchAll();
$subtotal = 0.0;
foreach ($items as $item) $subtotal += (float) $item['current_price'] * (int) $item['quantity'];
$shipping = $subtotal >= 500000 || $subtotal == 0 ? 0 : 15000;
$total = $subtotal + $shipping;
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#f7f8f4"><title>Keranjang Belanja | NusaMart</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet"><link rel="stylesheet" href="assets/css/style.css"></head><body>
<?php require __DIR__ . '/includes/site-header.php'; ?>
<main class="container commerce-page"><div class="page-title-row"><div><span class="eyebrow">RINGKASAN BELANJA</span><h1>Keranjang <span>belanjamu.</span></h1><p>Pastikan produk dan jumlahnya sudah sesuai sebelum checkout.</p></div><a href="katalog.php" class="text-link">← Lanjut belanja</a></div>
<?php if (!$items): ?><div class="empty-state commerce-empty"><span>♡</span><h3>Keranjangmu masih kosong.</h3><p>Yuk, jelajahi katalog dan temukan barang yang kamu butuhkan.</p><a class="button button-primary" href="katalog.php">Jelajahi katalog ↗</a></div>
<?php else: ?><div class="cart-layout"><section class="cart-panel"><form method="post" action="keranjang.php" id="cartUpdateForm"><?= csrf_field() ?><input type="hidden" name="action" value="update"><div class="cart-panel-heading"><h2>Produk pilihanmu</h2><span><?= count($items) ?> jenis produk</span></div>
<?php foreach ($items as $item): $stockInvalid = !(bool) $item['is_active'] || (int) $item['quantity'] > (int) $item['stock']; ?>
<div class="cart-item"><a href="detail.php?id=<?= (int) $item['product_id'] ?>" class="cart-item-image"><img src="<?= h($item['image_url'] ?: 'assets/img/product-placeholder.svg') ?>" alt="<?= h($item['name']) ?>"></a><div class="cart-item-main"><span class="product-category"><?= h($item['category_name']) ?></span><h3><a href="detail.php?id=<?= (int) $item['product_id'] ?>"><?= h($item['name']) ?></a></h3><p><?= h($item['short_description']) ?></p><div class="cart-item-bottom"><div class="cart-quantity"><label class="sr-only" for="quantity-<?= (int) $item['product_id'] ?>">Jumlah <?= h($item['name']) ?></label><button type="button" data-quantity-target="quantity-<?= (int) $item['product_id'] ?>" data-quantity-step="-1" aria-label="Kurangi jumlah">−</button><input id="quantity-<?= (int) $item['product_id'] ?>" name="quantity[<?= (int) $item['product_id'] ?>]" type="number" min="0" max="<?= (int) $item['stock'] ?>" value="<?= (int) $item['quantity'] ?>"><button type="button" data-quantity-target="quantity-<?= (int) $item['product_id'] ?>" data-quantity-step="1" aria-label="Tambah jumlah">+</button></div><span class="stock-label <?= $stockInvalid ? 'stock-empty' : 'stock-available' ?>"><?= $stockInvalid ? 'Stok berubah—periksa jumlah' : 'Stok: ' . (int) $item['stock'] ?></span></div></div><div class="cart-item-end"><strong><?= h(money_idr((float) $item['current_price'] * (int) $item['quantity'])) ?></strong><button class="remove-link" type="button" data-remove-product="<?= (int) $item['product_id'] ?>">Hapus</button></div></div>
<?php endforeach; ?><div class="cart-form-actions"><button class="button button-outline" type="submit">Perbarui keranjang</button><span class="cart-helper">Isi jumlah 0 untuk menghapus produk saat memperbarui.</span></div></form>
<?php foreach ($items as $item): ?><form method="post" action="keranjang.php" id="remove-form-<?= (int) $item['product_id'] ?>" hidden><?= csrf_field() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="product_id" value="<?= (int) $item['product_id'] ?>"></form><?php endforeach; ?></section>
<aside class="summary-card"><span class="eyebrow">RINGKASAN PESANAN</span><h2>Hampir jadi milikmu.</h2><div class="summary-line"><span>Subtotal</span><strong><?= h(money_idr($subtotal)) ?></strong></div><div class="summary-line"><span>Pengiriman</span><strong><?= $shipping === 0 ? 'Gratis' : h(money_idr($shipping)) ?></strong></div><?php if ($shipping > 0): ?><p class="shipping-hint">Tambah belanja <?= h(money_idr(500000 - $subtotal)) ?> lagi untuk gratis ongkir.</p><?php else: ?><p class="shipping-hint">Kamu mendapatkan pengiriman gratis.</p><?php endif; ?><div class="summary-total"><span>Total sementara</span><strong><?= h(money_idr($total)) ?></strong></div><a href="checkout.php" class="button button-primary button-full">Lanjut ke checkout ↗</a><div class="summary-security"><span>✓</span> Detail pesanan dikonfirmasi sebelum dibuat.</div></aside></div><?php endif; ?></main>
<?php require __DIR__ . '/includes/site-footer.php'; ?><div class="toast" id="toast" role="status" aria-live="polite"></div><script src="assets/js/app.js" defer></script><script>
document.querySelectorAll('[data-remove-product]').forEach(button => { button.addEventListener('click', function () { if (confirm('Hapus produk ini dari keranjang?')) { const form = document.getElementById('remove-form-' + this.dataset.removeProduct); if (form) form.submit(); } }); });
</script></body></html>
