<?php
declare(strict_types=1);
require_once __DIR__ . '/config/init.php';

if (current_user()) {
    redirect('index.php');
}

$name = '';
$email = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = str_lower_u(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if (!csrf_is_valid()) {
        $error = 'Sesi formulir sudah tidak berlaku. Muat ulang halaman lalu coba lagi.';
    } elseif (str_len_u($name) < 2 || str_len_u($name) > 120) {
        $error = 'Nama harus berisi 2–120 karakter.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || str_len_u($email) > 190) {
        $error = 'Masukkan alamat email yang valid.';
    } elseif (strlen($password) < 8) {
        $error = 'Kata sandi minimal terdiri dari 8 karakter.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Konfirmasi kata sandi belum sama.';
    } else {
        try {
            $pdo = db();
            $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, role, is_active) VALUES (:name, :email, :password_hash, :role, 1)');
            $stmt->execute([
                'name' => $name,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'role' => 'customer',
            ]);
            redirect('login.php?registered=1');
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                $error = 'Email ini sudah terdaftar. Coba masuk atau gunakan email lain.';
            } else {
                $error = 'Database belum terhubung. Pastikan MySQL aktif dan file database.sql sudah diimpor melalui phpMyAdmin.';
            }
        } catch (Throwable $exception) {
            $error = 'Terjadi kendala saat membuat akun. Silakan coba lagi.';
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
    <title>Buat Akun — NusaMart</title>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-page">
    <header class="auth-topbar"><div class="container auth-topbar-inner"><a class="brand" href="index.php" aria-label="NusaMart beranda"><span class="brand-mark" aria-hidden="true"><svg viewBox="0 0 40 40" fill="none"><path d="M8 14.5 20 7l12 7.5v13L20 35 8 27.5v-13Z" fill="currentColor" opacity=".14"/><path d="M8 14.5 20 22l12-7.5M20 22v13M8 14.5 20 7l12 7.5v13L20 35 8 27.5v-13Z" stroke="currentColor" stroke-width="2.4" stroke-linejoin="round"/><path d="m14.5 11 12 7.5" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg></span><span class="brand-word">nusa<span>mart.</span></span></a><a class="auth-back-link" href="index.php"><span aria-hidden="true">←</span> Kembali ke beranda</a></div></header>
    <main class="auth-main">
        <div class="auth-layout auth-layout-register">
            <section class="auth-showcase">
                <img class="auth-showcase-image" src="https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?auto=format&fit=crop&w=1400&q=90" alt="Pengalaman belanja online yang mudah">
                <div class="auth-showcase-shade"></div><div class="auth-showcase-top"><span class="showcase-badge"><span>✦</span> WELCOME TO THE CLUB</span><span class="showcase-index">NM — 002</span></div>
                <div class="auth-showcase-copy"><span class="eyebrow eyebrow-light">MULAI PERJALANANMU</span><h1>Temukan hal baik<br>yang <span>dekat.</span></h1><p>Buat akun gratis dan mulai jelajahi pilihan yang cocok dengan gaya hidupmu.</p><div class="showcase-bottom"><span class="showcase-line"></span><span>YOUR EVERYDAY SHOPPING PARTNER</span></div></div><div class="auth-showcase-deco" aria-hidden="true">✳</div>
            </section>
            <section class="auth-form-side"><div class="auth-form-wrap register-form-wrap">
                <div class="auth-form-heading"><span class="eyebrow">GABUNG BERSAMA KAMI</span><h2>Buat akun <span>baru.</span></h2><p>Isi beberapa detail sederhana untuk memulai.</p></div>
                <?php if ($error !== ''): ?><div class="form-alert form-alert-error" role="alert"><span>!</span><?= h($error) ?></div><?php endif; ?>
                <form class="auth-form" action="register.php" method="post" novalidate>
                    <?= csrf_field() ?>
                    <div class="form-field"><label for="name">Nama lengkap</label><div class="input-wrap"><span class="input-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="3.5"/><path d="M5 20c.6-3.4 3.1-5.2 7-5.2s6.4 1.8 7 5.2"/></svg></span><input type="text" id="name" name="name" value="<?= h($name) ?>" placeholder="Nama kamu" autocomplete="name" minlength="2" maxlength="120" required></div></div>
                    <div class="form-field"><label for="email">Alamat email</label><div class="input-wrap"><span class="input-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></svg></span><input type="email" id="email" name="email" value="<?= h($email) ?>" placeholder="nama@email.com" autocomplete="email" required></div></div>
                    <div class="form-field"><label for="password">Kata sandi <span class="label-help">Minimal 8 karakter</span></label><div class="input-wrap"><span class="input-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"/></svg></span><input type="password" id="password" name="password" placeholder="Buat kata sandi" autocomplete="new-password" minlength="8" required><button class="password-toggle" type="button" data-toggle-password="password" aria-label="Tampilkan kata sandi"><svg viewBox="0 0 24 24"><path d="M2.5 12s3.4-6 9.5-6 9.5 6 9.5 6-3.4 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.7"/></svg></button></div></div>
                    <div class="form-field"><label for="confirm_password">Ulangi kata sandi</label><div class="input-wrap"><span class="input-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/><path d="M21 12a9 9 0 1 1-2.7-6.4"/></svg></span><input type="password" id="confirm_password" name="confirm_password" placeholder="Ketik ulang kata sandi" autocomplete="new-password" required></div></div>
                    <button class="button button-primary auth-submit" type="submit">Buat akun <span aria-hidden="true">↗</span></button>
                </form>
                <div class="auth-existing">Sudah punya akun? <a href="login.php">Masuk di sini</a></div>
                <p class="auth-legal">Data akun disimpan pada database lokal yang terhubung melalui PHP dan MySQL.</p>
            </div></section>
        </div>
    </main>
    <div class="toast" id="toast" role="status" aria-live="polite"></div><script src="assets/js/app.js" defer></script>
</body>
</html>
