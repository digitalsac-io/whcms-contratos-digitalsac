<style>
  .mp-sign-pad { border: 2px dashed #bbb; border-radius: 8px; background: #fafafa; touch-action: none; cursor: crosshair; width: 100%; max-width: 580px; height: 200px; }
  .mp-sign-actions { margin-top: 8px; }
  .mp-sign-actions button { margin-right: 6px; }
  .mp-contract-body { background: #fff; padding: 24px; border: 1px solid #ddd; border-radius: 6px; max-height: 500px; overflow-y: auto; }
</style>

<h2>Contrato {$contract->number|escape}
  <span class="label label-default" style="vertical-align: middle;">{$contract->status|escape}</span>
</h2>

{if isset($smarty.get.error)}
  <div class="alert alert-danger">{$smarty.get.error|escape}</div>
{/if}

<div class="row">
  <div class="col-md-8">
    <div class="mp-contract-body">{$contract->rendered_html nofilter}</div>
    <p style="margin-top:8px;">
      <a class="btn btn-default btn-sm" href="{$base}&action=pdf&id={$contract->id}" target="_blank">Visualizar PDF</a>
    </p>
  </div>

  <div class="col-md-4">
    {if $signature}
      <div class="panel panel-success">
        <div class="panel-heading"><b>✓ Contrato assinado</b></div>
        <div class="panel-body">
          <p><b>Data:</b> {$signature->signed_at|date_format:"%d/%m/%Y %H:%M"}</p>
          <p><b>Método:</b> {$signature->method}</p>
          <p><b>Hash de integridade:</b><br>
            <code style="font-size:10px;word-break:break-all;">{$signature->payload_hash|truncate:32:"…"}</code>
          </p>
          {if $signature->method eq 'canvas' && $signature->canvas_data}
            <img src="{$signature->canvas_data}" alt="Assinatura" style="max-height:60px;border:1px solid #ddd;background:#fff;">
          {/if}
        </div>
      </div>
    {elseif $canSign}
      <div class="panel panel-primary">
        <div class="panel-heading"><b>Assinar contrato</b></div>
        <div class="panel-body">
          <p>Selecione como deseja assinar:</p>

          <form method="post" action="{$base}&action=sign" id="mp-sign-form">
            <input type="hidden" name="mp_csrf" value="{$csrf}">
            <input type="hidden" name="id" value="{$contract->id}">
            <input type="hidden" name="method" id="mp-method" value="">
            <input type="hidden" name="canvas_data" id="mp-canvas-data" value="">

            {if $allowCanvas}
              <p><label><input type="radio" name="method_choice" value="canvas" checked> Assinatura manuscrita</label></p>
              <canvas id="mp-pad" class="mp-sign-pad" width="580" height="200"></canvas>
              <div class="mp-sign-actions">
                <button type="button" class="btn btn-default btn-xs" id="mp-clear">Limpar</button>
              </div>
            {/if}

            {if $allowCheckbox}
              <p style="margin-top:14px;"><label><input type="radio" name="method_choice" value="checkbox" {if !$allowCanvas}checked{/if}> Aceite eletrônico</label></p>
              <div id="mp-checkbox-block" style="display:{if !$allowCanvas}block{else}none{/if};padding:8px;background:#f8f9fa;border:1px solid #ddd;border-radius:4px;">
                <label><input type="checkbox" id="mp-accept" required>
                  Li, concordo e aceito todos os termos descritos neste contrato.
                </label>
              </div>
            {/if}

            {if !$allowCanvas && !$allowCheckbox}
              <div class="alert alert-danger">
                Nenhum método de assinatura está habilitado no módulo. Solicite ao administrador para configurar em Métodos de Assinatura.
              </div>
            {/if}

            <p style="margin-top:14px;color:#888;font-size:11px;">
              Ao assinar, registraremos seu IP, navegador e data/hora para fins de auditoria.
            </p>

            <button type="submit" class="btn btn-success btn-block" id="mp-submit">Assinar contrato</button>
          </form>
        </div>
      </div>
    {else}
      <div class="alert alert-warning">Este contrato não pode mais ser assinado (status: {$contract->status}).</div>
    {/if}
  </div>
</div>

<script>
(function () {
  var canvas = document.getElementById('mp-pad');
  var form = document.getElementById('mp-sign-form');
  var pad, drawing = false, last = null;
  if (canvas) {
    var ctx = canvas.getContext('2d');
    var dpr = window.devicePixelRatio || 1;
    canvas.width = canvas.offsetWidth * dpr;
    canvas.height = canvas.offsetHeight * dpr;
    ctx.scale(dpr, dpr);
    ctx.strokeStyle = '#222';
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    function pos(e) {
      var r = canvas.getBoundingClientRect();
      var t = e.touches ? e.touches[0] : e;
      return { x: t.clientX - r.left, y: t.clientY - r.top };
    }
    function start(e) { drawing = true; last = pos(e); e.preventDefault(); }
    function move(e) {
      if (!drawing) return;
      var p = pos(e);
      ctx.beginPath(); ctx.moveTo(last.x, last.y); ctx.lineTo(p.x, p.y); ctx.stroke();
      last = p; e.preventDefault();
    }
    function end() { drawing = false; last = null; }
    canvas.addEventListener('mousedown', start);
    canvas.addEventListener('mousemove', move);
    canvas.addEventListener('mouseup', end);
    canvas.addEventListener('mouseleave', end);
    canvas.addEventListener('touchstart', start);
    canvas.addEventListener('touchmove', move);
    canvas.addEventListener('touchend', end);
    var clearBtn = document.getElementById('mp-clear');
    if (clearBtn) {
      clearBtn.onclick = function () {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
      };
    }
  }
  if (!form) return;
  // Toggle method UI
  document.querySelectorAll('input[name="method_choice"]').forEach(function (radio) {
    radio.addEventListener('change', function () {
      var cb = document.getElementById('mp-checkbox-block');
      if (cb) cb.style.display = (radio.value === 'checkbox' && radio.checked) ? 'block' : 'none';
      if (canvas) canvas.style.opacity = (radio.value === 'canvas' && radio.checked) ? '1' : '0.4';
    });
  });
  form.addEventListener('submit', function (e) {
    var choice = document.querySelector('input[name="method_choice"]:checked');
    if (!choice) { e.preventDefault(); alert('Selecione um método de assinatura.'); return; }
    var method = choice.value;
    document.getElementById('mp-method').value = method;
    if (method === 'canvas') {
      if (!canvas) { e.preventDefault(); return; }
      // Detect empty canvas
      var blank = document.createElement('canvas');
      blank.width = canvas.width; blank.height = canvas.height;
      if (canvas.toDataURL() === blank.toDataURL()) {
        e.preventDefault(); alert('Por favor, assine no quadro antes de continuar.'); return;
      }
      document.getElementById('mp-canvas-data').value = canvas.toDataURL('image/png');
    } else if (method === 'checkbox') {
      var cb = document.getElementById('mp-accept');
      if (!cb || !cb.checked) { e.preventDefault(); alert('Você precisa marcar o aceite para continuar.'); return; }
    }
  });
})();
</script>
