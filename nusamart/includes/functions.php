<?php
declare(strict_types=1);

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}


function str_len_u(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function str_sub_u(string $value, int $start, ?int $length = null): string
{
    if (function_exists('mb_substr')) {
        return mb_substr($value, $start, $length, 'UTF-8');
    }
    return $length === null ? substr($value, $start) : substr($value, $start, $length);
}

function str_lower_u(string $value): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
}

function str_upper_u(string $value): string
{
    return function_exists('mb_strtoupper') ? mb_strtoupper($value, 'UTF-8') : strtoupper($value);
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function csrf_is_valid(): bool
{
    $posted = $_POST['csrf_token'] ?? '';
    return is_string($posted)
        && isset($_SESSION['csrf_token'])
        && hash_equals((string) $_SESSION['csrf_token'], $posted);
}

function current_user(): ?array
{
    return isset($_SESSION['user']) && is_array($_SESSION['user']) ? $_SESSION['user'] : null;
}

function money_idr(mixed $amount): string
{
    return 'Rp' . number_format((float) $amount, 0, ',', '.');
}

function flash_set(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function flash_get(): ?array
{
    if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function redirect(string $location): never
{
    header('Location: ' . $location);
    exit;
}


/** Jumlah unit produk di keranjang pengguna yang sedang masuk. */
function get_cart_count(): int
{
    $user = current_user();
    if (!$user) {
        return 0;
    }

    try {
        $stmt = db()->prepare(
            'SELECT COALESCE(SUM(ci.quantity), 0)
             FROM carts c
             LEFT JOIN cart_items ci ON ci.cart_id = c.id
             WHERE c.user_id = :user_id'
        );
        $stmt->execute(['user_id' => (int) $user['id']]);
        return (int) $stmt->fetchColumn();
    } catch (Throwable $exception) {
        return 0;
    }
}

/** Pastikan pengguna masuk; tujuan setelah login hanya diisi oleh route lokal. */
function require_login(string $afterLogin = 'keranjang.php'): void
{
    if (current_user()) {
        return;
    }

    $_SESSION['after_login'] = $afterLogin;
    flash_set('warning', 'Silakan masuk terlebih dahulu untuk melanjutkan.');
    redirect('login.php');
}

/** Ambil atau buat keranjang aktif untuk seorang pengguna. */
function get_or_create_cart_id(PDO $pdo, int $userId): int
{
    $stmt = $pdo->prepare('SELECT id FROM carts WHERE user_id = :user_id LIMIT 1');
    $stmt->execute(['user_id' => $userId]);
    $cartId = $stmt->fetchColumn();

    if ($cartId !== false) {
        return (int) $cartId;
    }

    $stmt = $pdo->prepare('INSERT INTO carts (user_id) VALUES (:user_id)');
    $stmt->execute(['user_id' => $userId]);
    return (int) $pdo->lastInsertId();
}

/** Harga aktif: sale_price hanya dipakai jika lebih kecil dari harga normal. */
function product_price(array $product): float
{
    if (isset($product['sale_price']) && $product['sale_price'] !== null
        && (float) $product['sale_price'] > 0
        && (float) $product['sale_price'] < (float) $product['price']) {
        return (float) $product['sale_price'];
    }

    return (float) $product['price'];
}
