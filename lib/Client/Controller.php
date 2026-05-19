<?php

declare(strict_types=1);

namespace DigitalSac\MpContratos\Client;

use DigitalSac\MpContratos\Bootstrap;
use DigitalSac\MpContratos\Contract\ContractManager;
use DigitalSac\MpContratos\Contract\ContratadaRepository;
use DigitalSac\MpContratos\Contract\PdfGenerator;
use DigitalSac\MpContratos\Contract\SignatureService;
use DigitalSac\MpContratos\Database\Migrator;
use DigitalSac\MpContratos\Support\Csrf;
use DigitalSac\MpContratos\Support\Logger;
use WHMCS\Database\Capsule;

/**
 * Client-area dispatcher. The addon's _clientarea callback delegates to
 * this controller; output is returned as a structured array consumed
 * directly by the active client-area template.
 */
final class Controller
{
    public function __construct(
        private readonly ContractManager $contracts = new ContractManager(),
        private readonly SignatureService $signatures = new SignatureService(),
        private readonly ContratadaRepository $contratadas = new ContratadaRepository(),
        private readonly PdfGenerator $pdf = new PdfGenerator(),
    ) {}

    public function dispatch(int $clientId): array
    {
        $action = $_REQUEST['action'] ?? 'list';

        return match ($action) {
            'view' => $this->view($clientId),
            'sign' => $this->sign($clientId),
            'pdf'  => $this->pdf($clientId),
            default => $this->list($clientId),
        };
    }

    private function list(int $clientId): array
    {
        $rows = $this->contracts->listForClient($clientId);
        $items = [];
        foreach ($rows as $row) {
            $status = (string) ($row->status ?? '');
            $items[] = [
                'id' => (int) ($row->id ?? 0),
                'number' => (string) ($row->number ?? ''),
                'status' => $status,
                'status_label' => match ($status) {
                    ContractManager::STATUS_PENDING => 'Pendente',
                    ContractManager::STATUS_SENT => 'Enviado',
                    ContractManager::STATUS_SIGNED => 'Assinado',
                    ContractManager::STATUS_EXPIRED => 'Expirado',
                    ContractManager::STATUS_CANCELLED => 'Cancelado',
                    default => $status,
                },
                'created_at_display' => $this->formatDateTime((string) ($row->created_at ?? ''), false),
                'expires_at_display' => $this->formatDateTime((string) ($row->expires_at ?? ''), false),
                'signed_at_display' => $this->formatDateTime((string) ($row->signed_at ?? ''), true),
            ];
        }

        return [
            'pagetitle' => 'Meus Contratos',
            'breadcrumb' => ['index.php?m=mpcontratos' => 'Meus Contratos'],
            'templatefile' => 'client_contracts_list',
            'requirelogin' => true,
            'vars' => ['rows' => $items, 'rowsTotal' => count($items), 'base' => 'index.php?m=mpcontratos'],
        ];
    }

    private function view(int $clientId): array
    {
        $id = (int) ($_GET['id'] ?? 0);
        $contract = $this->contracts->find($id);
        if (!$contract || (int) $contract->client_id !== $clientId) {
            return [
                'pagetitle' => 'Contrato não encontrado',
                'templatefile' => 'client_contracts_list',
                'requirelogin' => true,
                'vars' => ['rows' => [], 'base' => 'index.php?m=mpcontratos', 'error' => 'Contrato não encontrado.'],
            ];
        }

        $signature  = $this->signatures->findForContract($id);
        $contratada = $contract->contratada_id ? $this->contratadas->find((int) $contract->contratada_id) : null;
        $methods = array_values(array_filter(array_map('trim', explode(',', (string) Bootstrap::setting('signature_methods', 'canvas,checkbox')))));

        return [
            'pagetitle' => 'Contrato ' . $contract->number,
            'breadcrumb' => [
                'index.php?m=mpcontratos' => 'Meus Contratos',
                'index.php?m=mpcontratos&action=view&id=' . $id => $contract->number,
            ],
            'templatefile' => 'client_contract_sign',
            'requirelogin' => true,
            'vars' => [
                'allowCanvas' => in_array('canvas', $methods, true),
                'allowCheckbox' => in_array('checkbox', $methods, true),
                'contract'    => $contract,
                'contratada'  => $contratada,
                'signature'   => $signature,
                'canSign'     => in_array($contract->status, [ContractManager::STATUS_PENDING, ContractManager::STATUS_SENT], true),
                'csrf'        => Csrf::token(),
                'base'        => 'index.php?m=mpcontratos',
                'methods'     => $methods,
            ],
        ];
    }

    private function sign(int $clientId): array
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->view($clientId);
        }
        $id = (int) ($_POST['id'] ?? 0);
        $contract = $this->contracts->find($id);
        if (!$contract || (int) $contract->client_id !== $clientId) {
            header('Location: index.php?m=mpcontratos');
            exit;
        }
        try {
            Csrf::assertValidPost();
            $method = (string) ($_POST['method'] ?? '');
            $canvas = $method === 'canvas' ? (string) ($_POST['canvas_data'] ?? '') : null;
            $this->signatures->sign($id, $method, $canvas, $_SERVER['HTTP_USER_AGENT'] ?? '');
            header('Location: index.php?m=mpcontratos&action=view&id=' . $id . '&signed=1');
            exit;
        } catch (\Throwable $e) {
            Logger::moduleLog('client.sign', ['id' => $id], $e->getMessage());
            header('Location: index.php?m=mpcontratos&action=view&id=' . $id . '&error=' . urlencode($e->getMessage()));
            exit;
        }
    }

    private function pdf(int $clientId): array
    {
        $id = (int) ($_GET['id'] ?? 0);
        $contract = $this->contracts->find($id);
        if (!$contract || (int) $contract->client_id !== $clientId) {
            http_response_code(404);
            echo 'Contrato não encontrado.';
            exit;
        }
        $bytes = $this->pdf->render($id);
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $this->pdf->filename($contract) . '"');
        echo $bytes;
        exit;
    }

    private function formatDateTime(string $value, bool $withTime): string
    {
        if ($value === '' || $value === '0000-00-00 00:00:00') {
            return '—';
        }
        try {
            $dt = new \DateTimeImmutable($value);
            return $dt->format($withTime ? 'd/m/Y H:i' : 'd/m/Y');
        } catch (\Throwable) {
            return $value;
        }
    }
}
