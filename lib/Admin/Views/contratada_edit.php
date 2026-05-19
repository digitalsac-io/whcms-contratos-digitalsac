<?php
/** @var object|null $row */
/** @var string $base */
/** @var string $csrf */
/** @var string $flash */

$row = $row ?? null;
$id  = $row->id ?? 0;
$hasCert = !empty($row->cert_path) || !empty($row->cert_blob);
$certStorage = !empty($row->cert_path) ? 'Arquivo' : (!empty($row->cert_blob) ? 'Banco de dados' : '—');
$v   = static fn(string $f, string $default = ''): string => htmlspecialchars((string) ($row->$f ?? $default), ENT_QUOTES, 'UTF-8');
?>

<?php if (!empty($flash)): ?>
  <div class="alert alert-info" style="margin-bottom:12px;"><?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
  <h2 style="margin:0;"><?= $id ? 'Editar Contratada' : 'Nova Contratada' ?></h2>
  <a class="btn btn-default" href="<?= $base ?>&action=contratadas">← Voltar</a>
</div>

<form method="post" action="<?= $base ?>&action=contratada_save" enctype="multipart/form-data" class="form-horizontal">
  <?= $csrf ?>
  <input type="hidden" name="id" value="<?= (int) $id ?>">

  <h4>Identificação</h4>
  <div class="form-group">
    <label class="col-sm-3 control-label">Razão Social *</label>
    <div class="col-sm-7"><input type="text" name="razao_social" required class="form-control" value="<?= $v('razao_social') ?>"></div>
  </div>
  <div class="form-group">
    <label class="col-sm-3 control-label">Nome Fantasia</label>
    <div class="col-sm-7"><input type="text" name="nome_fantasia" class="form-control" value="<?= $v('nome_fantasia') ?>"></div>
  </div>
  <div class="form-group">
    <label class="col-sm-3 control-label">CNPJ *</label>
    <div class="col-sm-4"><input type="text" name="cnpj" required class="form-control" placeholder="00.000.000/0000-00" value="<?= $v('cnpj') ?>"></div>
  </div>
  <div class="form-group">
    <label class="col-sm-3 control-label">Inscrição Estadual</label>
    <div class="col-sm-4"><input type="text" name="inscricao_estadual" class="form-control" value="<?= $v('inscricao_estadual') ?>"></div>
  </div>
  <div class="form-group">
    <label class="col-sm-3 control-label">Inscrição Municipal</label>
    <div class="col-sm-4"><input type="text" name="inscricao_municipal" class="form-control" value="<?= $v('inscricao_municipal') ?>"></div>
  </div>

  <h4 style="margin-top:24px;">Endereço</h4>
  <div class="form-group">
    <label class="col-sm-3 control-label">Logradouro</label>
    <div class="col-sm-6"><input type="text" name="endereco" class="form-control" value="<?= $v('endereco') ?>"></div>
    <div class="col-sm-2"><input type="text" name="numero" class="form-control" placeholder="Número" value="<?= $v('numero') ?>"></div>
  </div>
  <div class="form-group">
    <label class="col-sm-3 control-label">Complemento</label>
    <div class="col-sm-4"><input type="text" name="complemento" class="form-control" value="<?= $v('complemento') ?>"></div>
  </div>
  <div class="form-group">
    <label class="col-sm-3 control-label">Bairro</label>
    <div class="col-sm-4"><input type="text" name="bairro" class="form-control" value="<?= $v('bairro') ?>"></div>
  </div>
  <div class="form-group">
    <label class="col-sm-3 control-label">Cidade / UF</label>
    <div class="col-sm-4"><input type="text" name="cidade" class="form-control" value="<?= $v('cidade') ?>"></div>
    <div class="col-sm-2"><input type="text" name="uf" maxlength="2" class="form-control" placeholder="UF" value="<?= $v('uf') ?>"></div>
  </div>
  <div class="form-group">
    <label class="col-sm-3 control-label">CEP</label>
    <div class="col-sm-3"><input type="text" name="cep" class="form-control" placeholder="00000-000" value="<?= $v('cep') ?>"></div>
  </div>

  <h4 style="margin-top:24px;">Contato</h4>
  <div class="form-group">
    <label class="col-sm-3 control-label">E-mail</label>
    <div class="col-sm-5"><input type="email" name="email" class="form-control" value="<?= $v('email') ?>"></div>
  </div>
  <div class="form-group">
    <label class="col-sm-3 control-label">Telefone</label>
    <div class="col-sm-4"><input type="text" name="telefone" class="form-control" value="<?= $v('telefone') ?>"></div>
  </div>

  <h4 style="margin-top:24px;">Signatário e Assinatura Digital</h4>
  <div class="form-group">
    <label class="col-sm-3 control-label">Nome do Signatário</label>
    <div class="col-sm-5"><input type="text" name="signatory_name" class="form-control" value="<?= $v('signatory_name') ?>" placeholder="Ex.: Maria da Silva"></div>
  </div>
  <div class="form-group">
    <label class="col-sm-3 control-label">Cargo</label>
    <div class="col-sm-5"><input type="text" name="signatory_role" class="form-control" value="<?= $v('signatory_role') ?>" placeholder="Ex.: Sócia-Diretora"></div>
  </div>
  <div class="form-group">
    <label class="col-sm-3 control-label">Imagem da assinatura (PNG)</label>
    <div class="col-sm-6">
      <input type="file" name="signature" accept="image/png" class="form-control">
      <small class="text-muted">PNG com fundo transparente, ~600x200px. Será aplicada ao PDF final.</small>
      <?php if (!empty($row->signature_path) || !empty($row->signature_blob)): ?>
        <div style="margin-top:8px;border:1px solid #ddd;padding:6px;background:#fafafa;display:inline-block;">
          <small>Assinatura atual:</small><br>
          <?php if (!empty($row->signature_path)): ?>
            <img src="../modules/addons/mpcontratos/<?= htmlspecialchars($row->signature_path, ENT_QUOTES, 'UTF-8') ?>" style="max-height:60px;">
          <?php else: ?>
            <img src="data:image/png;base64,<?= htmlspecialchars((string) $row->signature_blob, ENT_QUOTES, 'UTF-8') ?>" style="max-height:60px;">
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
  <div class="form-group">
    <label class="col-sm-3 control-label">Assinar no navegador</label>
    <div class="col-sm-7">
      <p class="text-muted" style="margin-bottom:6px;">Desenhe a assinatura da contratada no quadro abaixo. Ao salvar, ela substitui a imagem de assinatura atual.</p>
      <canvas id="mpCompanySigPad" width="620" height="180" style="width:100%;max-width:620px;height:180px;border:2px dashed #bbb;border-radius:8px;background:#fafafa;cursor:crosshair;touch-action:none;"></canvas>
      <div style="margin-top:6px;">
        <button type="button" class="btn btn-default btn-xs" id="mpCompanySigClear">Limpar assinatura</button>
      </div>
      <input type="hidden" name="signature_canvas_data" id="mpCompanySigData" value="">
    </div>
  </div>
  <div class="form-group">
    <label class="col-sm-3 control-label">Usar certificado digital A1</label>
    <div class="col-sm-7">
      <label><input type="checkbox" name="cert_enabled" value="1" <?= !empty($row->cert_enabled) ? 'checked' : '' ?>> Assinar PDF com certificado da contratada</label>
      <p class="text-muted" style="margin-top:4px;">Quando habilitado, o PDF final é assinado digitalmente (ICP-Brasil A1) via arquivo .pfx/.p12.</p>
      <p style="margin:4px 0 0;">
        Status atual:
        <?php if (!empty($row->cert_enabled) && $hasCert): ?>
          <span class="label label-success">Ativo</span>
        <?php elseif ($hasCert): ?>
          <span class="label label-default">Certificado salvo (desativado)</span>
        <?php else: ?>
          <span class="label label-warning">Sem certificado</span>
        <?php endif; ?>
        <small class="text-muted" style="margin-left:8px;">Armazenamento: <?= htmlspecialchars($certStorage, ENT_QUOTES, 'UTF-8') ?></small>
      </p>
    </div>
  </div>
  <div class="form-group">
    <label class="col-sm-3 control-label">Certificado A1 (.pfx/.p12)</label>
    <div class="col-sm-6">
      <input type="file" name="cert_file" accept=".pfx,.p12,application/x-pkcs12" class="form-control">
      <small class="text-muted">Envie um novo arquivo apenas quando quiser substituir o certificado atual.</small>
      <?php if ($hasCert): ?>
        <div style="margin-top:6px;">
          <?php if (!empty($row->cert_path)): ?>
            <small>Arquivo atual: <code><?= htmlspecialchars((string) basename((string) $row->cert_path), ENT_QUOTES, 'UTF-8') ?></code></small>
          <?php else: ?>
            <small>Certificado salvo no banco de dados (sem arquivo em disco).</small>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
  <div class="form-group">
    <label class="col-sm-3 control-label">Senha do certificado</label>
    <div class="col-sm-4">
      <input type="password" name="cert_password" class="form-control" placeholder="<?= !empty($row->cert_password) ? '•••••••• (mantém se vazio)' : 'Senha do .pfx/.p12' ?>">
      <small class="text-muted">Deixe em branco para manter a senha já salva.</small><br>
      <small class="text-muted">Senha cadastrada atualmente: <?= !empty($row->cert_password) ? 'Sim' : 'Não' ?></small>
    </div>
  </div>

  <h4 style="margin-top:24px;">Configuração</h4>
  <div class="form-group">
    <div class="col-sm-offset-3 col-sm-7">
      <label><input type="checkbox" name="is_default" value="1" <?= !empty($row->is_default) ? 'checked' : '' ?>> Definir como Contratada padrão</label>
      <br>
      <label><input type="checkbox" name="active" value="1" <?= $id === 0 || !empty($row->active) ? 'checked' : '' ?>> Ativa</label>
    </div>
  </div>

  <div class="form-group" style="margin-top:24px;">
    <div class="col-sm-offset-3 col-sm-7">
      <button class="btn btn-primary" type="submit">Salvar</button>
      <button class="btn btn-default" type="submit" formaction="<?= $base ?>&action=contratada_cert_validate">Validar certificado</button>
      <a class="btn btn-default" href="<?= $base ?>&action=contratadas">Cancelar</a>
    </div>
  </div>
</form>

<script>
(function () {
  var form = document.querySelector('form[action*="action=contratada_save"]');
  var canvas = document.getElementById('mpCompanySigPad');
  var hidden = document.getElementById('mpCompanySigData');
  var clearBtn = document.getElementById('mpCompanySigClear');
  if (!form || !canvas || !hidden || !clearBtn) return;

  var ctx = canvas.getContext('2d');
  var dpr = window.devicePixelRatio || 1;
  var drawing = false;
  var drawn = false;
  var last = null;

  function setupCanvas() {
    var width = canvas.clientWidth || 620;
    var height = canvas.clientHeight || 180;
    canvas.width = Math.floor(width * dpr);
    canvas.height = Math.floor(height * dpr);
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    ctx.strokeStyle = '#222';
  }

  function point(e) {
    var r = canvas.getBoundingClientRect();
    var t = e.touches ? e.touches[0] : e;
    return { x: t.clientX - r.left, y: t.clientY - r.top };
  }

  function start(e) {
    drawing = true;
    last = point(e);
    e.preventDefault();
  }

  function move(e) {
    if (!drawing) return;
    var p = point(e);
    ctx.beginPath();
    ctx.moveTo(last.x, last.y);
    ctx.lineTo(p.x, p.y);
    ctx.stroke();
    last = p;
    drawn = true;
    e.preventDefault();
  }

  function end() {
    drawing = false;
    last = null;
  }

  setupCanvas();
  window.addEventListener('resize', setupCanvas);
  canvas.addEventListener('mousedown', start);
  canvas.addEventListener('mousemove', move);
  canvas.addEventListener('mouseup', end);
  canvas.addEventListener('mouseleave', end);
  canvas.addEventListener('touchstart', start);
  canvas.addEventListener('touchmove', move);
  canvas.addEventListener('touchend', end);

  clearBtn.addEventListener('click', function () {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    hidden.value = '';
    drawn = false;
  });

  form.addEventListener('submit', function () {
    if (drawn) {
      hidden.value = canvas.toDataURL('image/png');
    }
  });
})();
</script>
