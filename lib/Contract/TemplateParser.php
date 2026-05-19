<?php

declare(strict_types=1);

namespace DigitalSac\MpContratos\Contract;

/**
 * Renders a contract template by substituting placeholders against a context.
 *
 * Supported syntaxes:
 *   Modern : {{escopo.chave}}        e.g. {{cliente.nome}}, {{custom.rg}}
 *   Filter : {{produto.valor|upper}} pipes: upper, lower, escape
 *   Legacy : %NOME% / %CPFCNPJ% etc  (compat com Modules Pay)
 *   Bracket: [EMPRESA] / [CNPJ_EMPRESA] / [ENDERECO_EMPRESA]
 *   Custom : {{custom.<slug>}}       qualquer custom field do cliente
 */
final class TemplateParser
{
    /** Legacy %TOKEN% → dotted path. */
    private const LEGACY_PERCENT = [
        'NOME'      => 'cliente.nome',
        'EMPRESA'   => 'cliente.empresa',
        'CPFCNPJ'   => 'cliente.cpf_cnpj',
        'CPF'       => 'cliente.cpf',
        'CNPJ'      => 'cliente.cnpj',
        'EMAIL'     => 'cliente.email',
        'TELEFONE'  => 'cliente.telefone',
        'ENDERECO'  => 'cliente.endereco_completo',
        'CIDADE'    => 'cliente.cidade',
        'UF'        => 'cliente.uf',
        'ESTADO'    => 'cliente.estado',
        'CEP'       => 'cliente.cep',
        'SERVICO'   => 'servico.nome',
        'DOMINIO'   => 'servico.dominio',
        'VALOR'     => 'servico.valor_formatado',
        'CICLO'     => 'servico.ciclo',
        'FATURA'    => 'fatura.numero',
        'DATA'      => 'data.hoje',
        'NUMERO'    => 'contrato.numero',
        'VALIDADE'  => 'contrato.validade',
    ];

    /** Legacy [TOKEN] → dotted path (contratada). */
    private const LEGACY_BRACKET = [
        'EMPRESA'           => 'contratada.razao_social',
        'CNPJ_EMPRESA'      => 'contratada.cnpj',
        'ENDERECO_EMPRESA'  => 'contratada.endereco_completo',
        'EMAIL_EMPRESA'     => 'contratada.email',
        'TELEFONE_EMPRESA'  => 'contratada.telefone',
        'SIGNATARIO'        => 'contratada.signatory_name',
        'CARGO_SIGNATARIO'  => 'contratada.signatory_role',
    ];

    public function render(string $template, array $context): string
    {
        $out = $template;

        // 1) Modern {{escopo.chave|filter1|filter2}}
        $out = preg_replace_callback(
            '/\{\{\s*([a-z0-9_]+(?:\.[a-z0-9_]+)*)\s*((?:\|[a-z_]+)*)\s*\}\}/iu',
            function (array $m) use ($context): string {
                $value  = $this->resolve($m[1], $context);
                $filters = $m[2] !== '' ? array_filter(explode('|', trim($m[2], '|'))) : [];
                foreach ($filters as $f) {
                    $value = $this->applyFilter($f, $value);
                }
                return $value;
            },
            $out,
        );

        // 2) Legacy %TOKEN%
        $out = preg_replace_callback(
            '/%([A-Z][A-Z0-9_]+)%/u',
            function (array $m) use ($context): string {
                $path = self::LEGACY_PERCENT[$m[1]] ?? null;
                return $path ? $this->resolve($path, $context) : $m[0];
            },
            $out,
        );

        // 3) Legacy [TOKEN]
        $out = preg_replace_callback(
            '/\[([A-Z][A-Z0-9_]+)\]/u',
            function (array $m) use ($context): string {
                $path = self::LEGACY_BRACKET[$m[1]] ?? null;
                return $path ? $this->resolve($path, $context) : $m[0];
            },
            $out,
        );

        return $out;
    }

    /**
     * Pre-render a template body with a synthetic context for admin preview.
     * Provides dummy values where the real context is empty.
     */
    public function preview(string $template, array $context): string
    {
        $mock = [
            'cliente'    => ['nome' => 'Fulano de Tal', 'cpf_cnpj' => '123.456.789-09', 'empresa' => 'Acme LTDA', 'email' => 'fulano@example.com'],
            'servico'    => ['nome' => 'Plano Exemplo', 'valor_formatado' => 'R$ 99,90', 'ciclo' => 'Mensal'],
            'contrato'   => ['numero' => 'CTR-2026-0001', 'data_geracao_extenso' => '17 de maio de 2026'],
            'contratada' => ['razao_social' => 'Sua Empresa LTDA', 'cnpj' => '00.000.000/0001-00'],
            'data'       => ['hoje' => date('d/m/Y'), 'hoje_extenso' => '17 de maio de 2026'],
        ];
        $merged = $this->deepMerge($mock, $context);
        return $this->render($template, $merged);
    }

    /**
     * Extract every placeholder used in the template (for debugging).
     */
    public function extractPlaceholders(string $template): array
    {
        $out = [];
        if (preg_match_all('/\{\{\s*([a-z0-9_.]+)/iu', $template, $m)) {
            foreach ($m[1] as $p) $out['modern'][] = $p;
        }
        if (preg_match_all('/%([A-Z][A-Z0-9_]+)%/u', $template, $m)) {
            foreach ($m[1] as $p) $out['legacy'][] = $p;
        }
        if (preg_match_all('/\[([A-Z][A-Z0-9_]+)\]/u', $template, $m)) {
            foreach ($m[1] as $p) $out['bracket'][] = $p;
        }
        return array_map('array_unique', $out);
    }

    private function resolve(string $path, array $context): string
    {
        $parts = explode('.', $path);
        $cur = $context;
        foreach ($parts as $p) {
            if (is_array($cur) && array_key_exists($p, $cur)) {
                $cur = $cur[$p];
            } else {
                return '';
            }
        }
        if (is_scalar($cur)) {
            return (string) $cur;
        }
        return '';
    }

    private function applyFilter(string $filter, string $value): string
    {
        return match ($filter) {
            'upper' => mb_strtoupper($value, 'UTF-8'),
            'lower' => mb_strtolower($value, 'UTF-8'),
            'escape', 'e' => htmlspecialchars($value, ENT_QUOTES, 'UTF-8'),
            'trim'  => trim($value),
            default => $value,
        };
    }

    private function deepMerge(array $base, array $over): array
    {
        foreach ($over as $k => $v) {
            if (is_array($v) && isset($base[$k]) && is_array($base[$k])) {
                $base[$k] = $this->deepMerge($base[$k], $v);
            } elseif ($v !== '' && $v !== null) {
                $base[$k] = $v;
            }
        }
        return $base;
    }
}
