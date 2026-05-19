<?php

declare(strict_types=1);

namespace DigitalSac\MpContratos\Contract;

use DigitalSac\MpContratos\Database\Migrator;
use DigitalSac\MpContratos\Support\Formatter;
use WHMCS\Database\Capsule;

/**
 * Render a contract (and its signature, if any) into a PDF using Dompdf,
 * which is bundled with WHMCS for invoice generation.
 */
final class PdfGenerator
{
    public function __construct(
        private readonly ContractManager $contracts = new ContractManager(),
        private readonly SignatureService $signatures = new SignatureService(),
    ) {}

    /**
     * Render the contract to a binary PDF string.
     */
    public function render(int $contractId): string
    {
        $contract = $this->contracts->find($contractId);
        if (!$contract) {
            throw new \RuntimeException("Contrato {$contractId} não encontrado.");
        }

        $signature = $this->signatures->findForContract($contractId);
        $contratada = Capsule::table(Migrator::TABLE_CONTRATADAS)->where('id', $contract->contratada_id)->first();

        $html = $this->wrap($contract, $signature, $contratada);
        $useDigitalCert = $this->shouldUseDigitalCertificate($contratada);
        if ($useDigitalCert) {
            $signed = $this->renderWithTcpdf($html, $contratada, true);
            if ($signed !== null) {
                return $signed;
            }
            throw new \RuntimeException('A assinatura digital está habilitada para esta contratada, mas o servidor não possui TCPDF disponível.');
        }

        return $this->renderWithDompdf($html, $contratada);
    }

    public function filename(object $contract): string
    {
        $safe = preg_replace('/[^A-Za-z0-9_\-]/', '_', (string) $contract->number);
        return 'contrato-' . $safe . '.pdf';
    }

    private function wrap(object $contract, ?object $signature, ?object $contratada): string
    {
        $body = $contract->rendered_html;
        $number = htmlspecialchars((string) $contract->number, ENT_QUOTES, 'UTF-8');

        $sigBlock = $this->renderSignatureBlock($signature, $contratada);

        $css = '
            @page { margin: 22mm 18mm 26mm 18mm; }
            body { font-family: "DejaVu Sans", sans-serif; font-size: 11pt; color: #222; line-height: 1.45; }
            h1, h2, h3 { color: #111; }
            .mp-header { border-bottom: 1px solid #999; padding-bottom: 8px; margin-bottom: 16px; font-size: 9pt; color: #555; }
            .mp-footer { position: fixed; bottom: -18mm; left: 0; right: 0; text-align: center; font-size: 8pt; color: #888; border-top: 1px solid #ddd; padding-top: 4px; }
            .mp-signature { margin-top: 28pt; border-top: 1px dashed #999; padding-top: 14pt; }
            .mp-signature .meta { font-size: 9pt; color: #555; margin-top: 6pt; }
            .mp-signature img { max-height: 60pt; }
            table { width: 100%; border-collapse: collapse; }
            td, th { padding: 4pt 6pt; vertical-align: top; }
        ';

        $generated = Formatter::dateExtenso(date('Y-m-d'));
        return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="UTF-8"><title>Contrato {$number}</title>
<style>{$css}</style></head>
<body>
<div class="mp-header">Contrato Nº {$number} &middot; Gerado em {$generated}</div>
<div class="mp-body">{$body}</div>
{$sigBlock}
<div class="mp-footer">Contrato {$number} &middot; Documento eletrônico com validade jurídica conforme MP 2.200-2/2001</div>
</body></html>
HTML;
    }

    private function renderSignatureBlock(?object $signature, ?object $contratada): string
    {
        $signedAt = $signature && !empty($signature->signed_at)
            ? htmlspecialchars((string) (new \DateTimeImmutable($signature->signed_at))->format('d/m/Y H:i:s'), ENT_QUOTES, 'UTF-8')
            : null;
        $ip = $signature && !empty($signature->ip_address)
            ? htmlspecialchars((string) $signature->ip_address, ENT_QUOTES, 'UTF-8')
            : null;
        $hashShort = $signature && !empty($signature->payload_hash)
            ? substr((string) $signature->payload_hash, 0, 16)
            : null;
        $method = ($signature && ($signature->method ?? '') === 'canvas')
            ? 'Assinatura manuscrita digital'
            : 'Aceite eletrônico (clique)';

        $img = '';
        if ($signature && ($signature->method ?? '') === 'canvas' && !empty($signature->canvas_data)) {
            $img = '<img src="' . htmlspecialchars((string) $signature->canvas_data, ENT_QUOTES, 'UTF-8') . '" alt="Assinatura">';
        }

        $admin = '';
        if ($contratada && !empty($contratada->signature_path)) {
            $path = realpath(__DIR__ . '/../../' . ltrim($contratada->signature_path, '/'));
            if ($path && is_file($path)) {
                $data = base64_encode((string) file_get_contents($path));
                $admin = '<div style="float:right;text-align:center;width:45%;">'
                    . '<img src="data:image/png;base64,' . $data . '" style="max-height:60pt;"><br>'
                    . '<small>' . htmlspecialchars((string) ($contratada->signatory_name ?? ''), ENT_QUOTES, 'UTF-8')
                    . '<br>' . htmlspecialchars((string) ($contratada->signatory_role ?? ''), ENT_QUOTES, 'UTF-8')
                    . '<br>' . htmlspecialchars((string) ($contratada->razao_social ?? ''), ENT_QUOTES, 'UTF-8')
                    . '</small></div>';
            }
        } elseif ($contratada && !empty($contratada->signature_blob)) {
            $admin = '<div style="float:right;text-align:center;width:45%;">'
                . '<img src="data:image/png;base64,' . htmlspecialchars((string) $contratada->signature_blob, ENT_QUOTES, 'UTF-8') . '" style="max-height:60pt;"><br>'
                . '<small>' . htmlspecialchars((string) ($contratada->signatory_name ?? ''), ENT_QUOTES, 'UTF-8')
                . '<br>' . htmlspecialchars((string) ($contratada->signatory_role ?? ''), ENT_QUOTES, 'UTF-8')
                . '<br>' . htmlspecialchars((string) ($contratada->razao_social ?? ''), ENT_QUOTES, 'UTF-8')
                . '</small></div>';
        }

        $client = '<div style="float:left;text-align:center;width:45%;">'
            . $img
            . '<br><small>Assinatura do Contratante'
            . ($signature ? '<br>' . $method : '<br><em>Pendente de assinatura</em>')
            . '</small></div>';

        $meta = $signature
            ? 'Assinado em: <b>' . $signedAt . '</b> &middot; IP: <b>' . $ip . '</b> &middot; Hash de Integridade (SHA-256, primeiros 16 bytes): <code>' . $hashShort . '</code>'
            : 'Documento aguardando assinatura do contratante.';

        return '<div class="mp-signature">'
            . $client
            . $admin
            . '<div style="clear:both;"></div>'
            . '<div class="meta">'
            . $meta
            . ($this->shouldUseDigitalCertificate($contratada)
                ? '<br><b>Assinatura digital ICP-Brasil (A1) aplicada ao PDF da contratada.</b>'
                : '')
            . '</div></div>';
    }

    private function renderWithDompdf(string $html, ?object $contratada = null): string
    {
        // WHMCS environments vary: support both namespaced and legacy Dompdf.
        if (class_exists(\Dompdf\Dompdf::class)) {
            $dompdf = null;

            if (class_exists(\Dompdf\Options::class)) {
                $options = new \Dompdf\Options();
                $options->set('isHtml5ParserEnabled', true);
                $options->set('isRemoteEnabled', false);
                $options->set('defaultFont', 'DejaVu Sans');
                $dompdf = new \Dompdf\Dompdf($options);
            } else {
                $dompdf = new \Dompdf\Dompdf();
            }

            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            return (string) $dompdf->output();
        }

        if (class_exists(\DOMPDF::class)) {
            $dompdf = new \DOMPDF();
            if (method_exists($dompdf, 'set_option')) {
                $dompdf->set_option('isHtml5ParserEnabled', true);
                $dompdf->set_option('isRemoteEnabled', false);
                $dompdf->set_option('defaultFont', 'DejaVu Sans');
            }
            $dompdf->load_html($html, 'UTF-8');
            $dompdf->set_paper('A4', 'portrait');
            $dompdf->render();
            return (string) $dompdf->output();
        }

        $tcpdf = $this->renderWithTcpdf($html, $contratada, false);
        if ($tcpdf !== null) {
            return $tcpdf;
        }

        throw new \RuntimeException('Biblioteca de PDF não encontrada no WHMCS (Dompdf/TCPDF).');
    }

    private function renderWithTcpdf(string $html, ?object $contratada = null, bool $applyCertificate = false): ?string
    {
        if (!class_exists(\TCPDF::class) && !class_exists(\tecnickcom\tcpdf\TCPDF::class)) {
            $root = dirname(__DIR__, 5);
            $candidates = [
                $root . '/vendor/tecnickcom/tcpdf/tcpdf.php',
                $root . '/includes/tcpdf/tcpdf.php',
                $root . '/includes/classes/tcpdf/tcpdf.php',
            ];
            foreach ($candidates as $file) {
                if (is_file($file)) {
                    require_once $file;
                    break;
                }
            }
        }

        if (class_exists(\tecnickcom\tcpdf\TCPDF::class)) {
            $class = \tecnickcom\tcpdf\TCPDF::class;
        } elseif (class_exists(\TCPDF::class)) {
            $class = \TCPDF::class;
        } else {
            return null;
        }

        /** @var object $pdf */
        $tempPemPath = null;
        $pdf = new $class('P', 'mm', 'A4', true, 'UTF-8', false);
        if (method_exists($pdf, 'SetCreator')) {
            try {
                $pdf->SetCreator('DigitalSac Software Engineering');
                $pdf->SetAuthor('DigitalSac Software Engineering');
                $pdf->SetTitle('Contrato');
                $pdf->SetMargins(18, 18, 18);
                $pdf->SetAutoPageBreak(true, 20);
                $pdf->setPrintHeader(false);
                $pdf->setPrintFooter(false);
                $pdf->SetFont('dejavusans', '', 10);

                if ($applyCertificate) {
                    $cert = $this->prepareA1CertificatePem($contratada);
                    $tempPemPath = $cert['pem_path'];
                    if (method_exists($pdf, 'setSignature')) {
                        $pdf->setSignature(
                            'file://' . str_replace('\\', '/', $cert['pem_path']),
                            'file://' . str_replace('\\', '/', $cert['pem_path']),
                            '',
                            '',
                            2,
                            [
                                'Name' => (string) ($contratada->signatory_name ?? $contratada->razao_social ?? 'Contratada'),
                                'Location' => 'Brasil',
                                'Reason' => 'Assinatura digital da contratada',
                                'ContactInfo' => (string) ($contratada->email ?? ''),
                            ]
                        );
                        if (method_exists($pdf, 'setSignatureAppearance')) {
                            $pdf->setSignatureAppearance(132, 242, 58, 16);
                        }
                    }
                }

                $pdf->AddPage();
                $pdf->writeHTML($html, true, false, true, false, '');
                return (string) $pdf->Output('', 'S');
            } finally {
                if ($tempPemPath && is_file($tempPemPath)) {
                    @unlink($tempPemPath);
                }
            }
        }

        return null;
    }

    private function shouldUseDigitalCertificate(?object $contratada): bool
    {
        return $contratada
            && !empty($contratada->cert_enabled)
            && (!empty($contratada->cert_path) || !empty($contratada->cert_blob));
    }

    /**
     * Convert a .pfx/.p12 certificate to temporary PEM for TCPDF signing.
     *
     * @return array{pem_path:string}
     */
    private function prepareA1CertificatePem(?object $contratada): array
    {
        if (!$contratada || (empty($contratada->cert_path) && empty($contratada->cert_blob))) {
            throw new \RuntimeException('Certificado digital da contratada não configurado.');
        }
        if (!function_exists('openssl_pkcs12_read')) {
            throw new \RuntimeException('Extensão OpenSSL não disponível no servidor para ler certificado A1.');
        }

        $pkcs12 = '';
        if (!empty($contratada->cert_path)) {
            $path = realpath(__DIR__ . '/../../' . ltrim((string) $contratada->cert_path, '/'));
            if ($path && is_file($path)) {
                $pkcs12 = (string) file_get_contents($path);
            }
        }
        if ($pkcs12 === '' && !empty($contratada->cert_blob)) {
            $decoded = base64_decode((string) $contratada->cert_blob, true);
            if ($decoded !== false) {
                $pkcs12 = $decoded;
            }
        }
        if ($pkcs12 === '') {
            throw new \RuntimeException('Certificado digital não foi encontrado (arquivo/banco).');
        }
        $certs = [];
        $password = (string) ($contratada->cert_password ?? '');
        if (!openssl_pkcs12_read($pkcs12, $certs, $password)) {
            throw new \RuntimeException('Falha ao abrir certificado digital. Verifique a senha do certificado A1.');
        }
        if (empty($certs['cert']) || empty($certs['pkey'])) {
            throw new \RuntimeException('Certificado A1 inválido: conteúdo do certificado/chave privada ausente.');
        }

        $pemPath = tempnam(sys_get_temp_dir(), 'mpc_a1_');
        if ($pemPath === false) {
            throw new \RuntimeException('Não foi possível criar arquivo temporário para assinatura digital.');
        }
        $pem = trim((string) $certs['cert']) . PHP_EOL . trim((string) $certs['pkey']) . PHP_EOL;
        file_put_contents($pemPath, $pem);

        return ['pem_path' => $pemPath];
    }
}
