<?php
declare(strict_types=1);

function e(?string $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
function text_length(string $value): int {
    if (function_exists('mb_strlen')) return mb_strlen($value, 'UTF-8');
    $characters=preg_match_all('/./us', $value);
    return $characters === false ? strlen($value) : $characters;
}
function valid_date(string $value): ?DateTime {
    $date=DateTime::createFromFormat('!Y-m-d',$value);
    $issues=DateTime::getLastErrors();
    if (!$date || ($issues && ($issues['warning_count']>0 || $issues['error_count']>0)) || $date->format('Y-m-d')!==$value) return null;
    return $date;
}
function redirect(string $path): void {
    header('Location: ' . $path);
    exit;
}
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
function verify_csrf(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(419);
        exit('Invalid or expired security token. Go back and try again.');
    }
}
function is_logged_in(): bool {
    return isset($_SESSION['user']);
}
function can_manage_shop(): bool {
    return is_logged_in() && $_SESSION['user']['role'] === 'manager';
}
function require_login(): void {
    if (!is_logged_in()) redirect('login.php');
}
function require_manager(): void {
    require_login();
    if (!can_manage_shop()) {
        http_response_code(403);
        exit('Access denied. Manager account required.');
    }
}
function flash(string $message, string $type='success'): void {
    $_SESSION['flash'] = ['message'=>$message, 'type'=>$type];
}
function old(string $key, string $default=''): string {
    return e((string)($_POST[$key] ?? $default));
}
