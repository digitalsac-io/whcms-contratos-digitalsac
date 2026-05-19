<?php
/** @var array $templates */
/** @var array $contratadasL */
/** @var array $clients */
/** @var array $services */
/** @var string $base */
/** @var string $csrf */
?>
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
  <h2 style="margin:0;">Novo Contrato</h2>
  <a class="btn btn-default" href="<?= $base ?>&action=contracts">← Voltar</a>
</div>

<form method="post" action="<?= $base ?>&action=contract_create" class="form-horizontal">
  <?= $csrf ?>

  <div class="form-group">
    <label class="col-sm-3 control-label">Cliente (ID WHMCS) *</label>
    <div class="col-sm-7">
      <select name="client_id" required class="form-control">
        <option value="">— Selecione o cliente —</option>
        <?php foreach (($clients ?? []) as $cl): ?>
          <?php
            $fullName = trim((string) ($cl->firstname ?? '') . ' ' . (string) ($cl->lastname ?? ''));
            $company = trim((string) ($cl->companyname ?? ''));
            $label = '#' . (int) $cl->id . ' - ' . ($fullName !== '' ? $fullName : '(Sem nome)');
            if ($company !== '') {
                $label .= ' (' . $company . ')';
            }
            if (!empty($cl->email)) {
                $label .= ' - ' . (string) $cl->email;
            }
          ?>
          <option value="<?= (int) $cl->id ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
        <?php endforeach; ?>
      </select>
      <small class="text-muted">Lista com até 2000 clientes ordenados por nome. Se não encontrar, busque pelo cliente no WHMCS e informe o ID manualmente.</small>
    </div>
  </div>

  <div class="form-group">
    <label class="col-sm-3 control-label">Template *</label>
    <div class="col-sm-7">
      <select name="template_id" required class="form-control">
        <option value="">— Selecione —</option>
        <?php foreach ($templates as $t): ?>
          <option value="<?= (int) $t->id ?>"><?= htmlspecialchars($t->name, ENT_QUOTES, 'UTF-8') ?> (v<?= (int) $t->version ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <div class="form-group">
    <label class="col-sm-3 control-label">Serviço (opcional)</label>
    <div class="col-sm-7">
      <select name="service_id" id="mp-service-select" class="form-control">
        <option value="">— Sem serviço —</option>
        <?php foreach (($services ?? []) as $svc): ?>
          <?php
            $domain = trim((string) ($svc->domain ?? ''));
            $product = trim((string) ($svc->product_name ?? ''));
            $status = trim((string) ($svc->domainstatus ?? ''));
            $label = '#' . (int) $svc->id . ' - ';
            $label .= ($product !== '' ? $product : 'Serviço');
            if ($domain !== '') {
                $label .= ' - ' . $domain;
            }
            if ($status !== '') {
                $label .= ' [' . $status . ']';
            }
          ?>
          <option value="<?= (int) $svc->id ?>" data-client-id="<?= (int) ($svc->userid ?? 0) ?>">
            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
          </option>
        <?php endforeach; ?>
      </select>
      <small class="text-muted">A lista é filtrada automaticamente pelo cliente selecionado.</small>
    </div>
  </div>

  <div class="form-group">
    <label class="col-sm-3 control-label">Contratada</label>
    <div class="col-sm-7">
      <select name="contratada_id" class="form-control">
        <option value="">— Resolver automaticamente —</option>
        <?php foreach ($contratadasL as $c): ?>
          <option value="<?= (int) $c->id ?>"><?= htmlspecialchars($c->razao_social, ENT_QUOTES, 'UTF-8') ?> (<?= $c->is_default ? 'padrão' : 'opcional' ?>)</option>
        <?php endforeach; ?>
      </select>
      <small class="text-muted">Em branco: usa mapeamento do produto → padrão.</small>
    </div>
  </div>

  <div class="form-group">
    <label class="col-sm-3 control-label">Validade (dias)</label>
    <div class="col-sm-3">
      <input type="number" name="validity_days" class="form-control" value="365" min="1" max="3650">
    </div>
  </div>

  <div class="form-group">
    <div class="col-sm-offset-3 col-sm-7">
      <button class="btn btn-primary" type="submit">Gerar Contrato</button>
      <a class="btn btn-default" href="<?= $base ?>&action=contracts">Cancelar</a>
    </div>
  </div>
</form>

<script>
(function () {
  var clientSelect = document.querySelector('select[name="client_id"]');
  var serviceSelect = document.getElementById('mp-service-select');
  if (!clientSelect || !serviceSelect) {
    return;
  }

  function filterServices() {
    var clientId = clientSelect.value || '';
    var selectedStillVisible = false;

    for (var i = 0; i < serviceSelect.options.length; i++) {
      var option = serviceSelect.options[i];
      if (option.value === '') {
        option.hidden = false;
        continue;
      }
      var optionClientId = option.getAttribute('data-client-id') || '';
      var visible = clientId === '' || optionClientId === clientId;
      option.hidden = !visible;
      if (visible && option.selected) {
        selectedStillVisible = true;
      }
    }

    if (!selectedStillVisible && serviceSelect.value !== '') {
      serviceSelect.value = '';
    }
  }

  clientSelect.addEventListener('change', filterServices);
  filterServices();
})();
</script>
