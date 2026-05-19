<?php
/**
 * Bootstrap — registers a PSR-4 autoloader for the addon namespace.
 *
 * Avoids a composer install requirement so the addon drops in cleanly.
 */

declare(strict_types=1);

namespace DigitalSac\MpContratos;

if (!class_exists(Bootstrap::class, false)) {
    class Bootstrap
    {
        private static bool $booted = false;

        public static function init(): void
        {
            if (self::$booted) {
                return;
            }
            self::$booted = true;

            spl_autoload_register(static function (string $class): void {
                $prefix = 'DigitalSac\\MpContratos\\';
                if (!str_starts_with($class, $prefix)) {
                    return;
                }
                $relative = substr($class, strlen($prefix));
                $path = __DIR__ . DIRECTORY_SEPARATOR
                    . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
                if (is_file($path)) {
                    require $path;
                }
            });
        }

        /**
         * Read a stored addon setting.
         */
        public static function setting(string $key, mixed $default = null): mixed
        {
            try {
                $row = \WHMCS\Database\Capsule::table('tbladdonmodules')
                    ->where('module', 'mpcontratos')
                    ->where('setting', $key)
                    ->first();
                return $row ? $row->value : $default;
            } catch (\Throwable) {
                return $default;
            }
        }

        /**
         * Absolute filesystem path to the addon root (parent of lib/).
         */
        public static function path(string $relative = ''): string
        {
            $root = dirname(__DIR__);
            return $root . ($relative ? DIRECTORY_SEPARATOR . ltrim($relative, '/\\') : '');
        }

        /**
         * Public URL base for assets (depends on WHMCS install location).
         */
        public static function assetUrl(string $relative = ''): string
        {
            $base = rtrim((string) \App::getSystemURL(), '/');
            return $base . '/modules/addons/mpcontratos/public/'
                . ltrim($relative, '/');
        }
    }
}
