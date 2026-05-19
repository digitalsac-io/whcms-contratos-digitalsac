<?php
/** @var array $stats */
/** @var array $recent */
/** @var bool $hasContratadas */
/** @var string $base */
/** @var string $flash */
?>
<?php if ($flash): ?>
  <div class="alert alert-info"><?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<?php if (!$hasContratadas): ?>
  <div class="alert alert-warning">
    <b>Atenção:</b> nenhuma Contratada cadastrada.
    <a href="<?= $base ?>&action=contratada_edit">Cadastre a primeira</a> antes de gerar contratos.
  </div>
<?php endif; ?>

<div class="row" style="margin-bottom: 16px;">
  <?php foreach ([
    ['total',     'Total',      '#6c757d'],
    ['pending',   'Pendentes',  '#f0ad4e'],
    ['sent',      'Enviados',   '#0c6dfd'],
    ['signed',    'Assinados',  '#198754'],
    ['expired',   'Expirados',  '#dc3545'],
    ['cancelled', 'Cancelados', '#343a40'],
  ] as [$k, $label, $color]): ?>
  <div class="col-md-2">
    <div style="background:<?= $color ?>;color:#fff;border-radius:8px;padding:14px;text-align:center;">
      <div style="font-size:26px;font-weight:700;line-height:1.1;"><?= (int) ($stats[$k] ?? 0) ?></div>
      <div style="font-size:12px;opacity:.85;margin-top:4px;"><?= $label ?></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<h3 style="margin-top:24px;">Últimos contratos</h3>
<table class="table table-striped">
  <thead>
    <tr>
      <th>Número</th><th>Cliente</th><th>Status</th><th>Criado em</th><th></th>
    </tr>
  </thead>
  <tbody>
    <?php if (empty($recent)): ?>
      <tr><td colspan="5" class="text-center text-muted">Nenhum contrato ainda.</td></tr>
    <?php endif; ?>
    <?php foreach ($recent as $r): ?>
      <tr>
        <td><b><?= htmlspecialchars($r->number, ENT_QUOTES, 'UTF-8') ?></b></td>
        <td>
          <?= htmlspecialchars(trim(($r->firstname ?? '') . ' ' . ($r->lastname ?? '')), ENT_QUOTES, 'UTF-8') ?>
          <?php if (!empty($r->companyname)): ?>
            <small class="text-muted">(<?= htmlspecialchars($r->companyname, ENT_QUOTES, 'UTF-8') ?>)</small>
          <?php endif; ?>
        </td>
        <td><span class="label label-default"><?= htmlspecialchars($r->status, ENT_QUOTES, 'UTF-8') ?></span></td>
        <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime((string) $r->created_at)), ENT_QUOTES, 'UTF-8') ?></td>
        <td>
          <a class="btn btn-xs btn-default" href="<?= $base ?>&action=contract_view&id=<?= (int) $r->id ?>">Ver</a>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<p style="margin-top:14px;">
  <a class="btn btn-primary" href="<?= $base ?>&action=contract_new">+ Novo Contrato</a>
  <a class="btn btn-default" href="<?= $base ?>&action=contracts">Todos os contratos</a>
</p>
