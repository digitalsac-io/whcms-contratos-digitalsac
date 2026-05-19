<?php
/** @var array $flashes */
/** @var string $modulelink */
/** @var string $view */
?>
<style>
  .mpc-flash { padding:10px 14px; border-radius:4px; margin-bottom:12px; }
  .mpc-flash-success { background:#dff0d8; color:#3c763d; border:1px solid #d6e9c6; }
  .mpc-flash-error { background:#f2dede; color:#a94442; border:1px solid #ebccd1; }
  .mpc-stats { display:flex; gap:14px; margin-bottom:20px; flex-wrap:wrap; }
  .mpc-stat { background:#fff; border:1px solid #e3e3e3; border-radius:6px; padding:16px 20px; flex:1; min-width:140px; }
  .mpc-stat-label { font-size:11px; color:#888; text-transform:uppercase; letter-spacing:.04em; }
  .mpc-stat-value { font-size:24px; font-weight:600; color:#333; margin-top:6px; }
  .mpc-actions { margin-bottom:12px; }
  .mpc-actions .btn { margin-right:6px; }
  .mpc-table th { background:#f5f5f5; }
  .mpc-doc-preview { background:#fff; border:1px solid #ddd; padding:24px; max-height:500px; overflow:auto; }
  .mpc-var-helper { background:#f9f9f9; border:1px solid #e3e3e3; padding:10px 12px; border-radius:4px; font-size:12px; }
  .mpc-var-helper code { background:#fff; padding:1px 5px; border-radius:3px; border:1px solid #ddd; }
  .mpc-badge { display:inline-block; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:500; }
  .mpc-badge-pending { background:#fcf8e3; color:#8a6d3b; }
  .mpc-badge-sent { background:#d9edf7; color:#31708f; }
  .mpc-badge-signed { background:#dff0d8; color:#3c763d; }
  .mpc-badge-expired { background:#f5f5f5; color:#666; }
  .mpc-badge-cancelled { background:#f2dede; color:#a94442; }
</style>

<?php foreach (($flashes ?? []) as $f): ?>
  <div class="mpc-flash mpc-flash-<?= htmlspecialchars($f['type']) ?>">
    <?= htmlspecialchars($f['message']) ?>
  </div>
<?php endforeach ?>

<?php
$viewFile = __DIR__ . '/' . basename($view ?? 'dashboard') . '.php';
if (is_file($viewFile)) {
    include $viewFile;
} else {
    include __DIR__ . '/dashboard.php';
}
