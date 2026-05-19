<?php
/** @var array $products */
/** @var array $mappings */
/** @var array $templates */
/** @var array $contratadasL */
/** @var string $base */
/** @var string $csrf */
/** @var string $flash */
?>
<?php if ($flash): ?><div class="alert alert-info"><?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

<h2 style="margin:0 0 14px;">Produtos × Templates × Contratadas</h2>
<p class="text-muted">Defina qual template é usado para cada produto e, opcionalmente, qual contratada. Marque <b>Auto</b> para gerar o contrato automaticamente quando uma fatura for criada (hook <code>InvoiceCreated</code>).</p>

<form method="post" action="<?= $base ?>&action=product_save">
  <?= $csrf ?>
  <table class="table table-striped table-hover">
    <thead>
      <tr>
        <th>Produto</th>
        <th>Grupo</th>
        <th>Template</th>
        <th>Contratada</th>
        <th class="text-center">Auto</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($products)): ?>
        <tr><td colspan="5" class="text-center text-muted">Nenhum produto cadastrado no WHMCS.</td></tr>
      <?php endif; ?>
      <?php foreach ($products as $p): ?>
        <?php
          $m = $mappings[$p->id] ?? null;
          $selTpl = $m->template_id ?? '';
          $selCtt = $m->contratada_id ?? '';
          $auto   = !empty($m->auto_generate);
        ?>
        <tr>
          <td><b><?= htmlspecialchars($p->name, ENT_QUOTES, 'UTF-8') ?></b></td>
          <td><small class="text-muted"><?= htmlspecialchars((string) ($p->group_name ?? ''), ENT_QUOTES, 'UTF-8') ?></small></td>
          <td>
            <select name="mapping[<?= (int) $p->id ?>][template_id]" class="form-control input-sm">
              <option value="">— Sem contrato —</option>
              <?php foreach ($templates as $t): ?>
                <option value="<?= (int) $t->id ?>" <?= (int) $selTpl === (int) $t->id ? 'selected' : '' ?>>
                  <?= htmlspecialchars($t->name, ENT_QUOTES, 'UTF-8') ?>
                </option>
              <?php endforeach; ?>
            </select>
          </td>
          <td>
            <select name="mapping[<?= (int) $p->id ?>][contratada_id]" class="form-control input-sm">
              <option value="">— Padrão —</option>
              <?php foreach ($contratadasL as $c): ?>
                <option value="<?= (int) $c->id ?>" <?= (int) $selCtt === (int) $c->id ? 'selected' : '' ?>>
                  <?= htmlspecialchars($c->razao_social, ENT_QUOTES, 'UTF-8') ?>
                </option>
              <?php endforeach; ?>
            </select>
          </td>
          <td class="text-center">
            <input type="checkbox" name="mapping[<?= (int) $p->id ?>][auto_generate]" value="1" <?= $auto ? 'checked' : '' ?>>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <button class="btn btn-primary" type="submit">Salvar Mapeamentos</button>
</form>
