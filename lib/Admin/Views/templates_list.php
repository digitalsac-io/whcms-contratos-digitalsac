<?php
/** @var array $rows */
/** @var string $base */
/** @var string $csrf */
/** @var string $flash */
?>
<?php if ($flash): ?><div class="alert alert-info"><?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
  <h2 style="margin:0;">Templates de Contrato</h2>
  <a class="btn btn-primary" href="<?= $base ?>&action=template_edit">+ Novo Template</a>
</div>

<p class="text-muted">
  Templates aceitam variáveis modernas <code>{{cliente.nome}}</code>, legadas <code>%NOME%</code> e <code>[EMPRESA]</code> (compatibilidade com Modules Pay), e filtros como <code>{{produto.valor|upper}}</code>.
</p>

<table class="table table-striped">
  <thead>
    <tr><th>Nome</th><th>Descrição</th><th class="text-center">Versão</th><th class="text-center">Ativo</th><th class="text-right">Ações</th></tr>
  </thead>
  <tbody>
    <?php if (empty($rows)): ?>
      <tr><td colspan="5" class="text-center text-muted">Nenhum template cadastrado.</td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><b><?= htmlspecialchars($r->name, ENT_QUOTES, 'UTF-8') ?></b></td>
        <td><?= htmlspecialchars((string) ($r->description ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
        <td class="text-center">v<?= (int) $r->version ?></td>
        <td class="text-center"><?= $r->active ? '<span class="label label-success">Sim</span>' : '<span class="label label-default">Não</span>' ?></td>
        <td class="text-right" style="white-space:nowrap;">
          <a class="btn btn-xs btn-default" href="<?= $base ?>&action=template_edit&id=<?= (int) $r->id ?>">Editar</a>
          <form method="post" action="<?= $base ?>&action=template_delete" style="display:inline;" onsubmit="return confirm('Excluir este template?');">
            <?= $csrf ?>
            <input type="hidden" name="id" value="<?= (int) $r->id ?>">
            <button class="btn btn-xs btn-danger" type="submit">Excluir</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
