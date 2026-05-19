<p class="text-muted">Total: {$rowsTotal} contrato(s)</p>

{if isset($error)}
  <div class="alert alert-warning">{$error|escape}</div>
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
      <tr>
        <td colspan="6" class="text-center text-muted">Você ainda não tem contratos.</td>
      </tr>
    {/if}
    {foreach from=$rows item=r}
      <tr>
        <td><strong>{$r.number|escape}</strong></td>
        <td><span class="label label-default">{$r.status_label|escape}</span></td>
        <td>{$r.created_at_display|escape}</td>
        <td>{$r.expires_at_display|escape}</td>
        <td>{$r.signed_at_display|escape}</td>
        <td><a class="btn btn-sm btn-primary" href="{$base}&action=view&id={$r.id}">Abrir</a></td>
      </tr>
    {/foreach}
  </tbody>
</table>
