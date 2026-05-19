<?php

declare(strict_types=1);

namespace DigitalSac\MpContratos\Admin;

use DigitalSac\MpContratos\Bootstrap;
use DigitalSac\MpContratos\Contract\ContractManager;
use DigitalSac\MpContratos\Contract\ContratadaRepository;
use DigitalSac\MpContratos\Contract\PdfGenerator;
use DigitalSac\MpContratos\Contract\PublicLinkService;
use DigitalSac\MpContratos\Contract\SignatureService;
use DigitalSac\MpContratos\Contract\TemplateParser;
use DigitalSac\MpContratos\Database\Migrator;
use DigitalSac\MpContratos\Delivery\DeliveryService;
use DigitalSac\MpContratos\Support\Csrf;
use DigitalSac\MpContratos\Support\Formatter;
use DigitalSac\MpContratos\Support\Logger;
use WHMCS\Database\Capsule;

final class Controller
{
    public function __construct(
        private readonly array $vars,
        private readonly ContractManager $contracts = new ContractManager(),
        private readonly ContratadaRepository $contratadas = new ContratadaRepository(),
        private readonly DeliveryService $delivery = new DeliveryService(),
        private readonly PublicLinkService $links = new PublicLinkService(),
        private readonly SignatureService $signatures = new SignatureService(),
        private readonly PdfGenerator $pdf = new PdfGenerator(),
        private readonly TemplateParser $parser = new TemplateParser(),
    ) {}

    public function dispatch(): void
    {
        $action = $_GET['action'] ?? 'dashboard';

        try {
            match ($action) {
                'dashboard'           => $this->dashboard(),

                'contratadas'         => $this->contratadasList(),
                'contratada_edit'     => $this->contratadaEdit(),
                'contratada_save'     => $this->contratadaSave(),
                'contratada_cert_validate' => $this->contratadaCertValidate(),
                'contratada_delete'   => $this->contratadaDelete(),

                'templates'           => $this->templatesList(),
                'template_edit'       => $this->templateEdit(),
                'template_save'       => $this->templateSave(),
                'template_delete'     => $this->templateDelete(),

                'contracts'           => $this->contractsList(),
                'contract_new'        => $this->contractNew(),
                'contract_create'     => $this->contractCreate(),
                'contract_view'       => $this->contractView(),
                'contract_pdf'        => $this->contractPdf(),
                'contract_send'       => $this->contractSend(),
                'contract_cancel'     => $this->contractCancel(),
                'contract_delete'     => $this->contractDelete(),
                'contract_link'       => $this->contractLink(),
                'contract_reactivate' => $this->contractReactivate(),

                'products'            => $this->productMappings(),
                'product_save'        => $this->productSave(),

                'field_maps'          => $this->fieldMaps(),
                'field_map_save'      => $this->fieldMapSave(),
                'field_map_delete'    => $this->fieldMapDelete(),

                default => $this->dashboard(),
            };
        } catch (\Throwable $e) {
            Logger::moduleLog('admin.dispatch', ['action' => $action], $e->getMessage());
            echo $this->renderError($e->getMessage());
        }
    }

    public static function renderSidebar(array $vars): string
    {
        $base = self::moduleLink();
        $items = [
            ['action' => 'dashboard',    'label' => 'Dashboard'],
            ['action' => 'contracts',    'label' => 'Contratos'],
            ['action' => 'templates',    'label' => 'Templates'],
            ['action' => 'contratadas',  'label' => 'Contratadas'],
            ['action' => 'products',     'label' => 'Produtos x Templates'],
            ['action' => 'field_maps',   'label' => 'Mapeamento de Campos'],
        ];
        $current = $_GET['action'] ?? 'dashboard';
        $html = '<ul class="list-group">';
        foreach ($items as $it) {
            $active = $current === $it['action'] ? ' active' : '';
            $html .= sprintf(
                '<li class="list-group-item%s"><a href="%s&action=%s" class="%s">%s</a></li>',
                $active, htmlspecialchars($base, ENT_QUOTES, 'UTF-8'),
                $it['action'], $active ? 'text-white' : '',
                htmlspecialchars($it['label'], ENT_QUOTES, 'UTF-8'),
            );
        }
        $html .= '</ul>';
        return $html;
    }

    // ---------------------------------------------------------------------
    // Dashboard
    // ---------------------------------------------------------------------

    private function dashboard(): void
    {
        $stats = $this->contracts->stats();
        $recent = $this->contracts->search([], 10)['rows'];
        $hasContratadas = $this->contratadas->count() > 0;
        $this->view('dashboard', compact('stats', 'recent', 'hasContratadas'));
    }

    // ---------------------------------------------------------------------
    // Contratadas
    // ---------------------------------------------------------------------

    private function contratadasList(): void
    {
        $rows = $this->contratadas->all(false);
        $this->view('contratadas_list', compact('rows'));
    }

    private function contratadaEdit(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $row = $id ? $this->contratadas->find($id) : null;
        $this->view('contratada_edit', compact('row'));
    }

    private function contratadaSave(): void
    {
        Csrf::assertValidPost();
        $id = (int) ($_POST['id'] ?? 0);
        if ($this->exceededPostMaxSize()) {
            $this->redirect('contratada_edit', ['id' => $id, 'msg' => 'Upload excedeu o limite do servidor (post_max_size/upload_max_filesize). Reduza o arquivo ou ajuste o PHP.']);
            return;
        }
        $existing = $id > 0 ? $this->contratadas->find($id) : null;
        $data = [
            'razao_social'        => (string) ($_POST['razao_social'] ?? ''),
            'nome_fantasia'       => (string) ($_POST['nome_fantasia'] ?? ''),
            'cnpj'                => (string) ($_POST['cnpj'] ?? ''),
            'inscricao_estadual'  => (string) ($_POST['inscricao_estadual'] ?? ''),
            'inscricao_municipal' => (string) ($_POST['inscricao_municipal'] ?? ''),
            'endereco'            => (string) ($_POST['endereco'] ?? ''),
            'numero'              => (string) ($_POST['numero'] ?? ''),
            'complemento'         => (string) ($_POST['complemento'] ?? ''),
            'bairro'              => (string) ($_POST['bairro'] ?? ''),
            'cidade'               => (string) ($_POST['cidade'] ?? ''),
            'uf'                  => strtoupper((string) ($_POST['uf'] ?? '')),
            'cep'                 => (string) ($_POST['cep'] ?? ''),
            'email'               => (string) ($_POST['email'] ?? ''),
            'telefone'            => (string) ($_POST['telefone'] ?? ''),
            'signatory_name'      => (string) ($_POST['signatory_name'] ?? ''),
            'signatory_role'      => (string) ($_POST['signatory_role'] ?? ''),
            'cert_enabled'        => !empty($_POST['cert_enabled']),
            'is_default'          => !empty($_POST['is_default']),
            'active'              => !empty($_POST['active']),
        ];

        $certPassword = (string) ($_POST['cert_password'] ?? '');
        if ($certPassword !== '') {
            $data['cert_password'] = $certPassword;
        } elseif ($id > 0) {
            $current = $this->contratadas->find($id);
            if ($current && isset($current->cert_password)) {
                $data['cert_password'] = (string) $current->cert_password;
            }
        }

        // Handle signature from browser canvas (fallback to DB when disk is not writable)
        $canvasData = (string) ($_POST['signature_canvas_data'] ?? '');
        if ($canvasData !== '') {
            if (!preg_match('#^data:image/png;base64,[A-Za-z0-9+/=\s]+$#', $canvasData)) {
                $this->redirect('contratada_edit', ['id' => $id, 'msg' => 'Formato de assinatura inválido. Assine novamente no quadro.']);
                return;
            }
            $raw = base64_decode(substr($canvasData, strpos($canvasData, ',') + 1), true);
            if ($raw === false || $raw === '') {
                $this->redirect('contratada_edit', ['id' => $id, 'msg' => 'Falha ao processar assinatura desenhada.']);
                return;
            }
            $storage = Bootstrap::path('storage/signatures');
            $canWrite = is_dir($storage) || (@mkdir($storage, 0775, true) || is_dir($storage));
            $canWrite = $canWrite && is_writable($storage);
            if ($canWrite) {
                $name = 'sig_' . ($id ?: 'new') . '_' . time() . '_canvas.png';
                $dest = $storage . '/' . $name;
                if (@file_put_contents($dest, $raw) !== false) {
                    $data['signature_path'] = 'storage/signatures/' . $name;
                    $data['signature_blob'] = null;
                } else {
                    $data['signature_blob'] = base64_encode($raw);
                    $data['signature_path'] = null;
                }
            } else {
                $data['signature_blob'] = base64_encode($raw);
                $data['signature_path'] = null;
            }
        }

        // Handle signature upload (fallback to DB when disk write fails)
        if (!empty($_FILES['signature']['tmp_name']) && is_uploaded_file($_FILES['signature']['tmp_name'])) {
            $storage = Bootstrap::path('storage/signatures');
            if (!is_dir($storage)) @mkdir($storage, 0775, true);
            $name = 'sig_' . ($id ?: 'new') . '_' . time() . '.png';
            $dest = $storage . '/' . $name;
            $moved = move_uploaded_file($_FILES['signature']['tmp_name'], $dest);
            if (!$moved) {
                $moved = @copy((string) $_FILES['signature']['tmp_name'], $dest);
            }
            if ($moved) {
                $data['signature_path'] = 'storage/signatures/' . $name;
                $data['signature_blob'] = null;
            } else {
                $rawSig = (string) file_get_contents((string) $_FILES['signature']['tmp_name']);
                if ($rawSig !== '') {
                    $data['signature_blob'] = base64_encode($rawSig);
                    $data['signature_path'] = null;
                }
            }
        }

        // Handle A1 certificate upload (.pfx/.p12)
        $certFile = $_FILES['cert_file'] ?? null;
        if (is_array($certFile)) {
            $err = (int) ($certFile['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($err !== UPLOAD_ERR_NO_FILE && $err !== UPLOAD_ERR_OK) {
                $errMap = [
                    UPLOAD_ERR_INI_SIZE   => 'Arquivo maior que o limite permitido no servidor (upload_max_filesize).',
                    UPLOAD_ERR_FORM_SIZE  => 'Arquivo maior que o limite permitido no formulário.',
                    UPLOAD_ERR_PARTIAL    => 'Upload do certificado foi enviado parcialmente.',
                    UPLOAD_ERR_NO_TMP_DIR => 'Pasta temporária de upload ausente no servidor.',
                    UPLOAD_ERR_CANT_WRITE => 'Sem permissão de escrita no servidor para upload.',
                    UPLOAD_ERR_EXTENSION  => 'Upload bloqueado por extensão do PHP.',
                ];
                $detail = $errMap[$err] ?? ('Código de erro: ' . $err);
                $this->redirect('contratada_edit', ['id' => $id, 'msg' => 'Falha no upload do certificado digital. ' . $detail]);
                return;
            }
        }

        if (!empty($_FILES['cert_file']['tmp_name']) && is_uploaded_file($_FILES['cert_file']['tmp_name'])) {
            $ext = strtolower((string) pathinfo((string) ($_FILES['cert_file']['name'] ?? ''), PATHINFO_EXTENSION));
            if (!in_array($ext, ['pfx', 'p12'], true)) {
                $this->redirect('contratada_edit', ['id' => $id, 'msg' => 'Certificado inválido. Envie arquivo .pfx ou .p12']);
                return;
            }
            $storage = Bootstrap::path('storage/certificates');
            $relativeBase = 'storage/certificates';
            $diskWritable = true;
            if (!is_dir($storage) && !@mkdir($storage, 0775, true) && !is_dir($storage)) {
                // Fallback: many installations already grant write permission to signatures.
                $storage = Bootstrap::path('storage/signatures');
                $relativeBase = 'storage/signatures';
                if (!is_dir($storage) && !@mkdir($storage, 0775, true) && !is_dir($storage)) {
                    $diskWritable = false;
                }
            }
            if ($diskWritable && !is_writable($storage)) {
                $diskWritable = false;
            }
            $name = 'cert_' . ($id ?: 'new') . '_' . time() . '.' . $ext;
            $dest = $storage . '/' . $name;
            $tmp = (string) $_FILES['cert_file']['tmp_name'];
            $moved = false;
            if ($diskWritable) {
                $moved = move_uploaded_file($tmp, $dest);
                if (!$moved) {
                    // Fallback for hosts where move_uploaded_file is restricted.
                    $moved = @copy($tmp, $dest);
                }
            }
            if ($moved) {
                $data['cert_path'] = $relativeBase . '/' . $name;
                $data['cert_blob'] = null;
            } else {
                // Final fallback: store certificate in database.
                $raw = (string) file_get_contents($tmp);
                if ($raw === '') {
                    $lastErr = error_get_last();
                    $detail = $lastErr['message'] ?? 'sem detalhes do PHP';
                    $this->redirect('contratada_edit', [
                        'id' => $id,
                        'msg' => 'Falha ao salvar certificado digital. Detalhe: ' . $detail,
                    ]);
                    return;
                }
                $data['cert_blob'] = base64_encode($raw);
                $data['cert_path'] = null;
            }
        }

        $hasCertNow = !empty($data['cert_path']) || !empty($data['cert_blob']);
        $hasCertExisting = !empty($existing->cert_path) || !empty($existing->cert_blob);
        if (!empty($data['cert_enabled']) && !$hasCertNow && !$hasCertExisting) {
            $this->redirect('contratada_edit', ['id' => $id, 'msg' => 'Certificado digital está habilitado, mas nenhum arquivo .pfx/.p12 foi enviado/salvo.']);
            return;
        }

        if ($id > 0) {
            $this->contratadas->update($id, $data);
            $saved = $this->contratadas->find($id);
            $certStatus = (!empty($saved->cert_enabled) && !empty($saved->cert_path))
                ? 'Certificado A1: ativo.'
                : (!empty($saved->cert_path) ? 'Certificado A1: salvo (desativado).' : 'Certificado A1: não configurado.');
            $msg = 'Contratada atualizada. ' . $certStatus;
        } else {
            $id = $this->contratadas->create($data);
            $saved = $this->contratadas->find($id);
            $certStatus = (!empty($saved->cert_enabled) && !empty($saved->cert_path))
                ? 'Certificado A1: ativo.'
                : (!empty($saved->cert_path) ? 'Certificado A1: salvo (desativado).' : 'Certificado A1: não configurado.');
            $msg = 'Contratada cadastrada. ' . $certStatus;
        }
        $this->redirect('contratada_edit', ['id' => $id, 'saved' => 1, 'msg' => $msg]);
    }

    private function contratadaCertValidate(): void
    {
        Csrf::assertValidPost();
        $id = (int) ($_POST['id'] ?? 0);
        if ($this->exceededPostMaxSize()) {
            $this->redirect('contratada_edit', ['id' => $id, 'msg' => 'Upload excedeu o limite do servidor (post_max_size/upload_max_filesize).']);
            return;
        }

        if (!function_exists('openssl_pkcs12_read')) {
            $this->redirect('contratada_edit', ['id' => $id, 'msg' => 'OpenSSL indisponível no servidor. Não foi possível validar o certificado.']);
            return;
        }

        $certBinary = null;
        if (!empty($_FILES['cert_file']['tmp_name']) && is_uploaded_file($_FILES['cert_file']['tmp_name'])) {
            $ext = strtolower((string) pathinfo((string) ($_FILES['cert_file']['name'] ?? ''), PATHINFO_EXTENSION));
            if (!in_array($ext, ['pfx', 'p12'], true)) {
                $this->redirect('contratada_edit', ['id' => $id, 'msg' => 'Arquivo inválido para validação. Use .pfx ou .p12.']);
                return;
            }
            $certBinary = (string) file_get_contents((string) $_FILES['cert_file']['tmp_name']);
        } elseif ($id > 0) {
            $row = $this->contratadas->find($id);
            if ($row && !empty($row->cert_path)) {
                $path = realpath(Bootstrap::path((string) $row->cert_path));
                if ($path && is_file($path)) {
                    $certBinary = (string) file_get_contents($path);
                }
            }
            if (($certBinary === null || $certBinary === '') && $row && !empty($row->cert_blob)) {
                $decoded = base64_decode((string) $row->cert_blob, true);
                if ($decoded !== false) {
                    $certBinary = $decoded;
                }
            }
        }

        if ($certBinary === null || $certBinary === '') {
            $this->redirect('contratada_edit', ['id' => $id, 'msg' => 'Nenhum certificado encontrado para validar.']);
            return;
        }

        $password = (string) ($_POST['cert_password'] ?? '');
        if ($password === '' && $id > 0) {
            $row = $this->contratadas->find($id);
            if ($row && isset($row->cert_password)) {
                $password = (string) $row->cert_password;
            }
        }
        if ($password === '') {
            $this->redirect('contratada_edit', ['id' => $id, 'msg' => 'Informe a senha do certificado para validar.']);
            return;
        }

        $certs = [];
        $ok = openssl_pkcs12_read($certBinary, $certs, $password);
        if (!$ok || empty($certs['cert']) || empty($certs['pkey'])) {
            $this->redirect('contratada_edit', ['id' => $id, 'msg' => 'Falha na validação do certificado. Verifique arquivo e senha.']);
            return;
        }

        $parsed = openssl_x509_parse($certs['cert']) ?: [];
        $subject = (string) ($parsed['name'] ?? ($parsed['subject']['CN'] ?? 'Certificado válido'));
        $this->redirect('contratada_edit', ['id' => $id, 'msg' => 'Certificado válido: ' . $subject]);
    }

    private function contratadaDelete(): void
    {
        Csrf::assertValidPost();
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $count = Capsule::table(Migrator::TABLE_CONTRACTS)->where('contratada_id', $id)->count();
            if ($count > 0) {
                $this->redirect('contratadas', ['msg' => "Não é possível remover: existem {$count} contratos vinculados."]);
                return;
            }
            $this->contratadas->delete($id);
        }
        $this->redirect('contratadas', ['msg' => 'Contratada removida.']);
    }

    // ---------------------------------------------------------------------
    // Templates
    // ---------------------------------------------------------------------

    private function templatesList(): void
    {
        $rows = Capsule::table(Migrator::TABLE_TEMPLATES)->orderByDesc('id')->get()->all();
        $this->view('templates_list', compact('rows'));
    }

    private function templateEdit(): void
    {
        $id  = (int) ($_GET['id'] ?? 0);
        $row = $id ? Capsule::table(Migrator::TABLE_TEMPLATES)->where('id', $id)->first() : null;
        $this->view('template_edit', compact('row'));
    }

    private function templateSave(): void
    {
        Csrf::assertValidPost();
        $id    = (int) ($_POST['id'] ?? 0);
        $name  = trim((string) ($_POST['name'] ?? ''));
        $desc  = trim((string) ($_POST['description'] ?? ''));
        $body  = (string) ($_POST['body_html'] ?? '');
        if (str_contains($body, '&lt;') || str_contains($body, '&gt;')) {
            $decoded = html_entity_decode($body, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            // Only replace when decoded content looks like real HTML.
            if (str_contains($decoded, '<') && str_contains($decoded, '>')) {
                $body = $decoded;
            }
        }
        $active = !empty($_POST['active']);
        if ($name === '' || $body === '') {
            $this->redirect('templates', ['msg' => 'Nome e corpo são obrigatórios.']);
            return;
        }
        $now = date('Y-m-d H:i:s');
        if ($id > 0) {
            $current = Capsule::table(Migrator::TABLE_TEMPLATES)->where('id', $id)->first();
            $newVer = $current && $current->body_html !== $body
                ? ((int) $current->version + 1)
                : ($current ? (int) $current->version : 1);
            Capsule::table(Migrator::TABLE_TEMPLATES)->where('id', $id)->update([
                'name'        => $name,
                'description' => $desc,
                'body_html'   => $body,
                'version'     => $newVer,
                'active'      => $active ? 1 : 0,
                'updated_at'  => $now,
            ]);
        } else {
            $id = Capsule::table(Migrator::TABLE_TEMPLATES)->insertGetId([
                'name'        => $name,
                'description' => $desc,
                'body_html'   => $body,
                'version'     => 1,
                'active'      => $active ? 1 : 0,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }
        $this->redirect('templates', ['msg' => 'Template salvo.']);
    }

    private function templateDelete(): void
    {
        Csrf::assertValidPost();
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $count = Capsule::table(Migrator::TABLE_CONTRACTS)->where('template_id', $id)->count();
            if ($count > 0) {
                $this->redirect('templates', ['msg' => "Não é possível remover: {$count} contratos usam este template."]);
                return;
            }
            Capsule::table(Migrator::TABLE_TEMPLATES)->where('id', $id)->delete();
            Capsule::table(Migrator::TABLE_PRODUCT_TEMPLATES)->where('template_id', $id)->delete();
        }
        $this->redirect('templates', ['msg' => 'Template removido.']);
    }

    // ---------------------------------------------------------------------
    // Contracts
    // ---------------------------------------------------------------------

    private function contractsList(): void
    {
        $filters = [
            'status'      => $_GET['status']      ?? '',
            'template_id' => $_GET['template_id'] ?? '',
            'q'           => $_GET['q']           ?? '',
        ];
        $page    = max(1, (int) ($_GET['p'] ?? 1));
        $limit   = 25;
        $offset  = ($page - 1) * $limit;
        $result  = $this->contracts->search($filters, $limit, $offset);
        $templates = Capsule::table(Migrator::TABLE_TEMPLATES)->get()->all();
        $this->view('contracts_list', [
            'rows'      => $result['rows'],
            'total'     => $result['total'],
            'page'      => $page,
            'limit'     => $limit,
            'filters'   => $filters,
            'templates' => $templates,
        ]);
    }

    private function contractNew(): void
    {
        $templates    = Capsule::table(Migrator::TABLE_TEMPLATES)->where('active', 1)->get()->all();
        $contratadasL = $this->contratadas->all();
        $clients = Capsule::table('tblclients')
            ->select('id', 'firstname', 'lastname', 'companyname', 'email')
            ->orderBy('firstname')
            ->orderBy('lastname')
            ->limit(2000)
            ->get()
            ->all();
        $services = Capsule::table('tblhosting as h')
            ->leftJoin('tblproducts as p', 'p.id', '=', 'h.packageid')
            ->select('h.id', 'h.userid', 'h.domain', 'h.billingcycle', 'h.amount', 'h.domainstatus', 'p.name as product_name')
            ->orderByDesc('h.id')
            ->limit(5000)
            ->get()
            ->all();
        $this->view('contract_new', compact('templates', 'contratadasL', 'clients', 'services'));
    }

    private function contractCreate(): void
    {
        Csrf::assertValidPost();
        $clientId     = (int) ($_POST['client_id'] ?? 0);
        $templateId   = (int) ($_POST['template_id'] ?? 0);
        $serviceId    = (int) ($_POST['service_id'] ?? 0) ?: null;
        $contratadaId = (int) ($_POST['contratada_id'] ?? 0) ?: null;
        $validity     = (int) ($_POST['validity_days'] ?? 365);
        if ($clientId <= 0 || $templateId <= 0) {
            $this->redirect('contract_new', ['msg' => 'Cliente e template são obrigatórios.']);
            return;
        }
        $expires = new \DateTimeImmutable("+{$validity} days");
        $id = $this->contracts->generate($clientId, $templateId, $serviceId, null, $contratadaId, $expires);
        $this->redirect('contract_view', ['id' => $id, 'msg' => 'Contrato gerado.']);
    }

    private function contractView(): void
    {
        $id  = (int) ($_GET['id'] ?? 0);
        $row = $this->contracts->find($id);
        if (!$row) {
            $this->redirect('contracts', ['msg' => 'Contrato não encontrado.']);
            return;
        }
        $client     = Capsule::table('tblclients')->where('id', $row->client_id)->first();
        $signature  = $this->signatures->findForContract($id);
        $contratada = $row->contratada_id ? $this->contratadas->find((int) $row->contratada_id) : null;
        $integrityOk = $signature ? $this->signatures->verifyIntegrity($id) : null;
        $links = Capsule::table(Migrator::TABLE_PUBLIC_LINKS)
            ->where('contract_id', $id)->orderByDesc('id')->get()->all();
        $logs = Capsule::table(Migrator::TABLE_LOGS)
            ->where('contract_id', $id)->orderByDesc('id')->get()->all();
        $this->view('contract_view', compact('row', 'client', 'signature', 'contratada', 'integrityOk', 'links', 'logs'));
    }

    private function contractPdf(): void
    {
        $id  = (int) ($_GET['id'] ?? 0);
        $row = $this->contracts->find($id);
        if (!$row) {
            http_response_code(404);
            echo 'Contrato não encontrado.';
            return;
        }
        $bytes = $this->pdf->render($id);
        $name  = $this->pdf->filename($row);
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $name . '"');
        echo $bytes;
        exit;
    }

    private function contractSend(): void
    {
        Csrf::assertValidPost();
        $id       = (int) ($_POST['id'] ?? 0);
        $channels = $_POST['channels'] ?? ['email'];
        if (!is_array($channels)) $channels = [$channels];
        $results = $this->delivery->deliver($id, $channels);
        $msg = [];
        foreach ($results as $ch => $r) {
            $msg[] = "{$ch}: " . ($r['ok'] ? 'OK' : 'FALHOU') . ' — ' . $r['info'];
        }
        $this->redirect('contract_view', ['id' => $id, 'msg' => implode(' | ', $msg)]);
    }

    private function contractCancel(): void
    {
        Csrf::assertValidPost();
        $id = (int) ($_POST['id'] ?? 0);
        $reason = (string) ($_POST['reason'] ?? '');
        $this->contracts->cancel($id, null, $reason);
        $this->redirect('contract_view', ['id' => $id, 'msg' => 'Contrato cancelado.']);
    }

    private function contractDelete(): void
    {
        Csrf::assertValidPost();
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->redirect('contracts', ['msg' => 'Contrato inválido.']);
            return;
        }

        $deleted = $this->contracts->delete($id, null);
        if ($deleted) {
            $this->redirect('contracts', ['msg' => 'Contrato apagado com sucesso.']);
            return;
        }

        $this->redirect('contracts', ['msg' => 'Não foi possível apagar o contrato.']);
    }

    private function contractLink(): void
    {
        Csrf::assertValidPost();
        $id = (int) ($_POST['id'] ?? 0);
        $token = $this->links->create($id);
        $this->redirect('contract_view', ['id' => $id, 'msg' => 'Link público gerado: ' . $this->links->url($token)]);
    }

    private function contractReactivate(): void
    {
        Csrf::assertValidPost();
        $id     = (int) ($_POST['id'] ?? 0);
        $extend = (int) ($_POST['extend_days'] ?? 0);
        if ($id <= 0) {
            $this->redirect('contracts', ['msg' => 'Contrato inválido.']);
            return;
        }

        // Optional: extend contract validity so it doesn't immediately re-expire.
        if ($extend > 0) {
            Capsule::table(Migrator::TABLE_CONTRACTS)->where('id', $id)->update([
                'expires_at' => (new \DateTimeImmutable("+{$extend} days"))->format('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $ok = $this->contracts->reactivate($id, null, $extend > 0 ? "manual: +{$extend} dias" : 'manual');
        $this->redirect('contract_view', [
            'id'  => $id,
            'msg' => $ok ? 'Contrato reativado.' : 'Não foi possível reativar (somente contratos com status "expired").',
        ]);
    }

    // ---------------------------------------------------------------------
    // Product mappings
    // ---------------------------------------------------------------------

    private function productMappings(): void
    {
        $products = Capsule::table('tblproducts as p')
            ->leftJoin('tblproductgroups as g', 'g.id', '=', 'p.gid')
            ->select('p.id', 'p.name', 'g.name as group_name')
            ->orderBy('g.name')->orderBy('p.name')->get()->all();
        $mappings = Capsule::table(Migrator::TABLE_PRODUCT_TEMPLATES)->get()->keyBy('product_id')->all();
        $templates = Capsule::table(Migrator::TABLE_TEMPLATES)->where('active', 1)->get()->all();
        $contratadasL = $this->contratadas->all();
        $this->view('products', compact('products', 'mappings', 'templates', 'contratadasL'));
    }

    private function productSave(): void
    {
        Csrf::assertValidPost();
        $now = date('Y-m-d H:i:s');
        $payload = $_POST['mapping'] ?? [];
        if (!is_array($payload)) $payload = [];

        foreach ($payload as $productId => $data) {
            $productId   = (int) $productId;
            $templateId  = (int) ($data['template_id']  ?? 0);
            $contratadaId = (int) ($data['contratada_id'] ?? 0) ?: null;
            $auto        = !empty($data['auto_generate']) ? 1 : 0;

            if ($templateId <= 0) {
                Capsule::table(Migrator::TABLE_PRODUCT_TEMPLATES)->where('product_id', $productId)->delete();
                continue;
            }

            $existing = Capsule::table(Migrator::TABLE_PRODUCT_TEMPLATES)
                ->where('product_id', $productId)->first();
            if ($existing) {
                Capsule::table(Migrator::TABLE_PRODUCT_TEMPLATES)
                    ->where('product_id', $productId)
                    ->update([
                        'template_id'    => $templateId,
                        'contratada_id'  => $contratadaId,
                        'auto_generate'  => $auto,
                        'updated_at'     => $now,
                    ]);
            } else {
                Capsule::table(Migrator::TABLE_PRODUCT_TEMPLATES)->insert([
                    'product_id'    => $productId,
                    'template_id'   => $templateId,
                    'contratada_id' => $contratadaId,
                    'auto_generate' => $auto,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ]);
            }
        }
        $this->redirect('products', ['msg' => 'Mapeamentos salvos.']);
    }

    // ---------------------------------------------------------------------
    // Field mappings
    // ---------------------------------------------------------------------

    private function fieldMaps(): void
    {
        $rows = Capsule::table(Migrator::TABLE_FIELD_MAPS)->orderBy('variable_slug')->get()->all();
        $customFields = Capsule::table('tblcustomfields')->where('type', 'client')->get()->all();
        $this->view('field_maps', compact('rows', 'customFields'));
    }

    private function fieldMapSave(): void
    {
        Csrf::assertValidPost();
        $slug = preg_replace('/[^a-z0-9_]/', '', strtolower((string) ($_POST['variable_slug'] ?? '')));
        $fid  = (int) ($_POST['custom_field_id'] ?? 0);
        $lbl  = (string) ($_POST['label'] ?? '');
        if ($slug !== '' && $fid > 0) {
            $now = date('Y-m-d H:i:s');
            Capsule::table(Migrator::TABLE_FIELD_MAPS)->updateOrInsert(
                ['variable_slug' => $slug],
                ['custom_field_id' => $fid, 'label' => $lbl, 'active' => 1, 'created_at' => $now, 'updated_at' => $now],
            );
        }
        $this->redirect('field_maps', ['msg' => 'Mapeamento salvo.']);
    }

    private function fieldMapDelete(): void
    {
        Csrf::assertValidPost();
        $id = (int) ($_POST['id'] ?? 0);
        Capsule::table(Migrator::TABLE_FIELD_MAPS)->where('id', $id)->delete();
        $this->redirect('field_maps', ['msg' => 'Mapeamento removido.']);
    }

    // ---------------------------------------------------------------------
    // Rendering helpers
    // ---------------------------------------------------------------------

    private function view(string $name, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        $vars = $this->vars; // expose vars to view
        $base = self::moduleLink();
        $csrf = Csrf::field();
        $flash = isset($_GET['msg']) ? (string) $_GET['msg'] : '';
        require Bootstrap::path('lib/Admin/Views/' . $name . '.php');
    }

    private function renderError(string $message): string
    {
        return '<div class="alert alert-danger"><b>Erro:</b> ' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</div>';
    }

    private function redirect(string $action, array $extras = []): void
    {
        $params = array_merge(['action' => $action], $extras);
        $url = self::moduleLink() . '&' . http_build_query($params);
        header('Location: ' . $url);
        exit;
    }

    private function exceededPostMaxSize(): bool
    {
        $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($contentLength <= 0) {
            return false;
        }

        $postMax = $this->iniToBytes((string) ini_get('post_max_size'));
        if ($postMax > 0 && $contentLength > $postMax) {
            return true;
        }

        return false;
    }

    private function iniToBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }
        $num = (int) $value;
        $unit = strtolower(substr($value, -1));
        return match ($unit) {
            'g' => $num * 1024 * 1024 * 1024,
            'm' => $num * 1024 * 1024,
            'k' => $num * 1024,
            default => $num,
        };
    }

    public static function moduleLink(): string
    {
        return 'addonmodules.php?module=mpcontratos';
    }
}
