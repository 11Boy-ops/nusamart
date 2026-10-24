<?php
declare(strict_types=1);
require_once __DIR__ . '/config/init.php';

$q = trim((string) ($_GET['q'] ?? ''));
if (str_len_u($q) > 100) $q = str_sub_u($q, 0, 100);
$categorySlug = trim((string) ($_GET['category'] ?? ''));
$sort = (string) ($_GET['sort'] ?? 'recommended');
$allowedSorts = ['recommended', 'newest', 'price_asc', 'price_desc', 'name'];
if (!in_array($sort, $allowedSorts, true)) $sort = 'recommended';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 12;
$categories = [];
$products = [];
$totalProducts = 0;
$dbError = false;
$currentCategory = null;

$sortSql = [
    'recommended' => 'p.is_featured DESC, p.created_at DESC, p.id DESC',
    'newest' => 'p.created_at DESC, p.id DESC',
    'price_asc' => 'current_price ASC, p.name ASC',
    'price_desc' => 'current_price DESC, p.name ASC',
    'name' => 'p.name ASC',
][$sort];

try {
    $pdo = db();
    $categories = $pdo->query('SELECT id, name, slug, icon, description FROM categories WHERE is_active = 1 ORDER BY name')->fetchAll();
    if ($categorySlug !== '') {
        $categoryStmt = $pdo->prepare('SELECT id, name, slug FROM categories WHERE slug = :slug AND is_active = 1 LIMIT 1');
        $categoryStmt->execute(['slug' => $categorySlug]);
        $currentCategory = $categoryStmt->fetch() ?: null;
    }

    $where = ['p.is_active = 1'];
    $params = [];
    if ($q !== '') {
        $where[] = '(p.name LIKE :q1 OR p.short_description LIKE :q2 OR p.description LIKE :q3 OR c.name LIKE :q4)';
        $like = '%' . $q . '%';
        $params += ['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like];
    }
    if ($categorySlug !== '') {
        if ($currentCategory) {
            $where[] = 'c.slug = :category_slug';
            $params['category_slug'] = $categorySlug;
        } else {
            $where[] = '1 = 0';
        }
    }
    $whereSql = implode(' AND ', $where);
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM products p INNER JOIN categories c ON c.id = p.category_id WHERE {$whereSql}");
    $countStmt->execute($params);
    $totalProducts = (int) $countStmt->fetchColumn();
    $pages = max(1, (int) ceil($totalProducts / $perPage));
    $page = min($page, $pages);
    $offset = ($page - 1) * $perPage;

    $sql = "SELECT p.id, p.name, p.short_description, p.description, p.price, p.sale_price, p.stock, p.image_url, p.badge, p.is_featured, p.created_at, c.name AS category_name, c.slug AS category_slug, CASE WHEN p.sale_price IS NOT NULL AND p.sale_price > 0 AND p.sale_price < p.price THEN p.sale_price ELSE p.price END AS current_price FROM products p INNER JOIN categories c ON c.id = p.category_id WHERE {$whereSql} ORDER BY {$sortSql} LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $products = $stmt->fetchAll();
} catch (Throwable $exception) {
    $dbError = true;
    $pages = 1;
    $categories = [];
}

function katalog_url(array $overrides = []): string {
    $values = [
        'q' => trim((string) ($_GET['q'] ?? '')),
        'category' => trim((string) ($_GET['category'] ?? '')),
        'sort' => (string) ($_GET['sort'] ?? 'recommended'),
        'page' => max(1, (int) ($_GET['page'] ?? 1)),
    ];
    foreach ($overrides as $key => $value) $values[$key] = $value;
    $values = array_filter($values, static fn($value) => $value !== '' && $value !== null && $value !== 'recommended' && !($value === 1 || $value === '1'));
    return 'katalog.php' . ($values ? '?' . http_build_query($values) : '');
}

$pageTitle = 'Katalog Produk | NusaMart';
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f7f8f4">
    <title><?= h($pageTitle) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php require __DIR__ . '/includes/site-header.php'; ?>
<main>
    <section class="catalog-hero"><div class="container"><span class="eyebrow">KOLEKSI NUSAMART</span><h1>Temukan barang yang <span>pas untukmu.</span></h1><p>Jelajahi semua pilihan atau gunakan filter untuk menemukan yang kamu cari.</p>
        <form class="catalog-search" action="katalog.php" method="get"><label class="sr-only" for="catalog-q">Cari produk</label><input id="catalog-q" type="search" name="q" value="<?= h($q) ?>" placeholder="Cari nama produk, kategori, atau kebutuhan..."><button class="button button-dark" type="submit">Cari produk ↗</button></form>
    </div></section>
    <section class="container catalog-layout section-space">
        <aside class="catalog-sidebar"><div class="sidebar-heading"><h2>Kategori</h2><a href="katalog.php">Reset</a></div>
            <a class="category-filter <?= $categorySlug === '' ? 'is-active' : '' ?>" href="<?= h(katalog_url(['category' => '', 'page' => 1])) ?>">Semua produk <span><?= $totalProducts ?></span></a>
            <?php foreach ($categories as $category): ?><a class="category-filter <?= $categorySlug === $category['slug'] ? 'is-active' : '' ?>" href="<?= h(katalog_url(['category' => $category['slug'], 'page' => 1])) ?>"><span><?= h($category['icon'] ?? '✦') ?> <?= h($category['name']) ?></span><?php if ($categorySlug === $category['slug']): ?><span>✓</span><?php endif; ?></a><?php endforeach; ?>
            <div class="sidebar-note"><strong>Tips belanja</strong><p>Periksa deskripsi dan stok produk sebelum menambahkan ke keranjang.</p></div>
        </aside>
        <div class="catalog-results">
            <div class="catalog-results-heading"><div><span class="eyebrow">KATALOG PRODUK</span><h2><?php if ($currentCategory) echo h($currentCategory['name']); elseif ($q !== '') echo 'Hasil: “' . h($q) . '”'; else echo 'Semua produk'; ?></h2><p><?= $totalProducts ?> produk tersedia<?= $q !== '' ? ' untuk pencarian ini' : '' ?>.</p></div>
                <form action="katalog.php" method="get" class="sort-form">
                    <?php if ($q !== ''): ?><input type="hidden" name="q" value="<?= h($q) ?>"><?php endif; ?>
                    <?php if ($categorySlug !== ''): ?><input type="hidden" name="category" value="<?= h($categorySlug) ?>"><?php endif; ?>
                    <label for="sort">Urutkan</label><select id="sort" name="sort" onchange="this.form.submit()"><option value="recommended" <?= $sort === 'recommended' ? 'selected' : '' ?>>Rekomendasi</option><option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Terbaru</option><option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Harga terendah</option><option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Harga tertinggi</option><option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Nama A–Z</option></select>
                </form>
            </div>
            <?php if ($dbError): ?><div class="form-alert form-alert-error">Database belum terhubung. Nyalakan MySQL dan impor database.sql.</div>
            <?php elseif (!$products): ?><div class="empty-state"><span>⌕</span><h3>Produk belum ditemukan.</h3><p>Coba kata kunci lain atau pilih kategori berbeda.</p><a href="katalog.php" class="button button-dark">Tampilkan semua produk</a></div>
            <?php else: ?>
                <div class="product-grid catalog-product-grid">
                <?php foreach ($products as $index => $product): $price = product_price($product); $discount = $price < (float) $product['price']; ?>
                    <article class="product-card" style="--card-delay: <?= (int) ($index % 12) * 35 ?>ms">
                        <a class="product-card-link" href="detail.php?id=<?= (int) $product['id'] ?>">
                            <div class="product-image-wrap"><img class="product-image" src="<?= h($product['image_url'] ?: 'assets/img/product-placeholder.svg') ?>" alt="<?= h($product['name']) ?>" loading="lazy"><?php if (!empty($product['badge'])): ?><span class="product-badge"><?= h($product['badge']) ?></span><?php endif; ?><span class="product-image-mark" aria-hidden="true">n.</span></div>
                            <div class="product-card-info"><span class="product-category"><?= h($product['category_name']) ?></span><h3><?= h($product['name']) ?></h3><p><?= h($product['short_description']) ?></p><div class="product-price-row"><strong><?= h(money_idr($price)) ?></strong><?php if ($discount): ?><del><?= h(money_idr($product['price'])) ?></del><?php endif; ?></div></div>
                        </a>
                        <div class="catalog-card-bottom"><span class="stock-label <?= (int) $product['stock'] > 0 ? 'stock-available' : 'stock-empty' ?>"><?= (int) $product['stock'] > 0 ? 'Stok tersedia' : 'Stok habis' ?></span><a class="mini-link" href="detail.php?id=<?= (int) $product['id'] ?>">Lihat detail ↗</a></div>
                    </article>
                <?php endforeach; ?>
                </div>
                <?php if ($pages > 1): ?><nav class="pagination" aria-label="Halaman katalog"><?php if ($page > 1): ?><a href="<?= h(katalog_url(['page' => $page - 1])) ?>">← Sebelumnya</a><?php endif; ?><span>Halaman <?= $page ?> dari <?= $pages ?></span><?php if ($page < $pages): ?><a href="<?= h(katalog_url(['page' => $page + 1])) ?>">Berikutnya →</a><?php endif; ?></nav><?php endif; ?>
            <?php endif; ?>
        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/site-footer.php'; ?><div class="toast" id="toast" role="status" aria-live="polite"></div><script src="assets/js/app.js" defer></script>
</body></html>
