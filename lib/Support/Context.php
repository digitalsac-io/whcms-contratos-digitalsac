<?php

declare(strict_types=1);

namespace DigitalSac\MpContratos\Support;

use DigitalSac\MpContratos\Bootstrap;
use DigitalSac\MpContratos\Contract\ContratadaRepository;
use DigitalSac\MpContratos\Database\Migrator;
use WHMCS\Database\Capsule;

/**
 * Builds the canonical context array consumed by TemplateParser.
 */
final class Context
{
    public function __construct(
        private readonly ContratadaRepository $contratadas = new ContratadaRepository(),
    ) {}

    public function build(
        int $clientId,
        ?int $contratadaId = null,
        ?int $serviceId = null,
        ?int $invoiceId = null,
        array $contractOverrides = [],
    ): array {
        return [
            'cliente'    => $this->clientContext($clientId),
            'servico'    => $serviceId ? $this->serviceContext($serviceId) : [],
            'fatura'     => $invoiceId ? $this->invoiceContext($invoiceId) : [],
            'contrato'   => $this->contractContext($contractOverrides),
            'contratada' => $this->contratadaContext($contratadaId, $serviceId),
            'data'       => $this->dateContext(),
            'custom'     => $this->customFields($clientId),
        ];
    }

    private function clientContext(int $clientId): array
    {
        $client = Capsule::table('tblclients')->where('id', $clientId)->first();
        if (!$client) {
            return [];
        }
        $arr = (array) $client;

        $cpfCnpj       = $this->resolveCpfCnpj($clientId);
        $cpfCnpjDigits = preg_replace('/\D/', '', $cpfCnpj);
        $fullName      = trim(($arr['firstname'] ?? '') . ' ' . ($arr['lastname'] ?? ''));
        $empresa       = $this->resolveCompanyName($clientId, $arr, $cpfCnpjDigits, $fullName);

        return [
            'id'                => (int) ($arr['id'] ?? 0),
            'nome'              => $fullName,
            'primeiro_nome'     => $arr['firstname'] ?? '',
            'sobrenome'         => $arr['lastname']  ?? '',
            'empresa'           => $empresa,
            'email'             => $arr['email']     ?? '',
            'telefone'          => Formatter::phone((string) ($arr['phonenumber'] ?? '')),
            'telefone_e164'     => Formatter::phoneE164((string) ($arr['phonenumber'] ?? '')),
            'cpf_cnpj'          => Formatter::document($cpfCnpj),
            'cpf_cnpj_raw'      => $cpfCnpjDigits,
            'cpf'               => strlen($cpfCnpjDigits) === 11 ? Formatter::cpf($cpfCnpj) : '',
            'cnpj'              => strlen($cpfCnpjDigits) === 14 ? Formatter::cnpj($cpfCnpj) : '',
            'tipo_pessoa'       => strlen($cpfCnpjDigits) === 14 ? 'PJ' : 'PF',
            'endereco'          => $arr['address1'] ?? '',
            'endereco2'         => $arr['address2'] ?? '',
            'cidade'            => $arr['city']     ?? '',
            'estado'            => $arr['state']    ?? '',
            'uf'                => strtoupper((string) ($arr['state'] ?? '')),
            'cep'               => Formatter::cep((string) ($arr['postcode'] ?? '')),
            'pais'              => $arr['country']  ?? '',
            'endereco_completo' => Formatter::fullAddress($arr),
        ];
    }

    private function serviceContext(int $serviceId): array
    {
        $svc = Capsule::table('tblhosting as h')
            ->leftJoin('tblproducts as p', 'p.id', '=', 'h.packageid')
            ->where('h.id', $serviceId)
            ->select(
                'h.id', 'h.domain', 'h.amount', 'h.billingcycle', 'h.nextduedate',
                'h.regdate', 'h.firstpaymentamount', 'h.packageid',
                'p.name as product_name', 'p.description as product_description',
            )
            ->first();
        if (!$svc) return [];
        $a = (array) $svc;
        $amount = (float) ($a['amount'] ?? 0);
        return [
            'id'               => (int) $a['id'],
            'product_id'       => (int) ($a['packageid'] ?? 0),
            'nome'             => $a['product_name'] ?? '',
            'descricao'        => strip_tags((string) ($a['product_description'] ?? '')),
            'dominio'          => $a['domain'] ?? '',
            'valor'            => $amount,
            'valor_formatado'  => Formatter::money($amount),
            'valor_extenso'    => Formatter::moneyExtenso($amount),
            'ciclo'            => Formatter::billingCycle((string) ($a['billingcycle'] ?? '')),
            'ciclo_raw'        => $a['billingcycle'] ?? '',
            'data_contratacao' => Formatter::date((string) ($a['regdate'] ?? '')),
            'proximo_venc'     => Formatter::date((string) ($a['nextduedate'] ?? '')),
        ];
    }

    private function invoiceContext(int $invoiceId): array
    {
        $inv = Capsule::table('tblinvoices')->where('id', $invoiceId)->first();
        if (!$inv) return [];
        $a = (array) $inv;
        return [
            'id'             => (int) $a['id'],
            'numero'         => (string) $a['id'],
            'status'         => $a['status'] ?? '',
            'subtotal'       => Formatter::money((float) ($a['subtotal'] ?? 0)),
            'total'          => Formatter::money((float) ($a['total'] ?? 0)),
            'total_raw'      => (float) ($a['total'] ?? 0),
            'vencimento'     => Formatter::date((string) ($a['duedate'] ?? '')),
            'data_emissao'   => Formatter::date((string) ($a['date'] ?? '')),
            'data_pagamento' => !empty($a['datepaid']) && $a['datepaid'] !== '0000-00-00 00:00:00'
                ? Formatter::date((string) $a['datepaid']) : '',
        ];
    }

    private function contractContext(array $overrides): array
    {
        return array_merge([
            'numero'              => '',
            'validade'            => '',
            'valor_total'         => '',
            'valor_total_extenso' => '',
            'data_geracao'        => Formatter::date(date('Y-m-d')),
            'data_geracao_extenso'=> Formatter::dateExtenso(),
        ], $overrides);
    }

    private function contratadaContext(?int $contratadaId, ?int $serviceId): array
    {
        $row = null;
        if ($contratadaId) {
            $row = $this->contratadas->find($contratadaId);
        }
        if (!$row && $serviceId) {
            $svc = Capsule::table('tblhosting')->where('id', $serviceId)->first();
            if ($svc && $svc->packageid) {
                $row = $this->contratadas->resolveForProduct((int) $svc->packageid);
            }
        }
        if (!$row) {
            $row = $this->contratadas->default();
        }
        if (!$row) return [];
        $a = (array) $row;
        $signaturePath = (string) ($a['signature_path'] ?? '');
        $signatureUrl = '';
        $signatureDataUri = '';

        if ($signaturePath !== '') {
            $signatureUrl = rtrim((string) \App::getSystemURL(), '/') . '/modules/addons/mpcontratos/' . ltrim($signaturePath, '/');
            $absPath = realpath(Bootstrap::path($signaturePath));
            if ($absPath && is_file($absPath)) {
                $signatureDataUri = 'data:image/png;base64,' . base64_encode((string) file_get_contents($absPath));
            }
        }
        if ($signatureDataUri === '' && !empty($a['signature_blob'])) {
            $signatureDataUri = 'data:image/png;base64,' . (string) $a['signature_blob'];
        }

        return [
            'id'                  => (int) $a['id'],
            'razao_social'        => $a['razao_social'] ?? '',
            'nome_fantasia'       => $a['nome_fantasia'] ?? '',
            'cnpj'                => Formatter::cnpj((string) ($a['cnpj'] ?? '')),
            'cnpj_raw'            => $a['cnpj'] ?? '',
            'inscricao_estadual'  => $a['inscricao_estadual'] ?? '',
            'inscricao_municipal' => $a['inscricao_municipal'] ?? '',
            'endereco'            => trim(($a['endereco'] ?? '')
                . (!empty($a['numero']) ? ', ' . $a['numero'] : '')
                . (!empty($a['complemento']) ? ' - ' . $a['complemento'] : '')),
            'bairro'              => $a['bairro'] ?? '',
            'cidade'              => $a['cidade'] ?? '',
            'uf'                  => $a['uf'] ?? '',
            'cep'                 => Formatter::cep((string) ($a['cep'] ?? '')),
            'pais'                => $a['pais'] ?? 'Brasil',
            'email'               => $a['email'] ?? '',
            'telefone'            => Formatter::phone((string) ($a['telefone'] ?? '')),
            'signatory_name'      => $a['signatory_name'] ?? '',
            'signatory_role'      => $a['signatory_role'] ?? '',
            'assinatura_path'     => $signaturePath,
            'assinatura_url'      => $signatureUrl,
            'assinatura_data_uri' => $signatureDataUri,
            'endereco_completo'   => trim(($a['endereco'] ?? '')
                . (!empty($a['numero']) ? ', ' . $a['numero'] : '')
                . (!empty($a['bairro']) ? ' - ' . $a['bairro'] : '')
                . (!empty($a['cidade']) ? ', ' . $a['cidade'] : '')
                . (!empty($a['uf']) ? '/' . $a['uf'] : '')
                . (!empty($a['cep']) ? ' - CEP ' . Formatter::cep($a['cep']) : '')),
        ];
    }

    private function dateContext(): array
    {
        $now = new \DateTimeImmutable();
        return [
            'hoje'         => $now->format('d/m/Y'),
            'hoje_iso'     => $now->format('Y-m-d'),
            'hoje_extenso' => Formatter::dateExtenso($now),
            'hora'         => $now->format('H:i'),
            'ano'          => $now->format('Y'),
            'mes'          => $now->format('m'),
            'dia'          => $now->format('d'),
        ];
    }

    private function customFields(int $clientId): array
    {
        $rows = Capsule::table('tblcustomfields as f')
            ->leftJoin('tblcustomfieldsvalues as v', function ($j) use ($clientId) {
                $j->on('v.fieldid', '=', 'f.id')->where('v.relid', '=', $clientId);
            })
            ->where('f.type', 'client')
            ->select('f.id', 'f.fieldname', 'v.value')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $key = $this->slug((string) $r->fieldname);
            $out[$key] = (string) ($r->value ?? '');
            $out['id_' . $r->id] = (string) ($r->value ?? '');
        }

        $maps = Capsule::table(Migrator::TABLE_FIELD_MAPS)->where('active', 1)->get();
        foreach ($maps as $map) {
            $val = Capsule::table('tblcustomfieldsvalues')
                ->where('fieldid', $map->custom_field_id)
                ->where('relid', $clientId)
                ->value('value');
            if ($val !== null) {
                $out[$this->slug($map->variable_slug)] = (string) $val;
            }
        }
        return $out;
    }

    private function resolveCpfCnpj(int $clientId): string
    {
        $configured = Bootstrap::setting('cpf_cnpj_field_id');
        if ($configured) {
            $val = Capsule::table('tblcustomfieldsvalues')
                ->where('fieldid', (int) $configured)->where('relid', $clientId)->value('value');
            if (!empty($val)) return (string) $val;
        }
        $candidates = ['cpf/cnpj', 'cpf_cnpj', 'cpfcnpj', 'cpf', 'cnpj', 'documento'];
        $rows = Capsule::table('tblcustomfields as f')
            ->join('tblcustomfieldsvalues as v', 'v.fieldid', '=', 'f.id')
            ->where('f.type', 'client')->where('v.relid', $clientId)
            ->select('f.fieldname', 'v.value')->get();
        foreach ($rows as $r) {
            $name = strtolower((string) $r->fieldname);
            foreach ($candidates as $c) {
                if (str_contains($name, $c) && !empty($r->value)) return (string) $r->value;
            }
        }
        return '';
    }

    private function resolveCompanyName(int $clientId, array $clientRow, string $cpfCnpjDigits, string $fullName): string
    {
        // PF: use full name; PJ: prefer company name.
        if (strlen($cpfCnpjDigits) === 11) {
            return $fullName;
        }

        $source = (string) Bootstrap::setting('company_name_source', 'native');
        if ($source === 'custom_field') {
            $fid = (int) Bootstrap::setting('company_name_field_id', 0);
            if ($fid > 0) {
                $val = Capsule::table('tblcustomfieldsvalues')
                    ->where('fieldid', $fid)->where('relid', $clientId)->value('value');
                if (!empty($val)) return (string) $val;
            }
        }
        $company = (string) ($clientRow['companyname'] ?? '');

        // For CNPJ/PJ (or any doc with >12 digits), prefer company name and
        // fallback to full name when company name is empty.
        if (strlen($cpfCnpjDigits) > 12) {
            return $company !== '' ? $company : $fullName;
        }

        // Unknown/empty document: keep previous behavior but avoid empty output.
        return $company !== '' ? $company : $fullName;
    }

    private function slug(string $name): string
    {
        $s = strtolower(trim($name));
        $s = preg_replace('/[áàâãä]/u', 'a', $s);
        $s = preg_replace('/[éèêë]/u',  'e', $s);
        $s = preg_replace('/[íìîï]/u',  'i', $s);
        $s = preg_replace('/[óòôõö]/u', 'o', $s);
        $s = preg_replace('/[úùûü]/u',  'u', $s);
        $s = preg_replace('/[ç]/u',     'c', $s);
        $s = preg_replace('/[^a-z0-9_]+/', '_', $s);
        return trim((string) $s, '_');
    }
}
