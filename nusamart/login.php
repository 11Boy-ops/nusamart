<?php
declare(strict_types=1);
require_once __DIR__ . '/config/init.php';

if (current_user()) {
    redirect('index.php');
}

$email = '';
$error = '';
$success = '';
if (isset($_GET['registered'])) {
    $success = 'Akun berhasil dibuat. Silakan masuk menggunakan email dan kata sandi kamu.';
}
if (isset($_GET['logged_out'])) {
    $success = 'Kamu berhasil keluar dari akun.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (!csrf_is_valid()) {
        $error = 'Sesi formulir sudah tidak berlaku. Muat ulang halaman lalu coba lagi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $error = 'Masukkan email dan kata sandi yang valid.';
    } else {
        try {
            $stmt = db()->prepare('SELECT id, name, email, password_hash, role, is_active FROM users WHERE email = :email LIMIT 1');
            $stmt->execute(['email' => str_lower_u($email)]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, (string) $user['password_hash'])) {
                $error = 'Email atau kata sandi belum tepat. Coba periksa lagi.';
            } elseif (!(bool) $user['is_active']) {
                $error = 'Akun ini sedang tidak aktif. Silakan hubungi bantuan.';
            } else {
                session_regenerate_id(true);
                $_SESSION['user'] = [
                    'id' => (int) $user['id'],
                    'name' => (string) $user['name'],
                    'email' => (string) $user['email'],
                    'role' => (string) $user['role'],
                ];
                unset($_SESSION['csrf_token']);
                $welcomeMessage = 'Selamat datang kembali, ' . (string) $user['name'] . '! Kamu berhasil masuk.';
                if (isset($_SESSION['pending_cart_add']) && is_array($_SESSION['pending_cart_add'])) {
                    $pending = $_SESSION['pending_cart_add'];
                    unset($_SESSION['pending_cart_add']);
                    try {
                        $productId = max(0, (int) ($pending['product_id'] ?? 0));
                        $quantity = max(1, min(99, (int) ($pending['quantity'] ?? 1)));
                        $pdo = db();
                        $productStmt = $pdo->prepare('SELECT id, name, stock, is_active FROM products WHERE id = :id LIMIT 1');
                        $productStmt->execute(['id' => $productId]);
                        $pendingProduct = $productStmt->fetch();
                        if ($pendingProduct && (bool) $pendingProduct['is_active'] && (int) $pendingProduct['stock'] >= 1) {
                            $cartId = get_or_create_cart_id($pdo, (int) $user['id']);
                            $existingStmt = $pdo->prepare('SELECT quantity FROM cart_items WHERE cart_id = :cart AND product_id = :product LIMIT 1');
                            $existingStmt->execute(['cart' => $cartId, 'product' => $productId]);
                            $existingQuantity = $existingStmt->fetchColumn();
                            $newQuantity = ($existingQuantity === false ? 0 : (int) $existingQuantity) + $quantity;
                            if ($newQuantity <= (int) $pendingProduct['stock']) {
                                $addStmt = $pdo->prepare('INSERT INTO cart_items (cart_id, product_id, quantity) VALUES (:cart, :product, :quantity) ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)');
                                $addStmt->execute(['cart' => $cartId, 'product' => $productId, 'quantity' => $quantity]);
                                $welcomeMessage .= ' ' . (string) $pendingProduct['name'] . ' sudah ditambahkan ke keranjang.';
                            } else {
                                $welcomeMessage .= ' Namun jumlah ' . (string) $pendingProduct['name'] . ' melebihi stok yang tersedia.';
                            }
                        } else {
                            $welcomeMessage .= ' Namun produk yang dipilih sudah tidak tersedia.';
                        }
                    } catch (Throwable $pendingError) {
                        $welcomeMessage .= ' Produk belum dapat ditambahkan ke keranjang; silakan coba dari halaman detail.';
                    }
                }
                flash_set('success', $welcomeMessage);
                $destination = (string) ($_SESSION['after_login'] ?? 'index.php');
                unset($_SESSION['after_login']);
                if (!preg_match('/^(index\.php|katalog\.php|keranjang\.php|checkout\.php|detail\.php\?id=[0-9]+)$/', $destination)) {
                    $destination = 'index.php';
                }
                redirect($destination);
            }
        } catch (Throwable $exception) {
            $error = 'Database belum terhubung. Pastikan MySQL aktif dan file database.sql sudah diimpor melalui phpMyAdmin.';
        }
    }
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f7f8f4">
    <title>Masuk ke NusaMart</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-page">
    <header class="auth-topbar"><div class="container auth-topbar-inner"><a class="brand" href="index.php" aria-label="NusaMart beranda"><span class="brand-mark" aria-hidden="true"><svg viewBox="0 0 40 40" fill="none"><path d="M8 14.5 20 7l12 7.5v13L20 35 8 27.5v-13Z" fill="currentColor" opacity=".14"/><path d="M8 14.5 20 22l12-7.5M20 22v13M8 14.5 20 7l12 7.5v13L20 35 8 27.5v-13Z" stroke="currentColor" stroke-width="2.4" stroke-linejoin="round"/><path d="m14.5 11 12 7.5" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg></span><span class="brand-word">nusa<span>mart.</span></span></a><a class="auth-back-link" href="index.php"><span aria-hidden="true">←</span> Kembali ke beranda</a></div></header>
    <main class="auth-main">
        <div class="auth-layout">
            <section class="auth-showcase">
                <img class="auth-showcase-image" src="https://images.unsplash.com/photo-1607082349566-187342175e2f?auto=format&fit=crop&w=1400&q=90" alt="Suasana berbelanja yang menyenangkan">
                <div class="auth-showcase-shade"></div>
                <div class="auth-showcase-top"><span class="showcase-badge"><span>✦</span> LITTLE THINGS, BIG JOY</span><span class="showcase-index">NM — 001</span></div>
                <div class="auth-showcase-copy"><span class="eyebrow eyebrow-light">SENANG BERTEMU LAGI</span><h1>Tempat favoritmu<br>dimulai <span>di sini.</span></h1><p>Masuk untuk melanjutkan perjalanan menemukan barang-barang yang cocok dengan keseharianmu.</p><div class="showcase-bottom"><span class="showcase-line"></span><span>YOUR EVERYDAY SHOPPING PARTNER</span></div></div>
                <div class="auth-showcase-deco" aria-hidden="true">✳</div>
            </section>
            <section class="auth-form-side">
                <div class="auth-form-wrap">
                    <div class="auth-form-heading"><span class="eyebrow">AKUN NUSAMART</span><h2>Selamat datang <span>kembali.</span></h2><p>Masukkan detail akunmu untuk lanjut belanja.</p></div>
                    <?php if ($success !== ''): ?><div class="form-alert form-alert-success" role="status"><span>✓</span><?= h($success) ?></div><?php endif; ?>
                    <?php if ($error !== ''): ?><div class="form-alert form-alert-error" role="alert"><span>!</span><?= h($error) ?></div><?php endif; ?>
                    <form class="auth-form" action="login.php" method="post" novalidate>
                        <?= csrf_field() ?>
                        <div class="form-field"><label for="email">Alamat email</label><div class="input-wrap"><span class="input-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></svg></span><input type="email" id="email" name="email" value="<?= h($email) ?>" placeholder="nama@email.com" autocomplete="email" required></div></div>
                        <div class="form-field"><div class="label-row"><label for="password">Kata sandi</label></div><div class="input-wrap"><span class="input-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"/></svg></span><input type="password" id="password" name="password" placeholder="Masukkan kata sandi" autocomplete="current-password" required><button class="password-toggle" type="button" data-toggle-password="password" aria-label="Tampilkan kata sandi"><svg viewBox="0 0 24 24"><path d="M2.5 12s3.4-6 9.5-6 9.5 6 9.5 6-3.4 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.7"/></svg></button></div></div>
                        <button class="button button-primary auth-submit" type="submit">Masuk ke akun <span aria-hidden="true">↗</span></button>
                    </form>
                    <div class="auth-divider"><span></span><small>BARU DI NUSAMART?</small><span></span></div>
                    <a class="button button-outline auth-create-account" href="register.php">Buat akun baru</a>
                    <div class="demo-account"><span class="demo-icon">✦</span><div><strong>Mau coba dulu?</strong><p>Akun demo tersedia untuk pengujian website.</p><button type="button" class="demo-fill" id="fill-demo">Isi akun demo otomatis <span aria-hidden="true">→</span></button></div></div>
                    <p class="auth-legal">Dengan masuk, kamu menyetujui pengalaman penggunaan NusaMart yang nyaman dan aman.</p>
                </div>
            </section>
        </div>
    </main>
    <div class="toast" id="toast" role="status" aria-live="polite"></div>
    <script src="assets/js/app.js" defer></script>
</body>
</html>
