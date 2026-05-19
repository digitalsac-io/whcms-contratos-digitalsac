<?php

declare(strict_types=1);

/**
 * MP Contratos - Public Signature Endpoint
 *
 * Tokenized URL used by WhatsApp / e-mail to let a client sign a contract
 * without going through the WHMCS login.
 *
 * URL: /modules/addons/mpcontratos/public/sign.php?t={token}
 */

// Bootstrap WHMCS so we can use the full DB, Capsule, etc.
$initPath = realpath(__DIR__ . '/../../../../init.php');
if ($initPath && is_file($initPath)) {
    require_once $initPath;
} else {
    http_response_code(500);
    exit('WHMCS init.php não encontrado.');
}

require_once __DIR__ . '/../lib/Bootstrap.php';

use DigitalSac\MpContratos\Bootstrap;
use DigitalSac\MpContratos\Contract\ContratadaRepository;
use DigitalSac\MpContratos\Contract\PublicLinkService;
use DigitalSac\MpContratos\Contract\SignatureService;
use DigitalSac\MpContratos\Support\Csrf;
use DigitalSac\MpContratos\Support\Logger;

Bootstrap::init();

$links      = new PublicLinkService();
$signatures = new SignatureService();
$contratadas = new ContratadaRepository();

$token = $_GET['t'] ?? $_POST['t'] ?? '';
if (!is_string($token) || strlen($token) !== 48 || !ctype_xdigit($token)) {
    renderError('Link inválido.', 'O link fornecido está malformado.');
    exit;
}

try {
    $contract = $links->resolve($token);
} catch (\Throwable $e) {
    renderError('Link indisponível', $e->getMessage());
    exit;
}

$contratada = $contract->contratada_id ? $contratadas->find((int) $contract->contratada_id) : null;
$alreadySigned = !empty($contract->signed_at);

// Handle POST (sign action)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$alreadySigned) {
    try {
        Csrf::assertValidPost();
        $method = (string) ($_POST['method'] ?? '');
        $canvas = $method === 'canvas' ? (string) ($_POST['canvas_data'] ?? '') : null;
        $signatures->sign((int) $contract->id, $method, $canvas, $_SERVER['HTTP_USER_AGENT'] ?? '');
        $links->consume($token, Logger::clientIp());
        renderSuccess($contract, $contratada);
        exit;
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }
}

renderSignPage($contract, $contratada, $token, $alreadySigned, $error ?? null);

// ---------------------------------------------------------------------------
function renderSignPage(object $c, ?object $ct, string $token, bool $alreadySigned, ?string $error = null): void
{
    $methods = array_filter(array_map('trim',
        explode(',', (string) \DigitalSac\MpContratos\Bootstrap::setting('signature_methods', 'canvas,checkbox'))));
    $companyName = $ct ? htmlspecialchars((string) $ct->razao_social, ENT_QUOTES, 'UTF-8') : 'DigitalSac Software Engineering';
    $number = htmlspecialchars($c->number, ENT_QUOTES, 'UTF-8');
    $csrf   = Csrf::token();
    $body   = $c->rendered_html;
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Assinatura de Contrato <?= $number ?></title>
  <link rel="stylesheet" href="<?= htmlspecialchars(\DigitalSac\MpContratos\Bootstrap::assetUrl('assets/css/contracts.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
<header class="mp-header">
  <div class="mp-container">
    <h1>Assinatura de Contrato</h1>
    <p><?= $companyName ?> · Contrato <b><?= $number ?></b></p>
  </div>
</header>

<main class="mp-container mp-main">
  <?php if ($error): ?>
    <div class="mp-alert mp-alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <?php if ($alreadySigned): ?>
    <div class="mp-alert mp-alert-success">
      Este contrato já foi assinado em <b><?= htmlspecialchars(date('d/m/Y H:i', strtotime((string) $c->signed_at)), ENT_QUOTES, 'UTF-8') ?></b>.
    </div>
  <?php endif; ?>

  <section class="mp-card">
    <div class="mp-card-head">Conteúdo do Contrato</div>
    <div class="mp-card-body mp-contract"><?= $body ?></div>
  </section>

  <?php if (!$alreadySigned): ?>
  <section class="mp-card">
    <div class="mp-card-head">Assinar Eletronicamente</div>
    <div class="mp-card-body">
      <form method="post" id="mp-sign-form">
        <input type="hidden" name="t" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="mp_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="method" id="mp-method" value="">
        <input type="hidden" name="canvas_data" id="mp-canvas-data" value="">

        <?php if (in_array('canvas', $methods, true)): ?>
        <div class="mp-method">
          <label><input type="radio" name="method_choice" value="canvas" checked> <b>Assinatura manuscrita</b></label>
          <p class="mp-help">Use o dedo (toque) ou o mouse para assinar no quadro abaixo:</p>
          <canvas id="mp-pad" class="mp-sign-pad" width="600" height="200"></canvas>
          <div class="mp-pad-actions">
            <button type="button" id="mp-clear" class="mp-btn mp-btn-ghost">Limpar</button>
          </div>
        </div>
        <?php endif; ?>

        <?php if (in_array('checkbox', $methods, true)): ?>
        <div class="mp-method">
          <label><input type="radio" name="method_choice" value="checkbox"> <b>Aceite eletrônico</b></label>
          <div id="mp-checkbox-block" class="mp-checkbox-block">
            <label>
              <input type="checkbox" id="mp-accept">
              Declaro que li, compreendi e aceito todos os termos descritos neste contrato.
            </label>
          </div>
        </div>
        <?php endif; ?>

        <p class="mp-fineprint">
          Ao assinar, registraremos seu IP, navegador, data e hora para fins de auditoria
          (MP 2.200-2/2001).
        </p>

        <button type="submit" class="mp-btn mp-btn-primary mp-btn-block">Confirmar Assinatura</button>
      </form>
    </div>
  </section>
  <?php endif; ?>
</main>

<footer class="mp-footer">
  <div class="mp-container">
    <small>DigitalSac Contratos · Documento eletrônico com validade jurídica</small>
  </div>
</footer>

<script src="<?= htmlspecialchars(\DigitalSac\MpContratos\Bootstrap::assetUrl('assets/js/signature-pad.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
    <?php
}

function renderSuccess(object $c, ?object $ct): void
{
    $companyName = $ct ? htmlspecialchars($ct->razao_social, ENT_QUOTES, 'UTF-8') : 'DigitalSac Software Engineering';
    $number = htmlspecialchars($c->number, ENT_QUOTES, 'UTF-8');
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Contrato Assinado</title>
  <link rel="stylesheet" href="<?= htmlspecialchars(\DigitalSac\MpContratos\Bootstrap::assetUrl('assets/css/contracts.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
<header class="mp-header">
  <div class="mp-container"><h1>Contrato Assinado</h1></div>
</header>
<main class="mp-container mp-main">
  <div class="mp-card mp-success">
    <div class="mp-card-body" style="text-align:center;padding:40px;">
      <div style="font-size:64px;line-height:1;">✓</div>
      <h2>Obrigado!</h2>
      <p>O contrato <b><?= $number ?></b> da <?= $companyName ?> foi assinado eletronicamente com sucesso.</p>
      <p>Você receberá uma cópia em seu e-mail em alguns minutos.</p>
    </div>
  </div>
</main>
</body>
</html>
    <?php
}

function renderError(string $title, string $detail): void
{
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="UTF-8"><title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
<link rel="stylesheet" href="<?= htmlspecialchars(\DigitalSac\MpContratos\Bootstrap::assetUrl('assets/css/contracts.css'), ENT_QUOTES, 'UTF-8') ?>"></head>
<body>
<header class="mp-header"><div class="mp-container"><h1><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1></div></header>
<main class="mp-container mp-main">
  <div class="mp-alert mp-alert-danger"><?= htmlspecialchars($detail, ENT_QUOTES, 'UTF-8') ?></div>
  <p>Entre em contato com a empresa que enviou este link.</p>
</main>
</body>
</html>
    <?php
}
