/**
 * MP Contratos — Signature Pad
 *
 * Vanilla canvas implementation: mouse + touch, high-DPI aware.
 */
(function () {
  'use strict';

  var canvas = document.getElementById('mp-pad');
  if (!canvas) {
    bindSubmitHandler(null);
    bindRadioToggle(null);
    return;
  }

  var ctx = canvas.getContext('2d');
  var dpr = window.devicePixelRatio || 1;
  var rect = canvas.getBoundingClientRect();

  // High-DPI sizing
  canvas.width  = rect.width  * dpr;
  canvas.height = rect.height * dpr;
  ctx.scale(dpr, dpr);
  ctx.strokeStyle = '#222';
  ctx.lineWidth = 2;
  ctx.lineCap = 'round';
  ctx.lineJoin = 'round';

  var drawing = false;
  var lastPos = null;

  function getPos(e) {
    var r = canvas.getBoundingClientRect();
    var t = e.touches ? e.touches[0] : e;
    return { x: t.clientX - r.left, y: t.clientY - r.top };
  }
  function start(e) { drawing = true; lastPos = getPos(e); e.preventDefault && e.preventDefault(); }
  function move(e) {
    if (!drawing) return;
    var p = getPos(e);
    ctx.beginPath();
    ctx.moveTo(lastPos.x, lastPos.y);
    ctx.lineTo(p.x, p.y);
    ctx.stroke();
    lastPos = p;
    e.preventDefault && e.preventDefault();
  }
  function end() { drawing = false; lastPos = null; }

  canvas.addEventListener('mousedown', start);
  canvas.addEventListener('mousemove', move);
  canvas.addEventListener('mouseup',   end);
  canvas.addEventListener('mouseleave', end);
  canvas.addEventListener('touchstart', start, { passive: false });
  canvas.addEventListener('touchmove',  move,  { passive: false });
  canvas.addEventListener('touchend',   end);

  var clearBtn = document.getElementById('mp-clear');
  if (clearBtn) {
    clearBtn.onclick = function () { ctx.clearRect(0, 0, canvas.width, canvas.height); };
  }

  function isCanvasEmpty() {
    var blank = document.createElement('canvas');
    blank.width = canvas.width; blank.height = canvas.height;
    return canvas.toDataURL() === blank.toDataURL();
  }

  bindRadioToggle(canvas);
  bindSubmitHandler({ canvas: canvas, isEmpty: isCanvasEmpty });

  // -------------------------------------------------------------------------

  function bindRadioToggle(canvasEl) {
    var radios = document.querySelectorAll('input[name="method_choice"]');
    radios.forEach(function (radio) {
      radio.addEventListener('change', updateUI);
    });
    updateUI();

    function updateUI() {
      var checked = document.querySelector('input[name="method_choice"]:checked');
      var cb = document.getElementById('mp-checkbox-block');
      var showCheckbox = checked && checked.value === 'checkbox';
      if (cb) cb.style.display = showCheckbox ? 'block' : 'none';
      if (canvasEl) canvasEl.style.opacity = (checked && checked.value === 'canvas') ? '1' : '0.4';
    }
  }

  function bindSubmitHandler(ctxObj) {
    var form = document.getElementById('mp-sign-form');
    if (!form) return;
    form.addEventListener('submit', function (e) {
      var choice = document.querySelector('input[name="method_choice"]:checked');
      if (!choice) { e.preventDefault(); alert('Selecione um método de assinatura.'); return; }
      var method = choice.value;
      document.getElementById('mp-method').value = method;

      if (method === 'canvas') {
        if (!ctxObj || !ctxObj.canvas) { e.preventDefault(); alert('Canvas indisponível.'); return; }
        if (ctxObj.isEmpty()) { e.preventDefault(); alert('Por favor, assine no quadro antes de continuar.'); return; }
        document.getElementById('mp-canvas-data').value = ctxObj.canvas.toDataURL('image/png');
      } else if (method === 'checkbox') {
        var cb = document.getElementById('mp-accept');
        if (!cb || !cb.checked) { e.preventDefault(); alert('Marque o aceite eletrônico para continuar.'); return; }
      }
    });
  }
})();
