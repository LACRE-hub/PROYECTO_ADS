<?php
/**
 * Configuración central — carga variables desde .env (nunca hardcodear secretos)
 * El archivo .env está en la raíz del proyecto y NO se sube a Git.
 */

$envFile = dirname(__DIR__) . '/.env';

if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}

// ── Base de datos ─────────────────────────────────────────────────
define('DB_HOST',    $_ENV['DB_HOST']    ?? 'localhost');
define('DB_NAME',    $_ENV['DB_NAME']    ?? 'sistema_hospitalario');
define('DB_USER',    $_ENV['DB_USER']    ?? 'root');
define('DB_PASS',    $_ENV['DB_PASS']    ?? '');
define('DB_CHARSET', 'utf8mb4');

// ── Google reCAPTCHA v2 ───────────────────────────────────────────
// Obtén tu clave secreta en: https://www.google.com/recaptcha/admin
define('RECAPTCHA_SECRET', $_ENV['RECAPTCHA_SECRET'] ?? '');
