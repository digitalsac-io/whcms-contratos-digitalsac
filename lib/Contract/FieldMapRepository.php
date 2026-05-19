<?php

declare(strict_types=1);

namespace DigitalSac\MpContratos\Contract;

use DigitalSac\MpContratos\Database\Migrator;
use DigitalSac\MpContratos\Support\Formatter;
use WHMCS\Database\Capsule;

/**
 * Maps user-defined variable slugs to WHMCS client custom fields.
 *
 * Example: `{{cliente.rg}}` → tblcustomfields.id = 7
 */
final class FieldMapRepository
{
    public static function all(bool $onlyActive = true): array
    {
        $q = Capsule::table(Migrator::TABLE_FIELD_MAPS);
        if ($onlyActive) {
            $q->where('active', 1);
        }
        return $q->orderBy('variable_slug')->get()->all();
    }

    /**
     * @return array<string,string>
     */
    public static function valuesForClient(int $clientId): array
    {
        $maps = self::all(true);
        if (!$maps) return [];

        $ids = array_map(static fn($m) => (int) $m->custom_field_id, $maps);
        $values = Capsule::table('tblcustomfieldsvalues')
            ->whereIn('fieldid', $ids)
            ->where('relid', $clientId)
            ->pluck('value', 'fieldid');

        $out = [];
        foreach ($maps as $m) {
            $out[$m->variable_slug] = (string) ($values[$m->custom_field_id] ?? '');
        }
        return $out;
    }

    public static function upsert(string $slug, int $customFieldId, ?string $label = null): int
    {
        $slug = Formatter::slugify($slug);
        if ($slug === '') {
            throw new \InvalidArgumentException('Slug inválido.');
        }
        $existing = Capsule::table(Migrator::TABLE_FIELD_MAPS)
            ->where('variable_slug', $slug)->first();

        $payload = [
            'variable_slug'   => $slug,
            'custom_field_id' => $customFieldId,
            'label'           => $label,
            'active'          => 1,
            'updated_at'      => date('Y-m-d H:i:s'),
        ];

        if ($existing) {
            Capsule::table(Migrator::TABLE_FIELD_MAPS)->where('id', $existing->id)->update($payload);
            return (int) $existing->id;
        }
        $payload['created_at'] = date('Y-m-d H:i:s');
        return (int) Capsule::table(Migrator::TABLE_FIELD_MAPS)->insertGetId($payload);
    }

    public static function delete(int $id): bool
    {
        return Capsule::table(Migrator::TABLE_FIELD_MAPS)->where('id', $id)->delete() > 0;
    }

    public static function availableCustomFields(): array
    {
        return Capsule::table('tblcustomfields')
            ->where('type', 'client')
            ->orderBy('fieldname')
            ->select('id', 'fieldname', 'fieldtype')
            ->get()->all();
    }
}
