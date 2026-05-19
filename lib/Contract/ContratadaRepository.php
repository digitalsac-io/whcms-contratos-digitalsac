<?php

declare(strict_types=1);

namespace DigitalSac\MpContratos\Contract;

use DigitalSac\MpContratos\Database\Migrator;
use WHMCS\Database\Capsule;

/**
 * CRUD operations for multiple contracting entities ("Contratadas").
 *
 * DigitalSac has two owners → two legal entities → two contratadas.
 * Each template/product mapping can be bound to a specific one, and the
 * choice is snapshotted on contract creation so renaming an entity later
 * does not retroactively alter past contracts.
 */
final class ContratadaRepository
{
    public function all(bool $onlyActive = true): array
    {
        $q = Capsule::table(Migrator::TABLE_CONTRATADAS);
        if ($onlyActive) {
            $q->where('active', 1);
        }
        return $q->orderByDesc('is_default')->orderBy('razao_social')->get()->all();
    }

    public function find(int $id): ?object
    {
        $row = Capsule::table(Migrator::TABLE_CONTRATADAS)->where('id', $id)->first();
        return $row ?: null;
    }

    public function default(): ?object
    {
        $row = Capsule::table(Migrator::TABLE_CONTRATADAS)
            ->where('active', 1)->where('is_default', 1)->first();
        if ($row) {
            return $row;
        }
        $row = Capsule::table(Migrator::TABLE_CONTRATADAS)->where('active', 1)->first();
        return $row ?: null;
    }

    public function resolveForProduct(int $productId, ?int $fallbackId = null): ?object
    {
        $mapping = Capsule::table(Migrator::TABLE_PRODUCT_TEMPLATES)
            ->where('product_id', $productId)
            ->whereNotNull('contratada_id')
            ->first();

        if ($mapping && $mapping->contratada_id) {
            $row = $this->find((int) $mapping->contratada_id);
            if ($row) return $row;
        }
        if ($fallbackId) {
            $row = $this->find($fallbackId);
            if ($row) return $row;
        }
        return $this->default();
    }

    public function create(array $data): int
    {
        $now = date('Y-m-d H:i:s');
        $data = $this->normalize($data);
        $data['created_at'] = $now;
        $data['updated_at'] = $now;

        if (!empty($data['is_default'])) {
            Capsule::table(Migrator::TABLE_CONTRATADAS)->update(['is_default' => 0]);
        } elseif ($this->count() === 0) {
            $data['is_default'] = 1;
        }

        return (int) Capsule::table(Migrator::TABLE_CONTRATADAS)->insertGetId($data);
    }

    public function update(int $id, array $data): void
    {
        $data = $this->normalize($data);
        $data['updated_at'] = date('Y-m-d H:i:s');

        if (!empty($data['is_default'])) {
            Capsule::table(Migrator::TABLE_CONTRATADAS)
                ->where('id', '!=', $id)
                ->update(['is_default' => 0]);
        }
        Capsule::table(Migrator::TABLE_CONTRATADAS)->where('id', $id)->update($data);
    }

    public function delete(int $id): void
    {
        Capsule::table(Migrator::TABLE_CONTRATADAS)->where('id', $id)->delete();
    }

    public function count(): int
    {
        return (int) Capsule::table(Migrator::TABLE_CONTRATADAS)->count();
    }

    private function normalize(array $data): array
    {
        $allowed = [
            'razao_social','nome_fantasia','cnpj','inscricao_estadual','inscricao_municipal',
            'endereco','numero','complemento','bairro','cidade','uf','cep','pais',
            'email','telefone','logo_path','signature_path','signature_blob','cert_path','cert_blob','cert_password','cert_enabled',
            'signatory_name','signatory_role',
            'is_default','active',
        ];
        $out = [];
        foreach ($allowed as $k) {
            if (array_key_exists($k, $data)) {
                $out[$k] = is_string($data[$k]) ? trim($data[$k]) : $data[$k];
            }
        }
        if (isset($out['cnpj'])) {
            $out['cnpj'] = preg_replace('/\D/', '', (string) $out['cnpj']);
        }
        if (isset($out['cep'])) {
            $out['cep'] = preg_replace('/\D/', '', (string) $out['cep']);
        }
        $out['is_default'] = !empty($out['is_default']) ? 1 : 0;
        $out['cert_enabled'] = !empty($out['cert_enabled']) ? 1 : 0;
        $out['active']     = array_key_exists('active', $out) ? (int) (bool) $out['active'] : 1;
        return $out;
    }
}
