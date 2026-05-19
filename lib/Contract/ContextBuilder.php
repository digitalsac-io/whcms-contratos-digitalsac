<?php

declare(strict_types=1);

namespace DigitalSac\MpContratos\Contract;

use DigitalSac\MpContratos\Bootstrap;
use DigitalSac\MpContratos\Support\Formatter;
use WHMCS\Database\Capsule;

/**
 * Builds the canonical context passed to TemplateParser:
 *
 *   [
 *     'cliente'    => [...],
 *     'contratada' => [...],
 *     'produto'    => [...],
 *     'servico'    => [...],
 *     'fatura'     => [...],
 *     'contrato'   => [...],
 *     'data'       => [...],
 *   ]
 */
final class ContextBuilder
{
    public static function build(
        int $clientId,
        int $contratadaId,
        ?int $serviceId = null,
        ?int $invoiceId = null,
        array $contractOverrides = [],
    ): array {
        $contratada = ContratadaRepository::find($contratadaId);
        if (!$contratada) {
            throw new \RuntimeException("Contratada #{$contratadaId} não encontrada.");
        }

        return [
            'cliente'    => self::clientContext($clientId),
            'contratada' => ContratadaRepository::toContext($contratada),
            'produto'    => $serviceId ? self::productContext($serviceId) : [],
            'servico'    => $serviceId ? self::serviceContext($serviceId) : [],
            'fatura'     => $invoiceId ? self::invoiceContext($invoiceId) : [],
            'contrato'   => self::contractContext($contractOverrides),
            'data'       => self::dateContext(),
        ];
    }

    private static function clientContext(int $clientId): array
    {
        $client = Capsule::table('tblclients')->where('id', $clientId)->first();
        if (!$client) {
            return [];
        }
        $arr = (array) $client;

        $cpfCnpj = self::resolveCpfCnpj($clientId);
        $empresa = self::resolveCompanyName($clientId, $arr);
        $mapped  = FieldMapRepository::valuesForClient($clientId);

        $base = [
            'id'                => (int) ($arr['id'] ?? 0),
            'nome'              => trim(($arr['firstname'] ?? '') . ' ' . ($arr['lastname'] ?? '')),
            'primeiro_nome'     => $arr['firstname'] ?? '',
            'sobrenome'         => $arr['lastname']  ?? '',
            'empresa'           => $empresa,
            'email'             => $arr['email']     ?? '',
            'telefone'          => Formatter::phone((string) ($arr['phonenumber'] ?? '')),
            'telefone_raw'      => Formatter::digits((string) ($arr['phonenumber'] ?? '')),
            'cpf_cnpj'          => Formatter::document($cpfCnpj),
            'cpf_cnpj_raw'      => Formatter::digits($cpfCnpj),
            'cpf'               => strlen(Formatter::digits($cpfCnpj)) === 11 ? Formatter::cpf($cpfCnpj) : '',
            'cnpj'              => strlen(Formatter::digits($cpfCnpj)) === 14 ? Formatter::cnpj($cpfCnpj) : '',
            'endereco'          => $arr['address1'] ?? '',
            'endereco2'         => $arr['address2'] ?? '',
            'cidade'            => $arr['city']     ?? '',
            'estado'            => $arr['state']    ?? '',
            'cep'               => Formatter::cep((string) ($arr['postcode'] ?? '')),
            'pais'              => $arr['country']  ?? '',
            'endereco_completo' => Formatter::fullAddress($arr),
        ];

        foreach ($mapped as $slug => $value) {
            if (!isset($base[$slug])) {
                $base[$slug] = $value;
            }
        }
        $base['custom'] = $mapped;

        return $base;
    }

    private static function productContext(int $serviceId): array
    {
        $svc = Capsule::table('tblhosting as h')
            ->leftJoin('tblproducts as p', 'p.id', '=', 'h.packageid')
            ->where('h.id', $serviceId)
            ->select('h.amount', 'h.billingcycle', 'p.id as product_id', 'p.name as product_name', 'p.description as product_description')
            ->first();
        if (!$svc) return [];
        $a = (array) $svc;
        return [
            'id'              => (int) ($a['product_id'] ?? 0),
            'nome'            => $a['product_name'] ?? '',
            'descricao'       => $a['product_description'] ?? '',
            'valor'           => (float) ($a['amount'] ?? 0),
            'valor_formatado' => Formatter::money((float) ($a['amount'] ?? 0)),
            'valor_extenso'   => Formatter::moneyExtenso((float) ($a['amount'] ?? 0)),
            'ciclo'           => Formatter::billingCycle((string) ($a['billingcycle'] ?? '')),
            'ciclo_raw'       => $a['billingcycle'] ?? '',
        ];
    }

    private static function serviceContext(int $serviceId): array
    {
        $svc = Capsule::table('tblhosting')->where('id', $serviceId)->first();
        if (!$svc) return [];
        $a = (array) $svc;
        return [
            'id'              => (int) $a['id'],
            'dominio'         => $a['domain'] ?? '',
            'usuario'         => $a['username'] ?? '',
            'valor'           => (float) ($a['amount'] ?? 0),
            'valor_formatado' => Formatter::money((float) ($a['amount'] ?? 0)),
            'ciclo'           => Formatter::billingCycle((string) ($a['billingcycle'] ?? '')),
            'data_contratacao'=> Formatter::date((string) ($a['regdate'] ?? '')),
            'proximo_venc'    => Formatter::date((string) ($a['nextduedate'] ?? '')),
            'status'          => $a['domainstatus'] ?? '',
        ];
    }

    private static function invoiceContext(int $invoiceId): array
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
            'total_extenso'  => Formatter::moneyExtenso((float) ($a['total'] ?? 0)),
            'vencimento'     => Formatter::date((string) ($a['duedate'] ?? '')),
            'data_emissao'   => Formatter::date((string) ($a['date'] ?? '')),
            'data_pagamento' => !empty($a['datepaid']) && !str_starts_with((string) $a['datepaid'], '0000-00-00')
                ? Formatter::date((string) $a['datepaid']) : '',
        ];
    }

    private static function contractContext(array $overrides): array
    {
        $base = [
            'numero' => '', 'data' => date('d/m/Y'),
            'data_extenso' => Formatter::dateExtenso(),
            'validade' => '', 'valor_total' => '', 'valor_total_extenso' => '',
        ];
        return array_merge($base, $overrides);
    }

    private static function dateContext(): array
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

    private static function resolveCpfCnpj(int $clientId): string
    {
        $configured = Bootstrap::setting('cpf_cnpj_field_id');
        if ($configured) {
            $row = Capsule::table('tblcustomfieldsvalues')
                ->where('fieldid', (int) $configured)
                ->where('relid', $clientId)
                ->first();
            if ($row && !empty($row->value)) return (string) $row->value;
        }
        $rows = Capsule::table('tblcustomfields as f')
            ->join('tblcustomfieldsvalues as v', 'v.fieldid', '=', 'f.id')
            ->where('f.type', 'client')->where('v.relid', $clientId)
            ->select('f.fieldname', 'v.value')->get();
        foreach ($rows as $r) {
            $name = mb_strtolower((string) $r->fieldname, 'UTF-8');
            if (preg_match('/cpf|cnpj|documento/u', $name) && !empty($r->value)) {
                return (string) $r->value;
            }
        }
        return '';
    }

    private static function resolveCompanyName(int $clientId, array $client): string
    {
        $source = (string) Bootstrap::setting('company_name_source', 'native');
        if ($source === 'custom_field') {
            $fid = (int) Bootstrap::setting('company_name_field_id', 0);
            if ($fid > 0) {
                $row = Capsule::table('tblcustomfieldsvalues')
                    ->where('fieldid', $fid)->where('relid', $clientId)->first();
                if ($row && !empty($row->value)) return (string) $row->value;
            }
        }
        return (string) ($client['companyname'] ?? '');
    }
}
