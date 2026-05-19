<?php
/** @var array $customFields */
/** @var int $cpfCnpjFieldId */
/** @var string $modulelink */
?>
<h2>Configurações</h2>
<p class="text-muted">
  A maior parte das configurações deste módulo é gerenciada na tela <strong>Addon Modules → DigitalSac Contratos → Configure</strong>
  do WHMCS. Esta tela serve para validar o que está aplicado e oferecer atalhos para as áreas relacionadas.
</p>

<table class="table mpc-table">
  <tr>
    <td style="width:240px"><strong>Campo CPF/CNPJ (custom field)</strong></td>
    <td>
      <?php
        $name = '';
        foreach ($customFields as $f) {
          if ((int) $f->id === $cpfCnpjFieldId) { $name = $f->fieldname; break; }
        }
        echo $cpfCnpjFieldId
          ? '#' . $cpfCnpjFieldId . ' — ' . htmlspecialchars($name)
          : '<em class="text-muted">não configurado (será detectado por heurística)</em>';
      ?>
    </td>
  </tr>
  <tr>
    <td><strong>Driver WhatsApp</strong></td>
    <td><code><?= htmlspecialchars((string) \DigitalSac\MpContratos\Bootstrap::setting('whatsapp_driver', 'disabled')) ?></code></td>
  </tr>
  <tr>
    <td><strong>Endpoint WhatsApp</strong></td>
    <td><code><?= htmlspecialchars((string) \DigitalSac\MpContratos\Bootstrap::setting('whatsapp_endpoint', '')) ?: '<em class="text-muted">não definido</em>' ?></code></td>
  </tr>
  <tr>
    <td><strong>Auto-gerar em InvoiceCreated</strong></td>
    <td><?= (int) \DigitalSac\MpContratos\Bootstrap::setting('auto_generate_on_invoice', 0) ? 'Sim' : 'Não' ?></td>
  </tr>
  <tr>
    <td><strong>Prefixo do número</strong></td>
    <td><code><?= htmlspecialchars((string) \DigitalSac\MpContratos\Bootstrap::setting('contract_prefix', 'CTR')) ?></code></td>
  </tr>
  <tr>
    <td><strong>TTL de link público (horas)</strong></td>
    <td><?= (int) \DigitalSac\MpContratos\Bootstrap::setting('public_link_ttl', 72) ?></td>
  </tr>
</table>

<div style="margin-top:18px" class="panel panel-info">
  <div class="panel-heading">Recursos relacionados</div>
  <div class="panel-body">
    <p>→ <a href="<?= $modulelink ?>&section=contratadas">Gerenciar Contratadas</a> (empresas emissoras)</p>
    <p>→ <a href="<?= $modulelink ?>&section=field_maps">Mapear Custom Fields para variáveis</a></p>
    <p>→ <a href="<?= $modulelink ?>&section=product_mappings">Mapear Produtos × Templates</a></p>
    <p>→ <a href="configaddonmods.php">Configurações WHMCS do módulo</a> (CNPJ, driver, etc.)</p>
  </div>
</div>
