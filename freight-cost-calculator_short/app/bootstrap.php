<?php
declare(strict_types=1);
require_once __DIR__ . '/calculator.php';
date_default_timezone_set('Europe/Kyiv');
if (PHP_SAPI !== 'cli') {
    session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax',
        'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'use_strict_mode' => true]);
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header("Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
    $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function db(): PDO
{
    static $pdo;
    if (!$pdo) {
        $dsn = 'mysql:host=' . (getenv('DB_HOST') ?: '127.0.0.1')
            . ';port=' . (getenv('DB_PORT') ?: '3306')
            . ';dbname=' . (getenv('DB_NAME') ?: 'freight_cost') . ';charset=utf8mb4';
        $pdo = new PDO($dsn, getenv('DB_USER') ?: 'freight', getenv('DB_PASSWORD') ?: '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec("SET time_zone = '+00:00'");
    }
    return $pdo;
}
function run(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}
function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function field(array $data, string $key): string
{
    $value = $data[$key] ?? '';
    if (!is_string($value)) throw new DomainException('Некоректний формат поля ' . $key);
    return trim($value);
}
function text_value(array $data, string $key, string $label, int $min, int $max): string
{
    $value = field($data, $key);
    $length = preg_match_all('/./us', $value);
    if ($length === false || $length < $min || $length > $max) {
        throw new DomainException("$label: від $min до $max символів.");
    }
    return $value;
}
function id_value(array $data, string $key = 'id'): int
{
    $value = field($data, $key);
    if (!preg_match('/^[1-9]\d{0,8}$/D', $value)) throw new DomainException('Оберіть коректний запис.');
    return (int)$value;
}
function csrf(): string
{
    return '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">';
}
function money(int|string $cents): string
{
    $cents = (int)$cents;
    return number_format(intdiv($cents, 100), 0, ',', ' ') . ',' . str_pad((string)($cents % 100), 2, '0', STR_PAD_LEFT);
}
function decimal_string(int|string $units, int $places): string
{
    $scale = 10 ** $places;
    return intdiv((int)$units, $scale) . '.' . str_pad((string)((int)$units % $scale), $places, '0', STR_PAD_LEFT);
}
function route_label(array $route): string
{
    return $route['origin'] . ' → ' . $route['destination'] . ' · ' . $route['via'];
}
function local_time(string $utc): string
{
    return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Europe/Kyiv'))->format('d.m.Y H:i');
}
