<?php
/** @var array $rows */
/** @var string $base */
/** @var string $flash */
/** @var string $csrf */

use DigitalSac\MpContratos\Support\Formatter;
?>
<?php if ($flash): ?>
  <div class="alert alert-info"><?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
  <h2 style="margin:0;">Contratadas</h2>
  <a class="btn btn-primary" href="<?= $base ?>&action=contratada_edit">+ Nova Contratada</a>
</div>

<p class="text-muted">Empresas emissoras dos contratos. Cada contrato registra qual contratada foi usada (snapshot), preservando o histórico mesmo se a entidade for alterada depois.</p>

<table class="table table-striped">
  <thead>
    <tr>
      <th>Razão Social</th><th>CNPJ</th><th>Cidade/UF</th><th>Signatário</th>
      <th class="text-center">Cert. A1</th><th class="text-center">Padrão</th><th class="text-center">Ativa</th><th></th>
    </tr>
  </thead>
  <tbody>
    <?php if (empty($rows)): ?>
      <tr><td colspan="8" class="text-center text-muted">Nenhuma contratada cadastrada.</td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td>
          <b><?= htmlspecialchars($r->razao_social, ENT_QUOTES, 'UTF-8') ?></b>
          <?php if (!empty($r->nome_fantasia)): ?>
            <br><small class="text-muted"><?= htmlspecialchars($r->nome_fantasia, ENT_QUOTES, 'UTF-8') ?></small>
          <?php endif; ?>
        </td>
        <td><?= htmlspecialchars(Formatter::cnpj((string) $r->cnpj), ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars(($r->cidade ?? '') . (!empty($r->uf) ? '/' . $r->uf : ''), ENT_QUOTES, 'UTF-8') ?></td>
        <td>
          <?= htmlspecialchars((string) ($r->signatory_name ?? ''), ENT_QUOTES, 'UTF-8') ?>
          <?php if (!empty($r->signatory_role)): ?>
            <br><small class="text-muted"><?= htmlspecialchars($r->signatory_role, ENT_QUOTES, 'UTF-8') ?></small>
          <?php endif; ?>
        </td>
        <td class="text-center">
          <?php $hasCert = !empty($r->cert_path) || !empty($r->cert_blob); ?>
          <?php if (!empty($r->cert_enabled) && $hasCert): ?>
            <span class="label label-success">Ativo</span>
          <?php elseif ($hasCert): ?>
            <span class="label label-default">Salvo</span>
          <?php else: ?>
            <span class="label label-warning">Não</span>
          <?php endif; ?>
        </td>
        <td class="text-center"><?= $r->is_default ? '<span class="label label-success">✓</span>' : '' ?></td>
        <td class="text-center"><?= $r->active ? '<span class="label label-info">Sim</span>' : '<span class="label label-default">Não</span>' ?></td>
        <td class="text-right" style="white-space:nowrap;">
          <a class="btn btn-xs btn-default" href="<?= $base ?>&action=contratada_edit&id=<?= (int) $r->id ?>">Editar</a>
          <form method="post" action="<?= $base ?>&action=contratada_delete" style="display:inline;"
                onsubmit="return confirm('Excluir esta contratada? Não será possível se houver contratos vinculados.');">
            <?= $csrf ?>
            <input type="hidden" name="id" value="<?= (int) $r->id ?>">
            <button class="btn btn-xs btn-danger" type="submit">Excluir</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
