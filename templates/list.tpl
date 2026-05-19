<h2>Meus Contratos</h2>
<p class="text-muted" style="margin-top:-6px;">Total: {$rowsTotal|default:0} contrato(s)</p>

{if isset($error)}
  <div class="alert alert-warning">{$error}</div>
{/if}

{if isset($smarty.get.signed) && $smarty.get.signed eq '1'}
  <div class="alert alert-success">✓ Contrato assinado com sucesso. Obrigado!</div>
{/if}

<table class="table table-striped">
  <thead>
    <tr>
      <th>Número</th>
      <th>Status</th>
      <th>Criado em</th>
      <th>Validade</th>
      <th>Assinado em</th>
      <th></th>
    </tr>
  </thead>
  <tbody>
    {if !$rows}
      <tr><td colspan="6" class="text-center text-muted">Você ainda não tem contratos.</td></tr>
    {/if}
    {foreach from=$rows item=r}
      <tr>
        <td><b>{$r.number|escape}</b></td>
        <td>
          <span class="label label-default">{$r.status_label|escape}</span>
        </td>
        <td>{$r.created_at_display|escape}</td>
        <td>{$r.expires_at_display|escape}</td>
        <td>{$r.signed_at_display|escape}</td>
        <td>
          <a class="btn btn-sm btn-primary" href="{$base}&action=view&id={$r.id}">Abrir</a>
        </td>
      </tr>
    {/foreach}
  </tbody>
</table>
