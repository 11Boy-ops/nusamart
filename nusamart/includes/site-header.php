<?php
$user = current_user();
$currentFlash = flash_get();
?>
<header class="site-header">
    <div class="topline">
        <div class="container topline-inner">
            <span><span class="topline-dot"></span> Banyak pilihan, belanja lebih tenang.</span>
            <span class="topline-right">Gratis ongkir untuk pesanan pertama <strong>•</strong> S&K berlaku</span>
        </div>
    </div>
    <div class="container header-main">
        <a class="brand" href="index.php" aria-label="NusaMart beranda">
            <span class="brand-mark" aria-hidden="true"><svg viewBox="0 0 40 40" fill="none"><path d="M8 14.5 20 7l12 7.5v13L20 35 8 27.5v-13Z" fill="currentColor" opacity=".14"/><path d="M8 14.5 20 22l12-7.5M20 22v13M8 14.5 20 7l12 7.5v13L20 35 8 27.5v-13Z" stroke="currentColor" stroke-width="2.4" stroke-linejoin="round"/><path d="m14.5 11 12 7.5" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg></span>
            <span class="brand-word">nusa<span>mart.</span></span>
        </a>

        <form class="header-search" action="katalog.php" method="get" role="search">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8"/><path d="m16 16 4.2 4.2"/></svg>
            <input type="search" name="q" value="<?= h($_GET['q'] ?? '') ?>" placeholder="Cari barang yang kamu suka..." aria-label="Cari produk">
            <button type="submit">Cari</button>
        </form>

        <div class="header-actions">
            <a class="header-cart" href="keranjang.php" aria-label="Keranjang belanja">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h2l2.1 11.2a2 2 0 0 0 2 1.6h7.8a2 2 0 0 0 2-1.6L20 8H6"/><circle cx="10" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg>
                <span>Keranjang</span><b class="cart-count"><?= get_cart_count() ?></b>
            </a>
            <?php if ($user): ?>
                <div class="user-greeting"><span class="user-avatar"><?= h(str_upper_u(str_sub_u((string) $user['name'], 0, 1))) ?></span><span>Hai, <strong><?= h(explode(' ', (string) $user['name'])[0]) ?></strong></span></div>
                <form action="logout.php" method="post" class="logout-form"><?= csrf_field() ?><button class="header-login" type="submit">Keluar</button></form>
            <?php else: ?>
                <a class="header-login" href="login.php"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="3.5"/><path d="M5 20c.6-3.4 3.1-5.2 7-5.2s6.4 1.8 7 5.2"/></svg><span>Masuk</span></a>
                <a class="button button-dark button-small header-register" href="register.php">Daftar <span aria-hidden="true">↗</span></a>
            <?php endif; ?>
        </div>
        <button class="mobile-menu-toggle" type="button" aria-label="Buka menu" aria-expanded="false" aria-controls="mobile-menu"><span></span><span></span></button>
    </div>
    <nav class="main-nav" aria-label="Navigasi utama">
        <div class="container nav-inner">
            <div class="nav-links">
                <a class="nav-link" href="index.php">Beranda</a>
                <a class="nav-link" href="katalog.php">Katalog</a>
                <a class="nav-link" href="index.php#kategori">Kategori</a>
                <a class="nav-link" href="katalog.php?sort=popular">Pilihan kami</a>
                <a class="nav-link" href="index.php#tentang">Kenapa NusaMart?</a>
            </div>
            <span class="nav-note"><span aria-hidden="true">✦</span> Temukan favorit barumu hari ini</span>
        </div>
        <div class="mobile-menu" id="mobile-menu" hidden>
            <a href="index.php">Beranda</a><a href="katalog.php">Katalog produk</a><a href="index.php#kategori">Kategori</a><a href="katalog.php?sort=popular">Pilihan kami</a><a href="keranjang.php">Keranjang (<?= get_cart_count() ?>)</a><a href="index.php#tentang">Kenapa NusaMart?</a>
            <?php if (!$user): ?><a href="login.php">Masuk ke akun</a><a href="register.php">Buat akun baru</a><?php endif; ?>
            <form class="mobile-search" action="katalog.php" method="get"><input name="q" type="search" placeholder="Cari produk..." aria-label="Cari produk"><button type="submit">Cari</button></form>
        </div>
    </nav>
</header>
<?php if ($currentFlash): ?>
    <div class="container flash-wrap"><div class="flash flash-<?= h($currentFlash['type']) ?>" role="status"><?= h($currentFlash['message']) ?></div></div>
<?php endif; ?>
