<?php
/** @var array $rows */
/** @var int $total */
/** @var int $page */
/** @var int $limit */
/** @var array $filters */
/** @var array $templates */
/** @var string $base */
/** @var string $csrf */
/** @var string $flash */

$totalPages = max(1, (int) ceil($total / $limit));
?>
<?php if ($flash): ?><div class="alert alert-info"><?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
  <h2 style="margin:0;">Contratos <small class="text-muted">(<?= (int) $total ?>)</small></h2>
  <a class="btn btn-primary" href="<?= $base ?>&action=contract_new">+ Novo Contrato</a>
</div>

<form method="get" class="well well-sm" style="margin-bottom:14px;">
  <input type="hidden" name="module" value="mpcontratos">
  <input type="hidden" name="action" value="contracts">
  <div class="row">
    <div class="col-md-3">
      <input type="text" name="q" class="form-control" placeholder="Buscar nome/empresa/número..." value="<?= htmlspecialchars((string) $filters['q'], ENT_QUOTES, 'UTF-8') ?>">
    </div>
    <div class="col-md-2">
      <select name="status" class="form-control">
        <option value="">Todos os status</option>
        <?php foreach (['pending'=>'Pendente','sent'=>'Enviado','signed'=>'Assinado','expired'=>'Expirado','cancelled'=>'Cancelado'] as $k=>$l): ?>
          <option value="<?= $k ?>" <?= $filters['status'] === $k ? 'selected' : '' ?>><?= $l ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <select name="template_id" class="form-control">
        <option value="">Todos os templates</option>
        <?php foreach ($templates as $t): ?>
          <option value="<?= (int) $t->id ?>" <?= (int) $filters['template_id'] === (int) $t->id ? 'selected' : '' ?>><?= htmlspecialchars($t->name, ENT_QUOTES, 'UTF-8') ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <button class="btn btn-default" type="submit">Filtrar</button>
      <a class="btn btn-link btn-sm" href="<?= $base ?>&action=contracts">Limpar</a>
    </div>
  </div>
</form>

<table class="table table-striped">
  <thead>
    <tr><th>Número</th><th>Cliente</th><th>Status</th><th>Criado</th><th>Validade</th><th></th></tr>
  </thead>
  <tbody>
    <?php if (empty($rows)): ?>
      <tr><td colspan="6" class="text-center text-muted">Nenhum contrato encontrado.</td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><b><?= htmlspecialchars($r->number, ENT_QUOTES, 'UTF-8') ?></b></td>
        <td>
          <?= htmlspecialchars(trim(($r->firstname ?? '') . ' ' . ($r->lastname ?? '')), ENT_QUOTES, 'UTF-8') ?>
          <?php if (!empty($r->companyname)): ?><br><small class="text-muted"><?= htmlspecialchars($r->companyname, ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?>
        </td>
        <td>
          <?php
          $statusClass = ['pending'=>'warning','sent'=>'info','signed'=>'success','expired'=>'danger','cancelled'=>'default'][$r->status] ?? 'default';
          ?>
          <span class="label label-<?= $statusClass ?>"><?= htmlspecialchars($r->status, ENT_QUOTES, 'UTF-8') ?></span>
        </td>
        <td><?= htmlspecialchars(date('d/m/Y', strtotime((string) $r->created_at)), ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= !empty($r->expires_at) ? htmlspecialchars(date('d/m/Y', strtotime((string) $r->expires_at)), ENT_QUOTES, 'UTF-8') : '-' ?></td>
        <td class="text-right" style="white-space:nowrap;">
          <a class="btn btn-xs btn-default" href="<?= $base ?>&action=contract_view&id=<?= (int) $r->id ?>">Ver</a>
          <a class="btn btn-xs btn-default" href="<?= $base ?>&action=contract_pdf&id=<?= (int) $r->id ?>" target="_blank">PDF</a>
          <form method="post" action="<?= $base ?>&action=contract_delete" style="display:inline-block;margin-left:4px;" onsubmit="return confirm('Apagar este contrato? Esta ação remove contrato, assinatura, links e logs.');">
            <?= $csrf ?>
            <input type="hidden" name="id" value="<?= (int) $r->id ?>">
            <button type="submit" class="btn btn-xs btn-danger">Apagar</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<?php if ($totalPages > 1): ?>
<ul class="pagination">
  <?php for ($p = 1; $p <= $totalPages; $p++): ?>
    <li class="<?= $p === $page ? 'active' : '' ?>">
      <a href="<?= $base ?>&action=contracts&p=<?= $p ?>&<?= http_build_query(array_filter($filters)) ?>"><?= $p ?></a>
    </li>
  <?php endfor; ?>
</ul>
<?php endif; ?>
