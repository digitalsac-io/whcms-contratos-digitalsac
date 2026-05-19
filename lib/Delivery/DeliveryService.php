<?php

declare(strict_types=1);

namespace DigitalSac\MpContratos\Delivery;

use DigitalSac\MpContratos\Contract\ContractManager;
use DigitalSac\MpContratos\Contract\PdfGenerator;
use DigitalSac\MpContratos\Contract\PublicLinkService;
use DigitalSac\MpContratos\Delivery\WhatsApp\Message;
use DigitalSac\MpContratos\Delivery\WhatsApp\WhatsAppManager;
use DigitalSac\MpContratos\Support\Formatter;
use DigitalSac\MpContratos\Support\Logger;
use WHMCS\Database\Capsule;

/**
 * High-level façade for the admin "Send Contract" action. Dispatches to
 * EmailSender / WhatsAppManager and centralizes contract status updates.
 */
final class DeliveryService
{
    public function __construct(
        private readonly ContractManager $contracts = new ContractManager(),
        private readonly PdfGenerator $pdf = new PdfGenerator(),
        private readonly PublicLinkService $links = new PublicLinkService(),
        private readonly EmailSender $email = new EmailSender(),
        private readonly WhatsAppManager $whatsapp = new WhatsAppManager(),
    ) {}

    /**
     * @return array<string, array{ok:bool, info:string}>
     */
    public function deliver(int $contractId, array $channels = ['email']): array
    {
        $contract = $this->contracts->find($contractId);
        if (!$contract) {
            throw new \RuntimeException('Contrato não encontrado.');
        }

        $token = $this->links->create($contractId);
        $url   = $this->links->url($token);

        $results = [];

        if (in_array('email', $channels, true)) {
            try {
                $ok = $this->email->send($contractId, $url);
                $results['email'] = ['ok' => $ok, 'info' => $ok ? 'Enviado' : 'Falha no envio'];
                if ($ok) $this->contracts->markSent($contractId, 'email');
            } catch (\Throwable $e) {
                $results['email'] = ['ok' => false, 'info' => $e->getMessage()];
            }
        }

        if (in_array('whatsapp', $channels, true)) {
            if (!$this->whatsapp->isEnabled()) {
                $results['whatsapp'] = ['ok' => false, 'info' => 'Driver de WhatsApp desabilitado.'];
            } else {
                try {
                    $client = Capsule::table('tblclients')->where('id', $contract->client_id)->first();
                    if (!$client) {
                        throw new \RuntimeException('Cliente não encontrado.');
                    }
                    $pdfBytes = $this->pdf->render($contractId);
                    $tmp = sys_get_temp_dir() . '/' . $this->pdf->filename($contract);
                    file_put_contents($tmp, $pdfBytes);

                    $text = sprintf(
                        "Olá %s, seu contrato %s está disponível para assinatura.\n\nClique para revisar e assinar:\n%s",
                        trim(($client->firstname ?? '') . ' ' . ($client->lastname ?? '')),
                        $contract->number,
                        $url,
                    );
                    $message = new Message(
                        phoneE164:   Formatter::phoneE164((string) $client->phonenumber),
                        text:        $text,
                        pdfPath:     $tmp,
                        pdfFilename: $this->pdf->filename($contract),
                        caption:     "Contrato {$contract->number}",
                    );

                    $res = $this->whatsapp->send($message);
                    $results['whatsapp'] = [
                        'ok'   => $res->success,
                        'info' => $res->success ? ('Enviado (id: ' . ($res->providerId ?? '-') . ')') : ($res->error ?? 'Falha'),
                    ];
                    if ($res->success) {
                        $this->contracts->markSent($contractId, 'whatsapp');
                    }
                    @unlink($tmp);
                } catch (\Throwable $e) {
                    $results['whatsapp'] = ['ok' => false, 'info' => $e->getMessage()];
                    Logger::moduleLog('deliver.whatsapp', ['contract' => $contractId], $e->getMessage());
                }
            }
        }

        return $results;
    }
}
