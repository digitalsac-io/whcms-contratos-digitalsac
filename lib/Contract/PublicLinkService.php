<?php

declare(strict_types=1);

namespace DigitalSac\MpContratos\Contract;

use DigitalSac\MpContratos\Bootstrap;
use DigitalSac\MpContratos\Database\Migrator;
use DigitalSac\MpContratos\Support\Logger;
use WHMCS\Database\Capsule;

/**
 * Tokenized one-time/short-lived signature links so a client can sign
 * a contract sent via WhatsApp/email without logging into WHMCS.
 */
final class PublicLinkService
{
    public function create(int $contractId, ?\DateTimeInterface $expiresAt = null): string
    {
        $ttlHours = (int) (Bootstrap::setting('public_link_ttl', 72) ?: 72);
        $expires  = $expiresAt ?? (new \DateTimeImmutable("+{$ttlHours} hours"));
        $token    = bin2hex(random_bytes(24)); // 48 chars

        Capsule::table(Migrator::TABLE_PUBLIC_LINKS)->insert([
            'contract_id' => $contractId,
            'token'       => $token,
            'expires_at'  => $expires->format('Y-m-d H:i:s'),
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        Logger::event('link_created', $contractId, ['expires_at' => $expires->format('c')]);
        return $token;
    }

    public function url(string $token): string
    {
        $base = rtrim((string) \App::getSystemURL(), '/');
        return $base . '/modules/addons/mpcontratos/public/sign.php?t=' . urlencode($token);
    }

    /**
     * Resolve a token to its contract, validating expiry and consumption.
     * Returns the contract row or throws.
     */
    public function resolve(string $token): object
    {
        $link = Capsule::table(Migrator::TABLE_PUBLIC_LINKS)->where('token', $token)->first();
        if (!$link) {
            throw new \RuntimeException('Link inválido.');
        }
        if (!empty($link->used_at)) {
            throw new \RuntimeException('Este link já foi utilizado.');
        }
        if (strtotime($link->expires_at) < time()) {
            throw new \RuntimeException('Link expirado.');
        }
        $contract = Capsule::table(Migrator::TABLE_CONTRACTS)->where('id', $link->contract_id)->first();
        if (!$contract) {
            throw new \RuntimeException('Contrato não encontrado.');
        }
        return $contract;
    }

    public function consume(string $token, string $ip): void
    {
        Capsule::table(Migrator::TABLE_PUBLIC_LINKS)->where('token', $token)->update([
            'used_at'    => date('Y-m-d H:i:s'),
            'used_ip'    => $ip,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $row = Capsule::table(Migrator::TABLE_PUBLIC_LINKS)->where('token', $token)->first();
        if ($row) {
            Logger::event('link_used', (int) $row->contract_id, ['ip' => $ip]);
        }
    }
}
