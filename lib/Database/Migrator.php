<?php

declare(strict_types=1);

namespace DigitalSac\MpContratos\Database;

use WHMCS\Database\Capsule;
use Illuminate\Database\Schema\Blueprint;

/**
 * Idempotent schema migrator. Safe to run on activation and on upgrade.
 *
 * All tables are prefixed with `mod_mpcontratos_`.
 */
final class Migrator
{
    public const TABLE_CONTRATADAS       = 'mod_mpcontratos_contratadas';
    public const TABLE_TEMPLATES         = 'mod_mpcontratos_templates';
    public const TABLE_CONTRACTS         = 'mod_mpcontratos_contracts';
    public const TABLE_SIGNATURES        = 'mod_mpcontratos_signatures';
    public const TABLE_PRODUCT_TEMPLATES = 'mod_mpcontratos_product_templates';
    public const TABLE_PUBLIC_LINKS      = 'mod_mpcontratos_public_links';
    public const TABLE_LOGS              = 'mod_mpcontratos_logs';
    public const TABLE_FIELD_MAPS        = 'mod_mpcontratos_field_maps';

    public static function up(): void
    {
        $schema = Capsule::schema();

        if (!$schema->hasTable(self::TABLE_CONTRATADAS)) {
            $schema->create(self::TABLE_CONTRATADAS, function (Blueprint $t): void {
                $t->increments('id');
                $t->string('razao_social', 200);
                $t->string('nome_fantasia', 200)->nullable();
                $t->string('cnpj', 18);
                $t->string('inscricao_estadual', 30)->nullable();
                $t->string('inscricao_municipal', 30)->nullable();
                $t->string('endereco', 255)->nullable();
                $t->string('numero', 20)->nullable();
                $t->string('complemento', 100)->nullable();
                $t->string('bairro', 100)->nullable();
                $t->string('cidade', 100)->nullable();
                $t->string('uf', 2)->nullable();
                $t->string('cep', 10)->nullable();
                $t->string('pais', 50)->default('Brasil');
                $t->string('email', 150)->nullable();
                $t->string('telefone', 30)->nullable();
                $t->string('logo_path', 255)->nullable();        // upload relativo a /storage
                $t->string('signature_path', 255)->nullable();   // assinatura do admin (PNG)
                $t->longText('signature_blob')->nullable();      // assinatura da contratada (base64 no banco)
                $t->string('cert_path', 255)->nullable();        // certificado A1 (.pfx/.p12)
                $t->longText('cert_blob')->nullable();           // fallback do certificado (base64 no banco)
                $t->text('cert_password')->nullable();           // senha do certificado A1
                $t->boolean('cert_enabled')->default(false);     // habilita assinatura digital no PDF
                $t->string('signatory_name', 150)->nullable();   // ex: "Silvio Erick"
                $t->string('signatory_role', 100)->nullable();   // ex: "Sócio-administrador"
                $t->boolean('is_default')->default(false);
                $t->boolean('active')->default(true);
                $t->timestamps();
                $t->index('cnpj');
            });
        }
        if ($schema->hasTable(self::TABLE_CONTRATADAS)) {
            if (!$schema->hasColumn(self::TABLE_CONTRATADAS, 'cert_path')) {
                $schema->table(self::TABLE_CONTRATADAS, function (Blueprint $t): void {
                    $t->string('cert_path', 255)->nullable()->after('signature_path');
                });
            }
            if (!$schema->hasColumn(self::TABLE_CONTRATADAS, 'cert_password')) {
                $schema->table(self::TABLE_CONTRATADAS, function (Blueprint $t): void {
                    $t->text('cert_password')->nullable()->after('cert_path');
                });
            }
            if (!$schema->hasColumn(self::TABLE_CONTRATADAS, 'signature_blob')) {
                $schema->table(self::TABLE_CONTRATADAS, function (Blueprint $t): void {
                    $t->longText('signature_blob')->nullable()->after('signature_path');
                });
            }
            if (!$schema->hasColumn(self::TABLE_CONTRATADAS, 'cert_blob')) {
                $schema->table(self::TABLE_CONTRATADAS, function (Blueprint $t): void {
                    $t->longText('cert_blob')->nullable()->after('cert_path');
                });
            }
            if (!$schema->hasColumn(self::TABLE_CONTRATADAS, 'cert_enabled')) {
                $schema->table(self::TABLE_CONTRATADAS, function (Blueprint $t): void {
                    $t->boolean('cert_enabled')->default(false)->after('cert_password');
                });
            }
        }

        if (!$schema->hasTable(self::TABLE_FIELD_MAPS)) {
            $schema->create(self::TABLE_FIELD_MAPS, function (Blueprint $t): void {
                $t->increments('id');
                $t->string('variable_slug', 60); // ex: "rg", "inscricao_estadual"
                $t->unsignedInteger('custom_field_id'); // tblcustomfields.id
                $t->string('label', 150)->nullable();
                $t->boolean('active')->default(true);
                $t->timestamps();
                $t->unique('variable_slug');
            });
        }

        if (!$schema->hasTable(self::TABLE_TEMPLATES)) {
            $schema->create(self::TABLE_TEMPLATES, function (Blueprint $t): void {
                $t->increments('id');
                $t->string('name', 150);
                $t->string('description', 255)->nullable();
                $t->longText('body_html');
                $t->json('variables_schema')->nullable();
                $t->unsignedInteger('version')->default(1);
                $t->boolean('active')->default(true);
                $t->timestamps();
            });
        }

        if (!$schema->hasTable(self::TABLE_PRODUCT_TEMPLATES)) {
            $schema->create(self::TABLE_PRODUCT_TEMPLATES, function (Blueprint $t): void {
                $t->increments('id');
                $t->unsignedInteger('product_id');
                $t->unsignedInteger('template_id');
                $t->unsignedInteger('contratada_id')->nullable();
                $t->boolean('auto_generate')->default(true);
                $t->timestamps();
                $t->unique(['product_id', 'template_id']);
                $t->index('product_id');
                $t->index('contratada_id');
            });
        }

        if (!$schema->hasTable(self::TABLE_CONTRACTS)) {
            $schema->create(self::TABLE_CONTRACTS, function (Blueprint $t): void {
                $t->increments('id');
                $t->string('number', 40)->unique();
                $t->unsignedInteger('client_id');
                $t->unsignedInteger('contratada_id'); // qual contratada está emitindo
                $t->unsignedInteger('service_id')->nullable();
                $t->unsignedInteger('invoice_id')->nullable();
                $t->unsignedInteger('template_id');
                $t->unsignedInteger('template_version');
                $t->longText('rendered_html');
                $t->json('context_snapshot')->nullable();
                $t->string('status', 30)->default('pending');
                $t->dateTime('expires_at')->nullable();
                $t->dateTime('signed_at')->nullable();
                $t->dateTime('cancelled_at')->nullable();
                $t->timestamps();
                $t->index(['client_id', 'status']);
                $t->index('invoice_id');
                $t->index('service_id');
                $t->index('contratada_id');
            });
        }

        if (!$schema->hasTable(self::TABLE_SIGNATURES)) {
            $schema->create(self::TABLE_SIGNATURES, function (Blueprint $t): void {
                $t->increments('id');
                $t->unsignedInteger('contract_id');
                $t->string('method', 20);
                $t->longText('canvas_data')->nullable();
                $t->string('payload_hash', 64);
                $t->ipAddress('ip_address');
                $t->string('user_agent', 500)->nullable();
                $t->string('geo_country', 2)->nullable();
                $t->string('geo_region', 100)->nullable();
                $t->string('geo_city', 100)->nullable();
                $t->json('extra')->nullable();
                $t->dateTime('signed_at');
                $t->index('contract_id');
                $t->index('payload_hash');
            });
        }

        if (!$schema->hasTable(self::TABLE_PUBLIC_LINKS)) {
            $schema->create(self::TABLE_PUBLIC_LINKS, function (Blueprint $t): void {
                $t->increments('id');
                $t->unsignedInteger('contract_id');
                $t->string('token', 64)->unique();
                $t->dateTime('expires_at');
                $t->dateTime('used_at')->nullable();
                $t->ipAddress('used_ip')->nullable();
                $t->timestamps();
                $t->index('contract_id');
            });
        }

        if (!$schema->hasTable(self::TABLE_LOGS)) {
            $schema->create(self::TABLE_LOGS, function (Blueprint $t): void {
                $t->increments('id');
                $t->unsignedInteger('contract_id')->nullable();
                $t->unsignedInteger('admin_id')->nullable();
                $t->string('event', 60);
                // generated | sent_email | sent_whatsapp | viewed | signed |
                // cancelled | expired | link_created | link_used
                $t->string('channel', 30)->nullable();
                $t->json('payload')->nullable();
                $t->ipAddress('ip_address')->nullable();
                $t->dateTime('created_at');
                $t->index(['contract_id', 'event']);
                $t->index('event');
            });
        }
    }

    public static function down(): void
    {
        $schema = Capsule::schema();
        foreach ([
            self::TABLE_LOGS,
            self::TABLE_PUBLIC_LINKS,
            self::TABLE_SIGNATURES,
            self::TABLE_CONTRACTS,
            self::TABLE_PRODUCT_TEMPLATES,
            self::TABLE_TEMPLATES,
            self::TABLE_FIELD_MAPS,
            self::TABLE_CONTRATADAS,
        ] as $table) {
            $schema->dropIfExists($table);
        }
    }
}
