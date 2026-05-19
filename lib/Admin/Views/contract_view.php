<?php
/** @var object $row */
/** @var object|null $client */
/** @var object|null $signature */
/** @var object|null $contratada */
/** @var bool|null $integrityOk */
/** @var array $links */
/** @var array $logs */
/** @var string $base */
/** @var string $csrf */
/** @var string $flash */

use DigitalSac\MpContratos\Contract\PublicLinkService;
?>
<?php if ($flash): ?><div class="alert alert-info" style="word-break:break-all;"><?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
  <h2 style="margin:0;">
    Contrato <?= htmlspecialchars($row->number, ENT_QUOTES, 'UTF-8') ?>
    <?php
    $statusClass = ['pending'=>'warning','sent'=>'info','signed'=>'success','expired'=>'danger','cancelled'=>'default'][$row->status] ?? 'default';
    ?>
    <span class="label label-<?= $statusClass ?>" style="vertical-align:middle;font-size:14px;"><?= $row->status ?></span>
  </h2>
  <div>
    <a class="btn btn-default" href="<?= $base ?>&action=contract_pdf&id=<?= (int) $row->id ?>" target="_blank">Ver PDF</a>
    <a class="btn btn-default" href="<?= $base ?>&action=contracts">← Voltar</a>
  </div>
</div>

<div class="row">
  <div class="col-md-8">
    <div class="panel panel-default">
      <div class="panel-heading"><b>Conteúdo do contrato (snapshot v<?= (int) $row->template_version ?>)</b></div>
      <div class="panel-body" style="max-height:600px;overflow-y:auto;background:#fff;padding:18px;">
        <?= $row->rendered_html /* já renderizado e congelado */ ?>
      </div>
    </div>
  </div>
  <div class="col-md-4">

    <div class="panel panel-default">
      <div class="panel-heading"><b>Detalhes</b></div>
      <table class="table table-condensed" style="margin:0;">
        <tbody>
          <tr><th>Cliente</th><td>
            <?php if ($client): ?>
              <?= htmlspecialchars(trim(($client->firstname ?? '') . ' ' . ($client->lastname ?? '')), ENT_QUOTES, 'UTF-8') ?>
              <br><small><?= htmlspecialchars($client->email ?? '', ENT_QUOTES, 'UTF-8') ?></small>
            <?php else: ?>—<?php endif; ?>
          </td></tr>
          <tr><th>Contratada</th><td>
            <?= $contratada ? htmlspecialchars($contratada->razao_social, ENT_QUOTES, 'UTF-8') : '—' ?>
          </td></tr>
          <tr><th>Criado em</th><td><?= htmlspecialchars(date('d/m/Y H:i', strtotime((string) $row->created_at)), ENT_QUOTES, 'UTF-8') ?></td></tr>
          <tr><th>Validade</th><td><?= !empty($row->expires_at) ? htmlspecialchars(date('d/m/Y', strtotime((string) $row->expires_at)), ENT_QUOTES, 'UTF-8') : '—' ?></td></tr>
          <tr><th>Assinado em</th><td><?= !empty($row->signed_at) ? htmlspecialchars(date('d/m/Y H:i', strtotime((string) $row->signed_at)), ENT_QUOTES, 'UTF-8') : '—' ?></td></tr>
          <?php if ($signature): ?>
          <tr><th>Método</th><td><?= htmlspecialchars($signature->method, ENT_QUOTES, 'UTF-8') ?></td></tr>
          <tr><th>IP do signatário</th><td><?= htmlspecialchars($signature->ip_address, ENT_QUOTES, 'UTF-8') ?></td></tr>
          <tr><th>Hash SHA-256</th><td style="word-break:break-all;font-family:monospace;font-size:10px;"><?= htmlspecialchars(substr($signature->payload_hash, 0, 32), ENT_QUOTES, 'UTF-8') ?>…</td></tr>
          <tr><th>Integridade</th><td>
            <?php if ($integrityOk): ?>
              <span class="label label-success">✓ Válida</span>
            <?php else: ?>
              <span class="label label-danger">✗ Adulterada</span>
            <?php endif; ?>
          </td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if (!in_array($row->status, ['signed','cancelled'], true)): ?>
    <div class="panel panel-default">
      <div class="panel-heading"><b>Enviar para assinatura</b></div>
      <div class="panel-body">
        <form method="post" action="<?= $base ?>&action=contract_send">
          <?= $csrf ?>
          <input type="hidden" name="id" value="<?= (int) $row->id ?>">
          <label><input type="checkbox" name="channels[]" value="email" checked> Por e-mail</label><br>
          <label><input type="checkbox" name="channels[]" value="whatsapp"> Por WhatsApp</label>
          <br><button class="btn btn-primary btn-sm" type="submit" style="margin-top:8px;">Enviar</button>
        </form>
      </div>
    </div>

    <div class="panel panel-default">
      <div class="panel-heading"><b>Link público de assinatura</b></div>
      <div class="panel-body">
        <form method="post" action="<?= $base ?>&action=contract_link">
          <?= $csrf ?>
          <input type="hidden" name="id" value="<?= (int) $row->id ?>">
          <button class="btn btn-default btn-sm" type="submit">Gerar novo link</button>
        </form>
        <?php if (!empty($links)): ?>
          <hr>
          <small><b>Links emitidos:</b></small>
          <ul style="padding-left:18px;font-size:11px;">
          <?php foreach (array_slice($links, 0, 5) as $l): ?>
            <li>
              <code><?= htmlspecialchars(substr($l->token, 0, 12), ENT_QUOTES, 'UTF-8') ?>…</code>
              · expira <?= htmlspecialchars(date('d/m H:i', strtotime((string) $l->expires_at)), ENT_QUOTES, 'UTF-8') ?>
              <?php if (!empty($l->used_at)): ?>· <span class="text-success">usado</span><?php endif; ?>
            </li>
          <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>

    <div class="panel panel-danger">
      <div class="panel-heading"><b>Cancelar contrato</b></div>
      <div class="panel-body">
        <form method="post" action="<?= $base ?>&action=contract_cancel" onsubmit="return confirm('Cancelar este contrato? A ação é irreversível.');">
          <?= $csrf ?>
          <input type="hidden" name="id" value="<?= (int) $row->id ?>">
          <input type="text" name="reason" class="form-control input-sm" placeholder="Motivo (opcional)" style="margin-bottom:6px;">
          <button class="btn btn-danger btn-sm" type="submit">Cancelar contrato</button>
        </form>
      </div>
    </div>

    <div class="panel panel-danger">
      <div class="panel-heading"><b>Apagar contrato</b></div>
      <div class="panel-body">
        <form method="post" action="<?= $base ?>&action=contract_delete" onsubmit="return confirm('Apagar este contrato definitivamente? Esta ação remove contrato, assinatura, links e logs.');">
          <?= $csrf ?>
          <input type="hidden" name="id" value="<?= (int) $row->id ?>">
          <button class="btn btn-danger btn-sm" type="submit">Apagar definitivamente</button>
        </form>
      </div>
    </div>
    <?php endif; ?>

  </div>
</div>

<h3 style="margin-top:24px;">Histórico (Auditoria)</h3>
<table class="table table-condensed table-striped">
  <thead><tr><th>Data/Hora</th><th>Evento</th><th>Canal</th><th>IP</th><th>Detalhes</th></tr></thead>
  <tbody>
    <?php if (empty($logs)): ?>
      <tr><td colspan="5" class="text-center text-muted">Sem eventos registrados.</td></tr>
    <?php endif; ?>
    <?php foreach ($logs as $log): ?>
      <tr>
        <td style="white-space:nowrap;"><?= htmlspecialchars(date('d/m/Y H:i:s', strtotime((string) $log->created_at)), ENT_QUOTES, 'UTF-8') ?></td>
        <td><span class="label label-default"><?= htmlspecialchars($log->event, ENT_QUOTES, 'UTF-8') ?></span></td>
        <td><?= htmlspecialchars((string) ($log->channel ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
        <td style="font-family:monospace;font-size:11px;"><?= htmlspecialchars((string) ($log->ip_address ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
        <td style="font-family:monospace;font-size:11px;max-width:400px;overflow:hidden;text-overflow:ellipsis;">
          <?= htmlspecialchars((string) ($log->payload ?? ''), ENT_QUOTES, 'UTF-8') ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
