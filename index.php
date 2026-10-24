<?php
declare(strict_types=1);
require_once __DIR__ . '/config/init.php';

$q = trim((string) ($_GET['q'] ?? ''));
if (str_len_u($q) > 100) {
    $q = str_sub_u($q, 0, 100);
}
$dbError = false;
$categories = [];
$products = [];

$fallbackCategories = [
    ['name' => 'Elektronik', 'slug' => 'elektronik', 'icon' => '⌁', 'description' => 'Perangkat pilihan'],
    ['name' => 'Fashion', 'slug' => 'fashion', 'icon' => '✳', 'description' => 'Gaya setiap hari'],
    ['name' => 'Rumah & Gaya Hidup', 'slug' => 'rumah-gaya-hidup', 'icon' => '⌂', 'description' => 'Ruang lebih nyaman'],
    ['name' => 'Kebutuhan Harian', 'slug' => 'kebutuhan-harian', 'icon' => '♡', 'description' => 'Praktis untuk aktivitas'],
];
$fallbackProducts = [
    ['id'=>1,'name'=>'Headphone Studio','short_description'=>'Suara jernih, desain ringan untuk menemani fokus.','price'=>549000,'sale_price'=>429000,'image_url'=>'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?auto=format&fit=crop&w=900&q=85','badge'=>'Paling dicari','category_name'=>'Elektronik'],
    ['id'=>2,'name'=>'Tas Daily Canvas','short_description'=>'Teman ringkas untuk rutinitas dari pagi sampai sore.','price'=>289000,'sale_price'=>219000,'image_url'=>'https://images.unsplash.com/photo-1548036328-c9fa89d128fa?auto=format&fit=crop&w=900&q=85','badge'=>'Hemat 24%','category_name'=>'Fashion'],
    ['id'=>3,'name'=>'Tumbler Minimal','short_description'=>'Jaga minuman tetap dekat di sepanjang aktivitas.','price'=>159000,'sale_price'=>null,'image_url'=>'https://images.unsplash.com/photo-1602143407151-7111542de6e8?auto=format&fit=crop&w=900&q=85','badge'=>'Pilihan baru','category_name'=>'Rumah & Gaya Hidup'],
    ['id'=>4,'name'=>'Smartwatch Active','short_description'=>'Pantau aktivitas harian dengan tampilan modern.','price'=>699000,'sale_price'=>559000,'image_url'=>'https://images.unsplash.com/photo-1523275335684-37898b6baf30?auto=format&fit=crop&w=900&q=85','badge'=>'Hemat 20%','category_name'=>'Elektronik'],
    ['id'=>5,'name'=>'Sneaker Urban','short_description'=>'Langkah nyaman dengan siluet yang versatile.','price'=>599000,'sale_price'=>479000,'image_url'=>'https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=900&q=85','badge'=>'Favorit','category_name'=>'Fashion'],
    ['id'=>6,'name'=>'Lampu Ambient','short_description'=>'Ciptakan sudut ruang yang hangat dan nyaman.','price'=>239000,'sale_price'=>189000,'image_url'=>'https://images.unsplash.com/photo-1507473885765-e6ed057f782c?auto=format&fit=crop&w=900&q=85','badge'=>'Hemat 21%','category_name'=>'Rumah & Gaya Hidup'],
    ['id'=>7,'name'=>'Kamera Pocket','short_description'=>'Abadikan momen kecil dengan gaya klasik.','price'=>1299000,'sale_price'=>1149000,'image_url'=>'https://images.unsplash.com/photo-1516035069371-29a1b244cc32?auto=format&fit=crop&w=900&q=85','badge'=>null,'category_name'=>'Elektronik'],
    ['id'=>8,'name'=>'Botol Travel','short_description'=>'Sederhana, ringan, dan siap menemani perjalanan.','price'=>99000,'sale_price'=>null,'image_url'=>'https://images.unsplash.com/photo-1523362628745-0c100150b504?auto=format&fit=crop&w=900&q=85','badge'=>null,'category_name'=>'Kebutuhan Harian'],
];

try {
    $pdo = db();
    $categoryStmt = $pdo->query('SELECT name, slug, icon, description FROM categories WHERE is_active = 1 ORDER BY id ASC LIMIT 8');
    $categories = $categoryStmt->fetchAll();

    if ($q !== '') {
        $search = '%' . $q . '%';
        $productStmt = $pdo->prepare(
            'SELECT p.id, p.name, p.short_description, p.price, p.sale_price, p.image_url, p.badge, c.name AS category_name
             FROM products p INNER JOIN categories c ON c.id = p.category_id
             WHERE p.is_active = 1 AND (p.name LIKE :s1 OR p.short_description LIKE :s2 OR c.name LIKE :s3)
             ORDER BY p.is_featured DESC, p.created_at DESC LIMIT 16'
        );
        $productStmt->execute(['s1' => $search, 's2' => $search, 's3' => $search]);
    } else {
        $productStmt = $pdo->query(
            'SELECT p.id, p.name, p.short_description, p.price, p.sale_price, p.image_url, p.badge, c.name AS category_name
             FROM products p INNER JOIN categories c ON c.id = p.category_id
             WHERE p.is_active = 1 AND p.is_featured = 1
             ORDER BY p.id ASC LIMIT 8'
        );
    }
    $products = $productStmt->fetchAll();
    if (!$categories) $categories = $fallbackCategories;
} catch (Throwable $exception) {
    // Tampilan tetap dapat dilihat sebelum database diimpor; login/registrasi membutuhkan database.
    $dbError = true;
    $categories = $fallbackCategories;
    $products = $fallbackProducts;
    if ($q !== '') {
        $needle = str_lower_u($q);
        $products = array_values(array_filter($products, static function (array $product) use ($needle): bool {
            return str_contains(str_lower_u($product['name']), $needle)
                || str_contains(str_lower_u($product['short_description']), $needle)
                || str_contains(str_lower_u($product['category_name']), $needle);
        }));
    }
}

$pageTitle = $q !== '' ? 'Hasil pencarian: ' . $q . ' | NusaMart' : 'NusaMart — Temukan favorit barumu';
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f7f8f4">
    <meta name="description" content="NusaMart, tempat menemukan barang pilihan untuk kebutuhan sehari-hari dengan pengalaman belanja yang lebih sederhana.">
    <title><?= h($pageTitle) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php require __DIR__ . '/includes/site-header.php'; ?>
<main>
    <?php if ($dbError): ?>
        <div class="container setup-notice"><strong>Mode pratinjau:</strong> produk contoh ditampilkan. Impor file <code>database.sql</code> melalui phpMyAdmin agar login, registrasi, dan data produk menggunakan database.</div>
    <?php endif; ?>

    <?php if ($q === ''): ?>
    <section class="hero-section">
        <div class="container hero-grid">
            <div class="hero-copy">
                <div class="eyebrow"><span class="eyebrow-spark">✦</span> TEMUKAN YANG KAMU BUTUHKAN</div>
                <h1>Hal kecil,<br>buat hari lebih <span>baik.</span></h1>
                <p class="hero-description">Temukan barang pilihan untuk rutinitasmu. Dari yang kamu butuhkan sampai yang bikin kamu tersenyum.</p>
                <div class="hero-ctas">
                    <a class="button button-primary button-large" href="#pilihan">Jelajahi produk <span aria-hidden="true">↗</span></a>
                    <a class="text-link" href="#kategori">Lihat kategori <span aria-hidden="true">→</span></a>
                </div>
                <div class="hero-proof">
                    <div class="avatar-stack" aria-hidden="true"><span>R</span><span>A</span><span>D</span><span>+</span></div>
                    <div><div class="star-row" aria-label="Lima dari lima bintang">★★★★★ <b>4.9/5</b></div><p>Disukai pelanggan kami</p></div>
                </div>
            </div>
            <div class="hero-visual">
                <div class="hero-photo-frame">
                    <img class="hero-photo" src="https://images.unsplash.com/photo-1607082349566-187342175e2f?auto=format&fit=crop&w=1400&q=90" alt="Berbagai pilihan barang belanja" fetchpriority="high">
                    <div class="hero-photo-overlay"></div>
                    <div class="hero-photo-label"><span class="label-icon">✳</span><span><small>CURATED FOR YOU</small><strong>Good finds, good days.</strong></span></div>
                </div>
                <div class="floating-card floating-card-top"><span class="floating-icon floating-icon-green">✓</span><span><strong>Pilihan berkualitas</strong><small>Disiapkan dengan cermat</small></span></div>
                <div class="floating-card floating-card-bottom"><span class="floating-icon floating-icon-orange">♡</span><span><strong>Favorit harian</strong><small>Mulai dari Rp99.000</small></span></div>
                <div class="hero-orbit orbit-one"></div><div class="hero-orbit orbit-two"></div>
            </div>
        </div>
        <div class="container hero-bottom-line"><span>01 — YOUR DAILY FINDS</span><span class="bottom-line-rule"></span><span>MADE FOR EVERYDAY LIVING <b>↘</b></span></div>
    </section>
    <?php else: ?>
    <section class="search-results-intro"><div class="container"><span class="eyebrow"><span class="eyebrow-spark">⌕</span> HASIL PENCARIAN</span><h1>Hasil untuk <span>“<?= h($q) ?>”</span></h1><p><?= count($products) ?> produk ditemukan. Coba kata kunci lain jika belum menemukan yang kamu cari.</p><a class="text-link" href="index.php">← Kembali ke beranda</a></div></section>
    <?php endif; ?>

    <section class="trust-strip" aria-label="Keunggulan NusaMart">
        <div class="container trust-grid">
            <div class="trust-item"><span class="trust-icon"><svg viewBox="0 0 24 24"><path d="M3 7h11v10H3zM14 10h4l3 3v4h-7z"/><circle cx="7.5" cy="18" r="2"/><circle cx="17.5" cy="18" r="2"/></svg></span><div><strong>Belanja praktis</strong><span>Semua dalam satu tempat</span></div></div>
            <div class="trust-item"><span class="trust-icon"><svg viewBox="0 0 24 24"><path d="M12 3 19 6v5c0 4.5-2.8 7.7-7 10-4.2-2.3-7-5.5-7-10V6z"/><path d="m9 12 2 2 4-4"/></svg></span><div><strong>Pilihan terkurasi</strong><span>Produk pilihan untukmu</span></div></div>
            <div class="trust-item"><span class="trust-icon"><svg viewBox="0 0 24 24"><path d="M4 5h16v12H9l-5 3z"/><path d="M8 9h8M8 13h5"/></svg></span><div><strong>Bantuan yang ramah</strong><span>Kami siap membantu</span></div></div>
            <div class="trust-item"><span class="trust-icon"><svg viewBox="0 0 24 24"><path d="M12 21s-8-4.5-8-11a4.5 4.5 0 0 1 8-2.8A4.5 4.5 0 0 1 20 10c0 6.5-8 11-8 11z"/></svg></span><div><strong>Dibuat sepenuh hati</strong><span>Nyaman dari awal sampai akhir</span></div></div>
        </div>
    </section>

    <section class="category-section section-space" id="kategori">
        <div class="container">
            <div class="section-heading">
                <div><span class="eyebrow">PILIH SESUAI KEBUTUHAN</span><h2>Mulai dari <span>kategori.</span></h2></div>
                <a class="text-link" href="katalog.php">Lihat semua produk <span aria-hidden="true">→</span></a>
            </div>
            <div class="category-grid">
                <?php foreach ($categories as $index => $category): ?>
                    <a class="category-card category-tone-<?= (int) $index % 4 ?>" href="katalog.php?category=<?= rawurlencode((string) $category['slug']) ?>">
                        <span class="category-symbol" aria-hidden="true"><?= h($category['icon'] ?? '✦') ?></span>
                        <span class="category-text"><strong><?= h($category['name']) ?></strong><small><?= h($category['description'] ?? 'Jelajahi koleksi pilihan') ?></small></span>
                        <span class="category-arrow" aria-hidden="true">↗</span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="products-section section-space" id="pilihan">
        <div class="container">
            <div class="section-heading products-heading">
                <div><span class="eyebrow"><?= $q !== '' ? 'HASIL PENCARIAN' : 'DIPILIH UNTUK KAMU' ?></span><h2><?= $q !== '' ? 'Barangkali ini yang kamu cari.' : 'Punya tempat di <span>favoritmu.</span>' ?></h2><p><?= $q !== '' ? 'Temukan produk yang sesuai dengan kebutuhanmu.' : 'Pilihan simpel, desain menarik, dan berguna untuk keseharian.' ?></p></div>
                <?php if ($q !== ''): ?><a class="text-link" href="index.php">Hapus pencarian <span aria-hidden="true">×</span></a><?php else: ?><span class="product-count-pill">Pilihan minggu ini <span>✳</span></span><?php endif; ?>
            </div>

            <?php if (!$products): ?>
                <div class="empty-state"><span>⌕</span><h3>Belum ada produk yang cocok.</h3><p>Coba kata kunci yang lebih singkat atau cari berdasarkan kategori.</p><a class="button button-dark" href="index.php">Kembali ke beranda</a></div>
            <?php else: ?>
                <div class="product-grid">
                    <?php foreach ($products as $index => $product):
                        $currentPrice = ($product['sale_price'] !== null && (float) $product['sale_price'] > 0) ? $product['sale_price'] : $product['price'];
                        $hasDiscount = $product['sale_price'] !== null && (float) $product['sale_price'] > 0 && (float) $product['sale_price'] < (float) $product['price'];
                    ?>
                    <article class="product-card" style="--card-delay: <?= (int) $index * 60 ?>ms">
                        <a class="product-card-link" href="detail.php?id=<?= (int) $product['id'] ?>" aria-label="Lihat detail <?= h($product['name']) ?>">
                        <div class="product-image-wrap">
                            <img class="product-image" src="<?= h($product['image_url'] ?? '') ?>" alt="<?= h($product['name']) ?>" loading="lazy">
                            <?php if (!empty($product['badge'])): ?><span class="product-badge"><?= h($product['badge']) ?></span><?php endif; ?>
                            <span class="product-image-mark" aria-hidden="true">n.</span>
                        </div>
                        <div class="product-card-info"><span class="product-category"><?= h($product['category_name'] ?? 'Pilihan NusaMart') ?></span><h3><?= h($product['name']) ?></h3><p><?= h($product['short_description']) ?></p>
                            <div class="product-price-row"><strong><?= h(money_idr($currentPrice)) ?></strong><?php if ($hasDiscount): ?><del><?= h(money_idr($product['price'])) ?></del><?php endif; ?></div>
                        </div>
                        <span class="product-card-more">Lihat detail <span aria-hidden="true">↗</span></span>
                        </a>
                    </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="editorial-section" id="tentang">
        <div class="container editorial-grid">
            <div class="editorial-art">
                <div class="editorial-image-main"><img src="https://images.unsplash.com/photo-1490312278390-ab64016e0aa9?auto=format&fit=crop&w=1000&q=85" alt="Sudut rumah yang nyaman dengan barang pilihan" loading="lazy"></div>
                <div class="editorial-sticker"><span>GOOD</span><span>THINGS</span><b>↗</b></div>
                <div class="editorial-caption">SMALL THINGS. BETTER DAYS.</div>
            </div>
            <div class="editorial-copy"><span class="eyebrow">SEDERHANA ITU BERARTI</span><h2>Belanja yang terasa <span>lebih dekat.</span></h2><p>Kami percaya barang yang tepat bisa membuat hari terasa lebih ringan. Karena itu, NusaMart dirancang untuk membantumu menemukan pilihan dengan cara yang jelas, nyaman, dan tidak ribet.</p>
                <div class="editorial-points"><div><span>01</span><div><strong>Mudah dijelajahi</strong><p>Temukan inspirasi dan produk dari kategori yang dekat dengan kebutuhanmu.</p></div></div><div><span>02</span><div><strong>Fokus pada yang penting</strong><p>Informasi produk dirancang ringkas supaya kamu lebih mudah membandingkan.</p></div></div><div><span>03</span><div><strong>Dibuat untuk keseharian</strong><p>Desain yang sederhana, ramah, dan nyaman di berbagai perangkat.</p></div></div></div>
                <a class="button button-primary button-large" href="#pilihan">Temukan favoritmu <span aria-hidden="true">↗</span></a>
            </div>
        </div>
    </section>

    <section class="closing-cta"><div class="container closing-cta-inner"><div><span class="eyebrow">MULAI DARI SINI</span><h2>Favorit baru kamu<br>mungkin ada di sini.</h2></div><div class="closing-cta-action"><p>Lihat-lihat dulu, temukan yang cocok, lalu simpan NusaMart sebagai teman belanjamu.</p><a href="#pilihan" class="button button-light button-large">Jelajahi pilihan <span aria-hidden="true">↗</span></a></div><span class="cta-decoration" aria-hidden="true">✳</span></div></section>
</main>
<?php require __DIR__ . '/includes/site-footer.php'; ?>
<div class="toast" id="toast" role="status" aria-live="polite"></div>
<script src="assets/js/app.js" defer></script>
</body>
</html>
