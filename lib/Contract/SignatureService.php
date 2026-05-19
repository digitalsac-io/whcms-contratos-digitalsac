<?php

declare(strict_types=1);

namespace DigitalSac\MpContratos\Contract;

use DigitalSac\MpContratos\Database\Migrator;
use DigitalSac\MpContratos\Support\Logger;
use WHMCS\Database\Capsule;

final class SignatureService
{
    public function __construct(
        private readonly ContractManager $contracts = new ContractManager(),
    ) {}

    /**
     * Persist a signature for a contract. The payload is hashed (SHA-256)
     * binding contract id + rendered html + method + ip + ua + timestamp,
     * giving us tamper detection over the signed material.
     *
     * @param 'canvas'|'checkbox' $method
     * @param string|null $canvasData base64 PNG (canvas method only)
     */
    public function sign(
        int $contractId,
        string $method,
        ?string $canvasData = null,
        ?string $userAgent = null,
        ?string $ip = null,
        array $extra = [],
    ): int {
        $contract = $this->contracts->find($contractId);
        if (!$contract) {
            throw new \RuntimeException('Contrato não encontrado.');
        }
        if (in_array($contract->status, [
            ContractManager::STATUS_SIGNED,
            ContractManager::STATUS_CANCELLED,
            ContractManager::STATUS_EXPIRED,
        ], true)) {
            throw new \RuntimeException("Contrato não pode ser assinado (status: {$contract->status}).");
        }

        $method = strtolower($method);
        if (!in_array($method, ['canvas', 'checkbox'], true)) {
            throw new \RuntimeException("Método de assinatura inválido: {$method}");
        }
        if ($method === 'canvas' && empty($canvasData)) {
            throw new \RuntimeException('Assinatura canvas exige imagem.');
        }

        // Sanitize canvas data — must be a data URI of a PNG
        if ($method === 'canvas') {
            if (!preg_match('#^data:image/png;base64,[A-Za-z0-9+/=\s]+$#', (string) $canvasData)) {
                throw new \RuntimeException('Formato de assinatura inválido (esperado data:image/png;base64).');
            }
        }

        $ip = $ip ?: Logger::clientIp();
        $userAgent = substr((string) ($userAgent ?? ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 500);
        $signedAt = date('Y-m-d H:i:s');

        $hash = $this->buildHash($contract, $method, $canvasData, $ip, $userAgent, $signedAt);

        $sigId = (int) Capsule::table(Migrator::TABLE_SIGNATURES)->insertGetId([
            'contract_id'  => $contractId,
            'method'       => $method,
            'canvas_data'  => $method === 'canvas' ? $canvasData : null,
            'payload_hash' => $hash,
            'ip_address'   => $ip,
            'user_agent'   => $userAgent,
            'extra'        => $extra ? json_encode($extra, JSON_UNESCAPED_UNICODE) : null,
            'signed_at'    => $signedAt,
        ]);

        $this->contracts->markSigned($contractId);
        Logger::event('signed', $contractId, [
            'method'       => $method,
            'signature_id' => $sigId,
            'payload_hash' => $hash,
        ]);
        return $sigId;
    }

    public function findForContract(int $contractId): ?object
    {
        return Capsule::table(Migrator::TABLE_SIGNATURES)
            ->where('contract_id', $contractId)
            ->orderByDesc('id')
            ->first() ?: null;
    }

    public function verifyIntegrity(int $contractId): bool
    {
        $sig = $this->findForContract($contractId);
        $contract = $this->contracts->find($contractId);
        if (!$sig || !$contract) return false;
        $expected = $this->buildHash(
            $contract,
            $sig->method,
            $sig->canvas_data,
            (string) $sig->ip_address,
            (string) $sig->user_agent,
            (string) $sig->signed_at,
        );
        return hash_equals($sig->payload_hash, $expected);
    }

    private function buildHash(
        object $contract,
        string $method,
        ?string $canvasData,
        string $ip,
        string $userAgent,
        string $signedAt,
    ): string {
        $payload = [
            'contract_id'   => (int) $contract->id,
            'number'        => (string) $contract->number,
            'rendered_html' => (string) $contract->rendered_html,
            'method'        => $method,
            'canvas_data'   => $canvasData,
            'ip'            => $ip,
            'ua'            => $userAgent,
            'signed_at'     => $signedAt,
        ];
        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
