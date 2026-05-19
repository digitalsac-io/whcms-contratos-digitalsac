<?php

declare(strict_types=1);

namespace DigitalSac\MpContratos\Support;

use DigitalSac\MpContratos\Database\Migrator;
use WHMCS\Database\Capsule;

/**
 * Writes audit events to mod_mpcontratos_logs and (optionally) to the
 * WHMCS module log via logModuleCall().
 */
final class Logger
{
    public static function event(
        string $event,
        ?int $contractId = null,
        array $payload = [],
        ?string $channel = null,
        ?int $adminId = null,
    ): void {
        try {
            Capsule::table(Migrator::TABLE_LOGS)->insert([
                'contract_id' => $contractId,
                'admin_id'    => $adminId,
                'event'       => substr($event, 0, 60),
                'channel'     => $channel ? substr($channel, 0, 30) : null,
                'payload'     => $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
                'ip_address'  => self::clientIp(),
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            self::moduleLog('logger.event', compact('event', 'contractId') + $payload, $e->getMessage());
        }
    }

    public static function moduleLog(string $action, array $request, string $response): void
    {
        if (function_exists('logModuleCall')) {
            logModuleCall(
                'mpcontratos',
                $action,
                $request,
                $response,
                '',
                ['mp_csrf', 'whatsapp_token', 'password'],
            );
        }
    }

    public static function clientIp(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $k) {
            if (!empty($_SERVER[$k])) {
                $ip = trim(explode(',', (string) $_SERVER[$k])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }
}
