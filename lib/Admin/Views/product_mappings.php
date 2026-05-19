<?php
/** @var array $rows */
/** @var array $products */
/** @var array $templates */
/** @var array $contratadas */
/** @var string $modulelink */
?>
<h2>Mapeamento Produto × Template</h2>
<p class="text-muted">Defina qual template (e qual contratada) é usado para cada produto WHMCS. Marque "Auto-gerar" para que contratos sejam criados automaticamente quando uma fatura para esse produto for emitida.</p>

<div class="panel panel-default">
  <div class="panel-heading">Adicionar mapeamento</div>
  <div class="panel-body">
    <form method="post" action="<?= $modulelink ?>&section=product_mappings&action=save" class="form-inline">
      <?= \DigitalSac\MpContratos\Support\Csrf::field() ?>
      <select name="product_id" class="form-control" required>
        <option value="">Produto…</option>
        <?php foreach ($products as $p): ?>
          <option value="<?= $p->id ?>"><?= htmlspecialchars($p->name) ?> (#<?= $p->id ?>)</option>
        <?php endforeach ?>
      </select>
      <select name="template_id" class="form-control" required>
        <option value="">Template…</option>
        <?php foreach ($templates as $t): ?>
          <option value="<?= $t->id ?>"><?= htmlspecialchars($t->name) ?></option>
        <?php endforeach ?>
      </select>
      <select name="contratada_id" class="form-control">
        <option value="">Contratada padrão</option>
        <?php foreach ($contratadas as $c): ?>
          <option value="<?= $c->id ?>"><?= htmlspecialchars($c->razao_social) ?></option>
        <?php endforeach ?>
      </select>
      <label><input type="checkbox" name="auto_generate" value="1" checked> Auto-gerar</label>
      <button type="submit" class="btn btn-primary">Salvar</button>
    </form>
  </div>
</div>

<table class="table table-striped mpc-table">
  <thead><tr><th>Produto</th><th>Template</th><th>Contratada</th><th>Auto-gerar</th><th></th></tr></thead>
  <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= htmlspecialchars($r->product_name ?? ('#' . $r->product_id)) ?></td>
        <td><?= htmlspecialchars($r->template_name ?? ('#' . $r->template_id)) ?></td>
        <td><?= htmlspecialchars($r->razao_social ?? '(padrão)') ?></td>
        <td><?= $r->auto_generate ? 'Sim' : 'Não' ?></td>
        <td>
          <a href="<?= $modulelink ?>&section=product_mappings&action=delete&id=<?= $r->id ?>" class="btn btn-xs btn-danger" onclick="return confirm('Remover este mapeamento?')">Remover</a>
        </td>
      </tr>
    <?php endforeach ?>
    <?php if (empty($rows)): ?>
      <tr><td colspan="5" class="text-muted">Nenhum mapeamento cadastrado.</td></tr>
    <?php endif ?>
  </tbody>
</table>
