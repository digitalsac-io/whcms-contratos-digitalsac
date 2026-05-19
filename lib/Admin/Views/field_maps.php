<?php
/** @var array $rows */
/** @var array $customFields */
/** @var string $base */
/** @var string $csrf */
/** @var string $flash */
?>
<?php if ($flash): ?><div class="alert alert-info"><?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

<h2 style="margin:0 0 14px;">Mapeamento de Custom Fields</h2>
<p class="text-muted">
  Vincule um Custom Field do WHMCS a uma variável de template. Útil para campos como RG, IE, Documento Adicional etc.
  Uma vez mapeado, use no template como <code>{{custom.&lt;variavel&gt;}}</code> (ex.: <code>{{custom.rg}}</code>).
</p>
<p class="text-muted">
  Custom fields também são acessíveis automaticamente via slug do nome do campo (sem precisar de mapeamento), mas o mapeamento dá um nome curto e estável.
</p>

<div class="row">
  <div class="col-md-7">
    <table class="table table-striped">
      <thead>
        <tr><th>Variável</th><th>Custom Field</th><th>Rótulo</th><th></th></tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="4" class="text-center text-muted">Nenhum mapeamento.</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $r): ?>
          <?php
            $cf = null;
            foreach ($customFields as $f) if ((int) $f->id === (int) $r->custom_field_id) { $cf = $f; break; }
          ?>
          <tr>
            <td><code>{{custom.<?= htmlspecialchars($r->variable_slug, ENT_QUOTES, 'UTF-8') ?>}}</code></td>
            <td>
              <?php if ($cf): ?>
                <?= htmlspecialchars($cf->fieldname, ENT_QUOTES, 'UTF-8') ?>
                <small class="text-muted">(#<?= (int) $cf->id ?>)</small>
              <?php else: ?>
                <span class="text-danger">Campo #<?= (int) $r->custom_field_id ?> não encontrado</span>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars((string) ($r->label ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            <td class="text-right">
              <form method="post" action="<?= $base ?>&action=field_map_delete" style="display:inline;" onsubmit="return confirm('Remover este mapeamento?');">
                <?= $csrf ?>
                <input type="hidden" name="id" value="<?= (int) $r->id ?>">
                <button class="btn btn-xs btn-danger" type="submit">Excluir</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="col-md-5">
    <div class="panel panel-default">
      <div class="panel-heading"><b>Adicionar mapeamento</b></div>
      <div class="panel-body">
        <form method="post" action="<?= $base ?>&action=field_map_save">
          <?= $csrf ?>
          <div class="form-group">
            <label>Variável (slug)</label>
            <input type="text" name="variable_slug" required pattern="[a-z0-9_]+" class="form-control" placeholder="ex.: rg, doc_adicional">
            <small class="text-muted">Apenas letras minúsculas, números e underscore.</small>
          </div>
          <div class="form-group">
            <label>Custom Field do WHMCS</label>
            <select name="custom_field_id" required class="form-control">
              <option value="">— Selecione —</option>
              <?php foreach ($customFields as $f): ?>
                <option value="<?= (int) $f->id ?>">
                  <?= htmlspecialchars($f->fieldname, ENT_QUOTES, 'UTF-8') ?> (#<?= (int) $f->id ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Rótulo (descrição)</label>
            <input type="text" name="label" class="form-control" placeholder="Ex.: Registro Geral">
          </div>
          <button class="btn btn-primary btn-sm" type="submit">Salvar mapeamento</button>
        </form>
      </div>
    </div>
  </div>
</div>
