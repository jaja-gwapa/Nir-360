<?php
declare(strict_types=1);

final class Helpers
{
    public static function jsonResponse(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_SLASHES);
    }

    public static function csrfToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['nir360_csrf'])) {
            $_SESSION['nir360_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['nir360_csrf'];
    }

    public static function validateCsrf(string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return !empty($_SESSION['nir360_csrf']) && hash_equals($_SESSION['nir360_csrf'], $token);
    }

    /** Returns digits only (for storage and comparison). Use exactly 11 digits for Philippine numbers. */
    public static function normalizeMobile(string $mobile): string
    {
        $digits = preg_replace('/\D/', '', $mobile);
        return $digits;
    }
}
