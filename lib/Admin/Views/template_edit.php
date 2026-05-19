<?php
/** @var object|null $row */
/** @var string $base */
/** @var string $csrf */

$row = $row ?? null;
$id  = $row->id ?? 0;
$bodyHtml = $row->body_html ?? '<h2>CONTRATO Nº {{contrato.numero}}</h2>

<p><b>CONTRATADA:</b> {{contratada.razao_social}}, CNPJ {{contratada.cnpj}}, com sede em {{contratada.endereco_completo}}.</p>

<p><b>CONTRATANTE:</b> {{cliente.nome}}{{#cliente.empresa}} ({{cliente.empresa}}){{/cliente.empresa}}, inscrito(a) sob o CPF/CNPJ nº {{cliente.cpf_cnpj}}, residente em {{cliente.endereco_completo}}, e-mail {{cliente.email}}.</p>

<p>As partes acima qualificadas têm entre si justo e contratado o seguinte:</p>

<h3>1. OBJETO</h3>
<p>Prestação do serviço <b>{{servico.nome}}</b> ({{servico.dominio}}) no valor de <b>{{servico.valor_formatado}}</b> ({{servico.valor_extenso}}) em ciclo {{servico.ciclo}}.</p>

<h3>2. VIGÊNCIA</h3>
<p>O presente contrato vigorará a partir desta data e terá validade até {{contrato.validade}}.</p>

<p style="margin-top:40px;">{{contratada.cidade}}/{{contratada.uf}}, {{contrato.data_geracao_extenso}}.</p>';
?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
  <h2 style="margin:0;"><?= $id ? 'Editar Template' : 'Novo Template' ?></h2>
  <a class="btn btn-default" href="<?= $base ?>&action=templates">← Voltar</a>
</div>

<div class="row">
  <div class="col-md-8">
    <form method="post" action="<?= $base ?>&action=template_save">
      <?= $csrf ?>
      <input type="hidden" name="id" value="<?= (int) $id ?>">

      <div class="form-group">
        <label>Nome *</label>
        <input type="text" name="name" required class="form-control" value="<?= htmlspecialchars((string) ($row->name ?? ''), ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <div class="form-group">
        <label>Descrição</label>
        <input type="text" name="description" class="form-control" value="<?= htmlspecialchars((string) ($row->description ?? ''), ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <div class="form-group">
        <label>Corpo HTML *</label>
        <textarea name="body_html" required rows="22" style="font-family: ui-monospace, SFMono-Regular, Consolas, monospace; font-size: 13px;" class="form-control"><?= htmlspecialchars($bodyHtml, ENT_QUOTES, 'UTF-8') ?></textarea>
        <small class="text-muted">Aceita HTML. Use as variáveis listadas ao lado.</small>
      </div>
      <div class="form-group">
        <label><input type="checkbox" name="active" value="1" <?= $id === 0 || !empty($row->active) ? 'checked' : '' ?>> Ativo</label>
        <small class="text-muted" style="margin-left:10px;">Versão atual: v<?= (int) ($row->version ?? 0) ?> &middot; será incrementada se você alterar o corpo.</small>
      </div>
      <button class="btn btn-primary" type="submit">Salvar Template</button>
    </form>
  </div>
  <div class="col-md-4">
    <div class="panel panel-default">
      <div class="panel-heading"><b>Variáveis disponíveis</b></div>
      <div class="panel-body" style="font-size: 12px; max-height: 600px; overflow-y: auto;">
        <h5>Cliente</h5>
        <code>{{cliente.nome}}</code> &middot; <code>{{cliente.email}}</code> &middot;
        <code>{{cliente.cpf_cnpj}}</code> &middot; <code>{{cliente.empresa}}</code> &middot;
        <code>{{cliente.endereco_completo}}</code> &middot; <code>{{cliente.cidade}}</code> &middot;
        <code>{{cliente.uf}}</code> &middot; <code>{{cliente.telefone}}</code>
        <h5>Contratada</h5>
        <code>{{contratada.razao_social}}</code> &middot; <code>{{contratada.cnpj}}</code> &middot;
        <code>{{contratada.endereco_completo}}</code> &middot; <code>{{contratada.signatory_name}}</code> &middot;
        <code>{{contratada.assinatura_data_uri}}</code>
        <h5>Serviço</h5>
        <code>{{servico.nome}}</code> &middot; <code>{{servico.dominio}}</code> &middot;
        <code>{{servico.valor_formatado}}</code> &middot; <code>{{servico.valor_extenso}}</code> &middot;
        <code>{{servico.ciclo}}</code>
        <h5>Contrato</h5>
        <code>{{contrato.numero}}</code> &middot; <code>{{contrato.validade}}</code> &middot;
        <code>{{contrato.data_geracao_extenso}}</code>
        <h5>Data</h5>
        <code>{{data.hoje}}</code> &middot; <code>{{data.hoje_extenso}}</code> &middot;
        <code>{{data.ano}}</code>
        <h5>Custom Fields</h5>
        <code>{{custom.&lt;slug&gt;}}</code> — qualquer campo personalizado de cliente (slug do nome do campo).
        <h5>Filtros</h5>
        <code>|upper</code> &middot; <code>|lower</code> &middot; <code>|escape</code> &middot; <code>|trim</code>
        <h5 style="color:#888;">Legados (Modules Pay)</h5>
        <code>%NOME%</code> <code>%CPFCNPJ%</code> <code>%SERVICO%</code> <code>%VALOR%</code>
        <code>%CIDADE%</code> <code>%UF%</code> <code>%DATA%</code> <code>%NUMERO%</code>
        <code>[EMPRESA]</code> <code>[CNPJ_EMPRESA]</code> <code>[ENDERECO_EMPRESA]</code>
        <code>[SIGNATARIO]</code>
      </div>
    </div>
  </div>
</div>
