<?php

declare(strict_types=1);

namespace DigitalSac\MpContratos\Support;

/**
 * CSRF protection for admin/client forms.
 */
final class Csrf
{
    private const KEY = 'mpcontratos_csrf';

    public static function token(): string
    {
        self::ensureSession();
        if (empty($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(24));
        }
        return $_SESSION[self::KEY];
    }

    public static function verify(?string $token): bool
    {
        self::ensureSession();
        $expected = $_SESSION[self::KEY] ?? '';
        return is_string($token) && $expected !== '' && hash_equals((string) $expected, $token);
    }

    public static function field(): string
    {
        return '<input type="hidden" name="mp_csrf" value="'
            . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function assertValidPost(): void
    {
        if (!self::verify($_POST['mp_csrf'] ?? null)) {
            throw new \RuntimeException('Token CSRF inválido ou expirado.');
        }
    }

    private static function ensureSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
    }
}
