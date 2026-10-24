<?php
declare(strict_types=1);
require_once __DIR__ . '/config/init.php';
$id = max(0, (int) ($_GET['id'] ?? 0));
$product = null;
$related = [];
$dbError = false;
try {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT p.*, c.name AS category_name, c.slug AS category_slug FROM products p INNER JOIN categories c ON c.id = p.category_id WHERE p.id = :id AND p.is_active = 1 LIMIT 1');
    $stmt->execute(['id' => $id]);
    $product = $stmt->fetch() ?: null;
    if ($product) {
        $stmt = $pdo->prepare('SELECT p.id, p.name, p.short_description, p.price, p.sale_price, p.image_url, p.badge, c.name AS category_name FROM products p INNER JOIN categories c ON c.id = p.category_id WHERE p.is_active = 1 AND p.category_id = :category AND p.id <> :id ORDER BY p.is_featured DESC, p.id DESC LIMIT 4');
        $stmt->execute(['category' => (int) $product['category_id'], 'id' => $id]);
        $related = $stmt->fetchAll();
    }
} catch (Throwable $exception) { $dbError = true; }
if (!$product && !$dbError) { http_response_code(404); }
$pageTitle = $product ? $product['name'] . ' | NusaMart' : 'Produk tidak ditemukan | NusaMart';
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#f7f8f4"><title><?= h($pageTitle) ?></title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet"><link rel="stylesheet" href="assets/css/style.css"></head><body>
<?php require __DIR__ . '/includes/site-header.php'; ?>
<main class="container detail-page">
    <div class="breadcrumb"><a href="index.php">Beranda</a><span>/</span><a href="katalog.php">Katalog</a><?php if ($product): ?><span>/</span><a href="katalog.php?category=<?= h($product['category_slug']) ?>"><?= h($product['category_name']) ?></a><?php endif; ?></div>
    <?php if ($dbError): ?><div class="empty-state"><span>!</span><h3>Produk belum dapat dimuat.</h3><p>Pastikan MySQL berjalan dan database sudah diimpor.</p><a href="katalog.php" class="button button-dark">Kembali ke katalog</a></div>
    <?php elseif (!$product): ?><div class="empty-state"><span>⌕</span><h3>Produk tidak ditemukan.</h3><p>Produk mungkin sudah dihapus atau tautannya tidak tepat.</p><a href="katalog.php" class="button button-dark">Jelajahi katalog</a></div>
    <?php else: $price = product_price($product); $discount = $price < (float) $product['price']; ?>
    <section class="detail-layout">
        <div class="detail-image-panel"><img class="detail-image" src="<?= h($product['image_url'] ?: 'assets/img/product-placeholder.svg') ?>" alt="<?= h($product['name']) ?>"><span class="detail-image-caption">NUSAMART · PILIHAN UNTUKMU</span></div>
        <div class="detail-info">
            <span class="eyebrow"><?= h(strtoupper($product['category_name'])) ?></span>
            <?php if (!empty($product['badge'])): ?><span class="detail-badge"><?= h($product['badge']) ?></span><?php endif; ?>
            <h1><?= h($product['name']) ?></h1>
            <p class="detail-short"><?= h($product['short_description']) ?></p>
            <div class="detail-price"><strong><?= h(money_idr($price)) ?></strong><?php if ($discount): ?><del><?= h(money_idr($product['price'])) ?></del><span class="discount-percent">Hemat <?= (int) round((1 - $price / (float) $product['price']) * 100) ?>%</span><?php endif; ?></div>
            <div class="detail-stock <?= (int) $product['stock'] > 0 ? 'stock-available' : 'stock-empty' ?>"><span class="stock-dot"></span><?= (int) $product['stock'] > 0 ? 'Stok tersedia · ' . (int) $product['stock'] . ' unit' : 'Stok sedang habis' ?></div>
            <div class="detail-description"><h2>Deskripsi produk</h2><p><?= nl2br(h($product['description'] ?: $product['short_description'])) ?></p></div>
            <?php if ((int) $product['stock'] > 0): ?>
            <form class="detail-add-form" method="post" action="keranjang.php">
                <?= csrf_field() ?><input type="hidden" name="action" value="add"><input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                <div class="quantity-control"><button type="button" data-quantity-step="-1" aria-label="Kurangi jumlah">−</button><input type="number" name="quantity" value="1" min="1" max="<?= (int) $product['stock'] ?>" aria-label="Jumlah produk"><button type="button" data-quantity-step="1" aria-label="Tambah jumlah">+</button></div>
                <button class="button button-primary detail-add-button" type="submit">Tambah ke keranjang <span aria-hidden="true">↗</span></button>
            </form>
            <?php else: ?><button class="button button-disabled" type="button" disabled>Stok habis</button><?php endif; ?>
            <div class="detail-perks"><div><span>✓</span><div><strong>Produk terkurasi</strong><small>Pilihan untuk keseharianmu</small></div></div><div><span>↗</span><div><strong>Checkout praktis</strong><small>Alamat dan metode dikonfirmasi saat checkout</small></div></div></div>
        </div>
    </section>
    <?php if ($related): ?><section class="section-space detail-related"><div class="section-heading"><div><span class="eyebrow">KAMU MUNGKIN SUKA</span><h2>Pilihan lain di kategori ini.</h2></div><a class="text-link" href="katalog.php?category=<?= h($product['category_slug']) ?>">Lihat kategori →</a></div><div class="product-grid"><?php foreach ($related as $item): $itemPrice = product_price($item); ?><article class="product-card"><a class="product-card-link" href="detail.php?id=<?= (int) $item['id'] ?>"><div class="product-image-wrap"><img class="product-image" src="<?= h($item['image_url'] ?: 'assets/img/product-placeholder.svg') ?>" alt="<?= h($item['name']) ?>" loading="lazy"></div><div class="product-card-info"><span class="product-category"><?= h($item['category_name']) ?></span><h3><?= h($item['name']) ?></h3><p><?= h($item['short_description']) ?></p><div class="product-price-row"><strong><?= h(money_idr($itemPrice)) ?></strong></div></div></a></article><?php endforeach; ?></div></section><?php endif; ?>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/includes/site-footer.php'; ?><div class="toast" id="toast" role="status" aria-live="polite"></div><script src="assets/js/app.js" defer></script></body></html>
