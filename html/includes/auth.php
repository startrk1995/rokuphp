<?php
/**
 * Authentication helpers for rokuphp
 * PHP 8.x compatible — replaces RPCL Zend auth
 */

function get_data_path(): string {
    return __DIR__ . '/../data/';
}

function get_config_path(): string {
    return __DIR__ . '/../config/';
}

function session_init(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 3600,
            'path'     => '/',
            'secure'   => false,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }
}

function is_logged_in(): bool {
    session_init();
    return isset($_SESSION['rokuphp_logged_in']) && $_SESSION['rokuphp_logged_in'] === true;
}

function require_auth(): void {
    if (!is_logged_in()) {
        header('Location: /index.php');
        exit;
    }
}

function get_session_password(): string {
    session_init();
    return $_SESSION['rokuphp_password'] ?? '';
}

function user_exists(): bool {
    return file_exists(get_data_path() . 'validuser.txt');
}

function create_user(string $username, string $password): bool {
    $path = get_data_path();
    if (!is_dir($path)) {
        mkdir($path, 0775, true);
        chown($path, 'www-data');
    }
    $hash = md5($username . ':rokuphp:' . $password);
    return (bool)file_put_contents($path . 'validuser.txt', $username . ':rokuphp:' . $hash);
}

function verify_credentials(string $username, string $password): bool {
    $file = get_data_path() . 'validuser.txt';
    if (!file_exists($file)) {
        return false;
    }
    $content = trim((string)file_get_contents($file));
    // Format: user:rokuphp:md5(user:rokuphp:pass)
    $parts = explode(':', $content, 3);
    if (count($parts) !== 3) {
        return false;
    }
    $expected = md5($username . ':rokuphp:' . $password);
    return hash_equals($parts[0], $username) && hash_equals($parts[2], $expected);
}

function login(string $username, string $password): bool {
    if (verify_credentials($username, $password)) {
        session_init();
        session_regenerate_id(true);
        $_SESSION['rokuphp_logged_in'] = true;
        $_SESSION['rokuphp_user']      = $username;
        $_SESSION['rokuphp_password']  = $password;
        return true;
    }
    return false;
}

function logout(): void {
    session_init();
    session_destroy();
}

/**
 * Encrypt a value using the session password (AES-128-ECB — same as original).
 */
function do_encrypt(string $val): string {
    if ($val === '') return '';
    $key = get_session_password();
    if ($key === '') return '';
    $encrypted = openssl_encrypt($val, 'AES-128-ECB', $key);
    return $encrypted !== false ? $encrypted : '';
}

/**
 * Decrypt a value using the session password.
 */
function do_decrypt(string $val): string {
    if ($val === '') return '';
    $key = get_session_password();
    if ($key === '') return '';
    $decrypted = openssl_decrypt($val, 'AES-128-ECB', $key);
    return $decrypted !== false ? $decrypted : '';
}
