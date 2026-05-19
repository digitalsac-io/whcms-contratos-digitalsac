<?php

declare(strict_types=1);

namespace DigitalSac\MpContratos\Contract;

use DigitalSac\MpContratos\Bootstrap;
use DigitalSac\MpContratos\Database\Migrator;
use DigitalSac\MpContratos\Support\Context;
use DigitalSac\MpContratos\Support\Logger;
use WHMCS\Database\Capsule;

final class ContractManager
{
    public function __construct(
        private readonly TemplateParser $parser = new TemplateParser(),
        private readonly Context $context = new Context(),
        private readonly ContratadaRepository $contratadas = new ContratadaRepository(),
    ) {}

    public const STATUS_PENDING   = 'pending';
    public const STATUS_SENT      = 'sent';
    public const STATUS_SIGNED    = 'signed';
    public const STATUS_EXPIRED   = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Generate a contract for a given client/service/invoice combination.
     * Snapshots the template body and context so the contract is immutable
     * even if the template/contratada changes later.
     *
     * @return int contract id
     */
    public function generate(
        int $clientId,
        int $templateId,
        ?int $serviceId = null,
        ?int $invoiceId = null,
        ?int $contratadaId = null,
        ?\DateTimeInterface $expiresAt = null,
        ?int $adminId = null,
    ): int {
        $template = Capsule::table(Migrator::TABLE_TEMPLATES)->where('id', $templateId)->first();
        if (!$template) {
            throw new \RuntimeException("Template {$templateId} não encontrado.");
        }
        if (!$template->active) {
            throw new \RuntimeException("Template {$templateId} está inativo.");
        }

        // Resolve contratada: explicit > product mapping > default
        if (!$contratadaId && $serviceId) {
            $svc = Capsule::table('tblhosting')->where('id', $serviceId)->first();
            if ($svc) {
                $res = $this->contratadas->resolveForProduct((int) $svc->packageid);
                if ($res) $contratadaId = (int) $res->id;
            }
        }
        if (!$contratadaId) {
            $def = $this->contratadas->default();
            if (!$def) {
                throw new \RuntimeException('Nenhuma Contratada cadastrada. Cadastre ao menos uma antes de gerar contratos.');
            }
            $contratadaId = (int) $def->id;
        }

        $number   = $this->nextNumber();
        $expires  = $expiresAt ?? (new \DateTimeImmutable('+1 year'));

        $ctxOverrides = [
            'numero'      => $number,
            'validade'    => $expires->format('d/m/Y'),
            'valor_total' => '',
        ];
        $context = $this->context->build($clientId, $contratadaId, $serviceId, $invoiceId, $ctxOverrides);
        $templateBody = $this->normalizeTemplateHtml((string) $template->body_html);
        $html = $this->parser->render($templateBody, $context);

        $now = date('Y-m-d H:i:s');
        $id = (int) Capsule::table(Migrator::TABLE_CONTRACTS)->insertGetId([
            'number'           => $number,
            'client_id'        => $clientId,
            'contratada_id'    => $contratadaId,
            'service_id'       => $serviceId,
            'invoice_id'       => $invoiceId,
            'template_id'      => $templateId,
            'template_version' => (int) $template->version,
            'rendered_html'    => $html,
            'context_snapshot' => json_encode($context, JSON_UNESCAPED_UNICODE),
            'status'           => self::STATUS_PENDING,
            'expires_at'       => $expires->format('Y-m-d H:i:s'),
            'created_at'       => $now,
            'updated_at'       => $now,
        ]);

        Logger::event('generated', $id, [
            'template_id'   => $templateId,
            'contratada_id' => $contratadaId,
            'service_id'    => $serviceId,
            'invoice_id'    => $invoiceId,
        ], null, $adminId);

        return $id;
    }

    /**
     * Auto-generate contracts when a new invoice covers products that have
     * a template mapping with auto_generate=true. Called by InvoiceCreated hook.
     */
    public function autoGenerateForInvoice(int $invoiceId): array
    {
        $autoEnabled = Bootstrap::setting('auto_generate_on_invoice');
        if (!$autoEnabled) {
            return [];
        }
        $invoice = Capsule::table('tblinvoices')->where('id', $invoiceId)->first();
        if (!$invoice) return [];

        $items = Capsule::table('tblinvoiceitems')
            ->where('invoiceid', $invoiceId)
            ->where('type', 'Hosting')
            ->get();

        $created = [];
        foreach ($items as $item) {
            $svc = Capsule::table('tblhosting')->where('id', $item->relid)->first();
            if (!$svc) continue;

            // Already has contract for this service+invoice? skip
            $exists = Capsule::table(Migrator::TABLE_CONTRACTS)
                ->where('service_id', $svc->id)
                ->whereIn('status', [self::STATUS_PENDING, self::STATUS_SENT, self::STATUS_SIGNED])
                ->exists();
            if ($exists) continue;

            $mapping = Capsule::table(Migrator::TABLE_PRODUCT_TEMPLATES)
                ->where('product_id', $svc->packageid)
                ->where('auto_generate', 1)
                ->first();
            if (!$mapping) continue;

            try {
                $cid = $this->generate(
                    clientId:     (int) $invoice->userid,
                    templateId:   (int) $mapping->template_id,
                    serviceId:    (int) $svc->id,
                    invoiceId:    $invoiceId,
                    contratadaId: $mapping->contratada_id ? (int) $mapping->contratada_id : null,
                );
                $created[] = $cid;
            } catch (\Throwable $e) {
                Logger::moduleLog('autoGenerate', ['invoice_id' => $invoiceId, 'service_id' => $svc->id], $e->getMessage());
            }
        }
        return $created;
    }

    public function find(int $id): ?object
    {
        return Capsule::table(Migrator::TABLE_CONTRACTS)->where('id', $id)->first() ?: null;
    }

    public function findByNumber(string $number): ?object
    {
        return Capsule::table(Migrator::TABLE_CONTRACTS)->where('number', $number)->first() ?: null;
    }

    public function listForClient(int $clientId): array
    {
        return Capsule::table(Migrator::TABLE_CONTRACTS)
            ->where('client_id', $clientId)
            ->orderByDesc('id')
            ->get()->all();
    }

    public function search(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $q = Capsule::table(Migrator::TABLE_CONTRACTS . ' as c')
            ->leftJoin('tblclients as cl', 'cl.id', '=', 'c.client_id')
            ->select('c.*', 'cl.firstname', 'cl.lastname', 'cl.companyname', 'cl.email');

        if (!empty($filters['status']))     $q->where('c.status', $filters['status']);
        if (!empty($filters['template_id']))$q->where('c.template_id', (int) $filters['template_id']);
        if (!empty($filters['client_id']))  $q->where('c.client_id', (int) $filters['client_id']);
        if (!empty($filters['date_from']))  $q->where('c.created_at', '>=', $filters['date_from']);
        if (!empty($filters['date_to']))    $q->where('c.created_at', '<=', $filters['date_to']);
        if (!empty($filters['q'])) {
            $term = '%' . $filters['q'] . '%';
            $q->where(function ($w) use ($term) {
                $w->where('c.number', 'like', $term)
                  ->orWhere('cl.firstname', 'like', $term)
                  ->orWhere('cl.lastname', 'like', $term)
                  ->orWhere('cl.companyname', 'like', $term)
                  ->orWhere('cl.email', 'like', $term);
            });
        }

        $total = (clone $q)->count();
        $rows  = $q->orderByDesc('c.id')->limit($limit)->offset($offset)->get()->all();
        return ['total' => $total, 'rows' => $rows];
    }

    public function stats(): array
    {
        $rows = Capsule::table(Migrator::TABLE_CONTRACTS)
            ->select('status', Capsule::raw('count(*) as n'))
            ->groupBy('status')
            ->get();
        $out = [
            'total'     => 0,
            'pending'   => 0,
            'sent'      => 0,
            'signed'    => 0,
            'expired'   => 0,
            'cancelled' => 0,
        ];
        foreach ($rows as $r) {
            $out[$r->status] = (int) $r->n;
            $out['total']   += (int) $r->n;
        }
        return $out;
    }

    public function cancel(int $contractId, ?int $adminId = null, string $reason = ''): void
    {
        Capsule::table(Migrator::TABLE_CONTRACTS)->where('id', $contractId)->update([
            'status'       => self::STATUS_CANCELLED,
            'cancelled_at' => date('Y-m-d H:i:s'),
            'updated_at'   => date('Y-m-d H:i:s'),
        ]);
        Logger::event('cancelled', $contractId, ['reason' => $reason], null, $adminId);
    }

    public function delete(int $contractId, ?int $adminId = null): bool
    {
        $row = $this->find($contractId);
        if (!$row) {
            return false;
        }

        return (bool) Capsule::connection()->transaction(function () use ($contractId, $adminId, $row) {
            Capsule::table(Migrator::TABLE_SIGNATURES)->where('contract_id', $contractId)->delete();
            Capsule::table(Migrator::TABLE_PUBLIC_LINKS)->where('contract_id', $contractId)->delete();
            Capsule::table(Migrator::TABLE_LOGS)->where('contract_id', $contractId)->delete();
            $deleted = Capsule::table(Migrator::TABLE_CONTRACTS)->where('id', $contractId)->delete();

            if ($deleted > 0) {
                Logger::moduleLog('contract.delete', [
                    'contract_id' => $contractId,
                    'number' => (string) ($row->number ?? ''),
                    'admin_id' => $adminId,
                ], 'deleted');
                return true;
            }
            return false;
        });
    }

    public function expireOverdue(): int
    {
        $now = date('Y-m-d H:i:s');

        // 1) Expire contracts whose own expires_at has passed AND that don't have
        //    any active (unused, non-expired) public signature link. While a valid
        //    link exists the client can still sign, so the contract should remain
        //    in its current pending/sent status.
        $rows = Capsule::table(Migrator::TABLE_CONTRACTS)
            ->whereIn('status', [self::STATUS_PENDING, self::STATUS_SENT])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', $now)
            ->get();

        $count = 0;
        foreach ($rows as $r) {
            if ($this->hasActivePublicLink((int) $r->id, $now)) {
                continue;
            }
            Capsule::table(Migrator::TABLE_CONTRACTS)->where('id', $r->id)->update([
                'status'     => self::STATUS_EXPIRED,
                'updated_at' => $now,
            ]);
            Logger::event('expired', (int) $r->id);
            $count++;
        }

        // 2) Auto-recover: any contract sitting in `expired` that still has an
        //    active public link is brought back to `sent` so the client can use it.
        $expiredRows = Capsule::table(Migrator::TABLE_CONTRACTS)
            ->where('status', self::STATUS_EXPIRED)
            ->get();

        foreach ($expiredRows as $r) {
            if (!$this->hasActivePublicLink((int) $r->id, $now)) {
                continue;
            }
            $this->reactivate((int) $r->id, null, 'auto: link público ativo');
        }

        return $count;
    }

    /**
     * Returns true when the contract has at least one public signature link
     * that has neither been consumed nor passed its own expires_at.
     */
    private function hasActivePublicLink(int $contractId, ?string $now = null): bool
    {
        $now ??= date('Y-m-d H:i:s');
        return Capsule::table(Migrator::TABLE_PUBLIC_LINKS)
            ->where('contract_id', $contractId)
            ->whereNull('used_at')
            ->where('expires_at', '>', $now)
            ->exists();
    }

    /**
     * Bring an expired contract back to `sent` (or `pending` if it was never
     * delivered). Useful when an active public link still exists or when an
     * admin extends the validity manually.
     */
    public function reactivate(int $contractId, ?int $adminId = null, string $reason = ''): bool
    {
        $row = $this->find($contractId);
        if (!$row) {
            return false;
        }
        if ($row->status !== self::STATUS_EXPIRED) {
            return false;
        }

        $hasSentLog = Capsule::table(Migrator::TABLE_LOGS)
            ->where('contract_id', $contractId)
            ->where('event', 'like', 'sent_%')
            ->exists();
        $newStatus = $hasSentLog ? self::STATUS_SENT : self::STATUS_PENDING;

        Capsule::table(Migrator::TABLE_CONTRACTS)->where('id', $contractId)->update([
            'status'     => $newStatus,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        Logger::event('reactivated', $contractId, [
            'from'   => self::STATUS_EXPIRED,
            'to'     => $newStatus,
            'reason' => $reason,
        ], null, $adminId);
        return true;
    }

    public function markSent(int $contractId, string $channel): void
    {
        $row = $this->find($contractId);
        if (!$row) return;
        if ($row->status === self::STATUS_PENDING) {
            Capsule::table(Migrator::TABLE_CONTRACTS)->where('id', $contractId)->update([
                'status'     => self::STATUS_SENT,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
        Logger::event('sent_' . $channel, $contractId, [], $channel);
    }

    public function markSigned(int $contractId): void
    {
        Capsule::table(Migrator::TABLE_CONTRACTS)->where('id', $contractId)->update([
            'status'     => self::STATUS_SIGNED,
            'signed_at'  => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        Logger::event('signed', $contractId);
    }

    /**
     * Generate the next contract number in the format <PREFIX>-<YYYY>-<NNNNN>.
     */
    private function nextNumber(): string
    {
        $prefix = (string) Bootstrap::setting('contract_prefix', 'CTR');
        $year   = date('Y');

        // Find the highest sequence used this year for this prefix
        $like = sprintf('%s-%s-%%', $prefix, $year);
        $last = Capsule::table(Migrator::TABLE_CONTRACTS)
            ->where('number', 'like', $like)
            ->orderByDesc('id')
            ->value('number');

        $seq = 1;
        if ($last && preg_match('/-(\d+)$/', $last, $m)) {
            $seq = (int) $m[1] + 1;
        }
        return sprintf('%s-%s-%05d', $prefix, $year, $seq);
    }

    private function normalizeTemplateHtml(string $templateBody): string
    {
        if (!str_contains($templateBody, '&lt;') && !str_contains($templateBody, '&gt;')) {
            return $templateBody;
        }

        $decoded = html_entity_decode($templateBody, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (str_contains($decoded, '<') && str_contains($decoded, '>')) {
            return $decoded;
        }

        return $templateBody;
    }
}
