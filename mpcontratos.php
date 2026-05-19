<?php
/**
 * MP Contratos - WHMCS Contract Management Addon
 *
 * Compatível com WHMCS 9.0+ / PHP 8.2+
 *
 * @package    DigitalSac\MpContratos
 * @author     DigitalSac
 * @version    1.0.0
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

require_once __DIR__ . '/lib/Bootstrap.php';

use DigitalSac\MpContratos\Bootstrap;
use DigitalSac\MpContratos\Database\Migrator;
use DigitalSac\MpContratos\Admin\Controller as AdminController;
use WHMCS\Database\Capsule;

Bootstrap::init();

/**
 * Define addon configuration in WHMCS Setup > Addon Modules.
 */
function mpcontratos_config(): array
{
    $cpfCnpjOptions = mpcontratos_client_custom_fields_options();

    return [
        'name'        => 'DigitalSac Contratos',
        'description' => 'Gerenciamento automatizado de contratos digitais com assinatura eletrônica, envio por e-mail e WhatsApp.',
        'author'      => 'DigitalSac Software Engineering',
        'version'     => '1.0.0',
        'language'    => 'portuguese-br',
        'fields'      => [
            'cpf_cnpj_field_id' => [
                'FriendlyName' => 'Campo CPF/CNPJ',
                'Type'         => 'dropdown',
                'Options'      => $cpfCnpjOptions,
                'Description'  => 'Selecione o campo personalizado do cliente que contém CPF/CNPJ. Em branco = autodetectar por nome do campo.',
            ],
            'company_name_source' => [
                'FriendlyName' => 'Origem do Nome da Empresa (Cliente)',
                'Type'         => 'dropdown',
                'Options'      => 'native,custom_field',
                'Default'      => 'native',
                'Description'  => 'native = usa tblclients.companyname; custom_field = usa custom field configurado abaixo.',
            ],
            'company_name_field_id' => [
                'FriendlyName' => 'Custom Field do Nome Empresa',
                'Type'         => 'text',
                'Size'         => '5',
                'Description'  => 'ID do custom field caso "Origem" acima esteja como custom_field. Deixe em branco se usar o nativo.',
            ],
            'contract_prefix' => [
                'FriendlyName' => 'Prefixo de Numeração',
                'Type'         => 'text',
                'Size'         => '10',
                'Default'      => 'CTR',
                'Description'  => 'Prefixo usado na geração de número de contrato (ex: CTR-2026-0001).',
            ],
            'public_link_ttl' => [
                'FriendlyName' => 'Validade do Link Público (horas)',
                'Type'         => 'text',
                'Size'         => '5',
                'Default'      => '72',
                'Description'  => 'Tempo de validade dos links públicos de assinatura enviados por WhatsApp/e-mail.',
            ],
            'auto_generate_on_invoice' => [
                'FriendlyName' => 'Gerar Contrato Automaticamente',
                'Type'         => 'yesno',
                'Description'  => 'Gerar contrato automaticamente quando uma fatura nova for criada para produtos com template associado.',
            ],
            'whatsapp_driver' => [
                'FriendlyName' => 'Driver WhatsApp',
                'Type'         => 'dropdown',
                'Options'      => 'disabled,digitalsac,zuckzapgo,generic',
                'Default'      => 'disabled',
                'Description'  => 'Backend usado para envio de mensagens. Configure as credenciais em Configurações do módulo.',
            ],
            'whatsapp_endpoint' => [
                'FriendlyName' => 'Endpoint WhatsApp',
                'Type'         => 'text',
                'Size'         => '80',
                'Description'  => 'URL base do driver selecionado (ex: https://api.digitalsac.com.br).',
            ],
            'whatsapp_token' => [
                'FriendlyName' => 'Token WhatsApp',
                'Type'         => 'password',
                'Size'         => '80',
                'Description'  => 'Token de autenticação do driver selecionado.',
            ],
            'whatsapp_session' => [
                'FriendlyName' => 'Sessão / Instance ID',
                'Type'         => 'text',
                'Size'         => '40',
                'Description'  => 'Identificador da sessão (usado por DigitalSac/ZuckZapGo).',
            ],
            'signature_methods' => [
                'FriendlyName' => 'Métodos de Assinatura',
                'Type'         => 'text',
                'Size'         => '40',
                'Default'      => 'canvas,checkbox',
                'Description'  => 'Métodos habilitados separados por vírgula: canvas, checkbox.',
            ],
            'enable_audit_export' => [
                'FriendlyName' => 'Exportação de Auditoria',
                'Type'         => 'yesno',
                'Description'  => 'Permitir exportação de trilha de auditoria LGPD em PDF.',
            ],
        ],
    ];
}

/**
 * Build dropdown options for client custom fields.
 * Format expected by WHMCS addon config: comma-separated values.
 */
function mpcontratos_client_custom_fields_options(): string
{
    try {
        $rows = Capsule::table('tblcustomfields')
            ->where('type', 'client')
            ->orderBy('fieldname')
            ->select('id', 'fieldname')
            ->get();

        $options = ['Auto detectar'];
        foreach ($rows as $row) {
            $label = trim(str_replace(',', ' ', (string) $row->fieldname));
            $options[] = (int) $row->id . ' - ' . $label;
        }

        return implode(',', $options);
    } catch (\Throwable) {
        return '';
    }
}

/**
 * Run on addon activation.
 */
function mpcontratos_activate(): array
{
    try {
        Migrator::up();
        return [
            'status'      => 'success',
            'description' => 'DigitalSac Contratos ativado com sucesso. Tabelas criadas e prontas para uso.',
        ];
    } catch (\Throwable $e) {
        logModuleCall('mpcontratos', 'activate', [], $e->getMessage(), $e->getTraceAsString());
        return [
            'status'      => 'error',
            'description' => 'Erro ao ativar: ' . $e->getMessage(),
        ];
    }
}

/**
 * Run on addon deactivation.
 *
 * Note: we intentionally DO NOT drop tables on deactivate so data is preserved
 * across reactivations. Use the dedicated `mpcontratos:uninstall` console
 * routine if you really want to remove everything.
 */
function mpcontratos_deactivate(): array
{
    return [
        'status'      => 'success',
        'description' => 'DigitalSac Contratos desativado. As tabelas e dados foram preservados.',
    ];
}

/**
 * Run on addon upgrade — runs idempotent migrations.
 */
function mpcontratos_upgrade(array $vars): void
{
    Migrator::up();
}

/**
 * Admin area output dispatcher.
 */
function mpcontratos_output(array $vars): void
{
    // Keep schema in sync for incremental updates without requiring manual reactivation.
    Migrator::up();
    (new AdminController($vars))->dispatch();
}

/**
 * Admin sidebar.
 */
function mpcontratos_sidebar(array $vars): string
{
    return AdminController::renderSidebar($vars);
}

/**
 * Client area page handler.
 */
function mpcontratos_clientarea(array $vars): array
{
    Migrator::up();
    $clientId = (int) ($_SESSION['uid'] ?? 0);
    if ($clientId === 0) {
        return [
            'pagetitle'    => 'Contratos',
            'breadcrumb'   => ['index.php?m=mpcontratos' => 'Contratos'],
            'templatefile' => 'list',
            'requirelogin' => true,
            'vars'         => ['rows' => [], 'base' => 'index.php?m=mpcontratos'],
        ];
    }
    return (new \DigitalSac\MpContratos\Client\Controller())->dispatch($clientId);
}
