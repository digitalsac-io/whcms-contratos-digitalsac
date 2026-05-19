<?php

declare(strict_types=1);

/**
 * MP Contratos — WHMCS hooks.
 *
 * - InvoiceCreated:           auto-gera contratos para produtos com auto_generate=true
 * - DailyCronJob:             expira contratos vencidos
 * - EmailPreSend:             anexa PDF do contrato quando o EmailSender solicitou
 * - ClientAreaPrimaryNavbar:  injeta link "Meus Contratos" + badge de pendentes
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

require_once __DIR__ . '/lib/Bootstrap.php';

use DigitalSac\MpContratos\Bootstrap;
use DigitalSac\MpContratos\Contract\ContractManager;
use DigitalSac\MpContratos\Database\Migrator;
use DigitalSac\MpContratos\Support\Logger;
use WHMCS\Database\Capsule;

Bootstrap::init();

// ---------------------------------------------------------------------------
// Auto-geração de contratos a partir de fatura nova
// ---------------------------------------------------------------------------
add_hook('InvoiceCreated', 1, function (array $vars): void {
    $invoiceId = (int) ($vars['invoiceid'] ?? 0);
    if ($invoiceId <= 0) return;
    try {
        $created = (new ContractManager())->autoGenerateForInvoice($invoiceId);
        if (!empty($created)) {
            Logger::moduleLog('hook.InvoiceCreated', ['invoice_id' => $invoiceId], 'created: ' . implode(',', $created));
        }
    } catch (\Throwable $e) {
        Logger::moduleLog('hook.InvoiceCreated', ['invoice_id' => $invoiceId], $e->getMessage());
    }
});

// ---------------------------------------------------------------------------
// Cron diário: expirar contratos vencidos
// ---------------------------------------------------------------------------
add_hook('DailyCronJob', 1, function (): void {
    try {
        $n = (new ContractManager())->expireOverdue();
        if ($n > 0) {
            Logger::moduleLog('hook.DailyCronJob', [], "expired: {$n}");
        }
    } catch (\Throwable $e) {
        Logger::moduleLog('hook.DailyCronJob', [], $e->getMessage());
    }
});

// ---------------------------------------------------------------------------
// Anexar PDF ao e-mail (acionado pelo EmailSender via $GLOBALS)
// ---------------------------------------------------------------------------
add_hook('EmailPreSend', 1, function (array $vars): array {
    $userId = (int) ($vars['relid'] ?? 0);
    if ($userId === 0) return [];
    $key = 'mpcontratos_pending_attachment_' . $userId;
    if (empty($GLOBALS[$key]) || !is_file($GLOBALS[$key])) return [];

    $path = $GLOBALS[$key];
    unset($GLOBALS[$key]); // one-shot

    return [
        'attachments' => [
            ['data' => file_get_contents($path), 'filename' => basename($path)],
        ],
    ];
});

// ---------------------------------------------------------------------------
// Item no menu primário da área do cliente
// ---------------------------------------------------------------------------
add_hook('ClientAreaPrimaryNavbar', 1, function ($primaryNavbar): void {
    if (empty($_SESSION['uid'])) return;
    $clientId = (int) $_SESSION['uid'];

    try {
        $pending = Capsule::table(Migrator::TABLE_CONTRACTS)
            ->where('client_id', $clientId)
            ->whereIn('status', [ContractManager::STATUS_PENDING, ContractManager::STATUS_SENT])
            ->count();
    } catch (\Throwable) {
        return;
    }

    $label = 'Meus Contratos';
    if ($pending > 0) {
        $label .= ' <span class="badge badge-warning" style="background:#f0ad4e;color:#fff;border-radius:10px;padding:1px 7px;font-size:11px;">' . $pending . '</span>';
    }

    if (!$primaryNavbar->getChild('Meus Contratos')) {
        $primaryNavbar->addChild('Meus Contratos', [
            'label' => $label,
            'uri'   => 'index.php?m=mpcontratos',
            'order' => 95,
            'icon'  => 'fa-file-contract',
        ]);
    }
});
