<?php

declare(strict_types=1);

namespace DigitalSac\MpContratos\Delivery;

use DigitalSac\MpContratos\Contract\PdfGenerator;
use DigitalSac\MpContratos\Contract\PublicLinkService;
use DigitalSac\MpContratos\Support\Logger;
use WHMCS\Database\Capsule;

/**
 * Sends contracts by email. Uses WHMCS's bundled mailer through the
 * internal SendEmail local API, with the PDF generated on the fly and
 * the public signature link included in the body. PDF attachment is
 * applied via the EmailPreSend hook (see hooks.php).
 */
final class EmailSender
{
    public function __construct(
        private readonly PdfGenerator $pdf = new PdfGenerator(),
        private readonly PublicLinkService $links = new PublicLinkService(),
    ) {}

    public function send(int $contractId, ?string $signatureUrl = null): bool
    {
        $contract = Capsule::table('mod_mpcontratos_contracts')->where('id', $contractId)->first();
        if (!$contract) {
            throw new \RuntimeException("Contrato {$contractId} não encontrado.");
        }
        $client = Capsule::table('tblclients')->where('id', $contract->client_id)->first();
        if (!$client) {
            throw new \RuntimeException('Cliente do contrato não encontrado.');
        }

        // Try to generate PDF attachment, but do not block e-mail delivery if
        // the server lacks PDF libraries (Dompdf/TCPDF).
        $tmpPath = null;
        $pdfUnavailableReason = null;
        try {
            $pdfBytes = $this->pdf->render($contractId);
            $tmpPath  = sys_get_temp_dir() . '/' . $this->pdf->filename($contract);
            file_put_contents($tmpPath, $pdfBytes);
        } catch (\Throwable $e) {
            $pdfUnavailableReason = $e->getMessage();
            Logger::moduleLog('email.pdf_unavailable', ['id' => $contractId], $pdfUnavailableReason);
        }

        if (!$signatureUrl) {
            $token = $this->links->create($contractId);
            $signatureUrl = $this->links->url($token);
        }

        $subject = "Contrato {$contract->number} disponível para assinatura";
        $body    = $this->bodyHtml($contract, $client, $signatureUrl, $pdfUnavailableReason !== null);

        // Signal hook to attach the PDF on next outbound mail to this client.
        if ($tmpPath !== null && is_file($tmpPath)) {
            $GLOBALS['mpcontratos_pending_attachment_' . $contract->client_id] = $tmpPath;
        }

        $payloadBase = [
            'id'            => (int) $contract->client_id,
            'customtype'    => 'general',
            'customsubject' => $subject,
            'custommessage' => $body,
            'customvars'    => base64_encode(serialize([
                'mp_contract_number' => $contract->number,
                'mp_contract_url'    => $signatureUrl,
            ])),
        ];

        $attempts = [
            $payloadBase + ['messagename' => 'DigitalSac Contratos - Envio'],
            $payloadBase + ['messagename' => 'MP Contratos - Envio'],
            $payloadBase + ['messagename' => 'General'],
            $payloadBase,
        ];

        $result = ['result' => 'error', 'message' => 'Nenhuma tentativa de envio executada.'];
        foreach ($attempts as $args) {
            $result = localAPI('SendEmail', $args);
            if (($result['result'] ?? '') === 'success') {
                break;
            }
        }

        $success = ($result['result'] ?? '') === 'success';
        if ($success) {
            Logger::event('sent_email', $contractId, ['to' => $client->email, 'url' => $signatureUrl], 'email');
        } else {
            Logger::moduleLog('email.send', ['id' => $contractId, 'to' => $client->email], json_encode($result, JSON_UNESCAPED_UNICODE));
        }
        return $success;
    }

    private function bodyHtml(object $contract, object $client, string $url, bool $withoutAttachment = false): string
    {
        $name = htmlspecialchars(($client->firstname ?? '') . ' ' . ($client->lastname ?? ''), ENT_QUOTES, 'UTF-8');
        $num  = htmlspecialchars($contract->number, ENT_QUOTES, 'UTF-8');
        $url  = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        $attachmentNote = $withoutAttachment
            ? '<p style="color:#b36b00;">Obs.: Não foi possível anexar o PDF automaticamente neste momento. Use o link acima para visualizar e assinar o contrato.</p>'
            : '<p>Em anexo segue uma cópia do contrato em PDF para sua leitura.</p>';
        return <<<HTML
<p>Olá <b>{$name}</b>,</p>
<p>Seu contrato <b>{$num}</b> está pronto para ser revisado e assinado eletronicamente.</p>
<p style="margin: 22px 0;">
  <a href="{$url}" style="display:inline-block;padding:12px 22px;background:#0c6dfd;color:#fff;text-decoration:none;border-radius:6px;font-weight:600;">
    Revisar e assinar contrato
  </a>
</p>
{$attachmentNote}
<p style="color:#666;font-size:12px;">Este link é pessoal e expira em algumas horas. Se já assinou, pode desconsiderar esta mensagem.</p>
HTML;
    }
}
