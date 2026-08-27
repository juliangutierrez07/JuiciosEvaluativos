<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/functions.php';
$flash = getFlash();

$currentFile = basename($_SERVER['PHP_SELF']);
$currentDir  = basename(dirname($_SERVER['PHP_SELF']));
function isActive(string $dir, string $file = ''): string {
    global $currentDir, $currentFile;
    if ($file && $currentFile === $file && $currentDir === $dir) return ' active';
    if (!$file && $currentDir === $dir) return ' active';
    return '';
}
function isActiveRoot(string $file): string {
    global $currentDir, $currentFile;
    return ($currentFile === $file && ($currentDir === 'JuiciosEvaluativos' || $currentDir === 'htdocs')) ? ' active' : '';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Plataforma de seguimiento academico">
  <title><?= isset($pageTitle) ? e($pageTitle) . ' - ' : '' ?>EvalTrack</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">
  <link rel="stylesheet" href="<?= base('assets/css/style.css') ?>">
</head>
<body>

<div id="sidebar-overlay"></div>

<nav id="sidebar">
  <a class="sidebar-brand" href="<?= base('index.php') ?>">
    <div class="brand-icon"><i class="bi bi-mortarboard-fill" style="color:#fff"></i></div>
    <div class="brand-text">
      <div class="brand-title">EvalTrack</div>
      <div class="brand-sub">Control academico</div>
    </div>
  </a>

  <ul class="sidebar-nav">
    <li><a href="<?= base('index.php') ?>"<?= isActiveRoot('index.php') ?>>
      <i class="bi bi-grid-1x2-fill"></i> Dashboard
    </a></li>

    <li class="nav-section-label">Gestion</li>

    <li><a href="<?= base('modules/importar/index.php') ?>"<?= isActive('importar') ?>>
      <i class="bi bi-cloud-upload-fill"></i> Importar Excel
    </a></li>

    <li><a href="<?= base('modules/fichas/index.php') ?>"<?= isActive('fichas') ?>>
      <i class="bi bi-journal-text"></i> Buscar Ficha
    </a></li>

    <li><a href="<?= base('modules/aprendices/index.php') ?>"<?= isActive('aprendices') ?>>
      <i class="bi bi-people-fill"></i> Aprendices
    </a></li>

    <li><a href="<?= base('modules/reportes/consolidado.php') ?>"<?= isActive('reportes') ?>>
      <i class="bi bi-table"></i> Consolidado
    </a></li>
  </ul>

  <div class="sidebar-footer"></div>
</nav>

<div id="main-wrapper">
  <header id="topbar">
    <button id="sidebar-toggle" style="background:none;border:none;color:var(--text-muted);font-size:1.3rem;cursor:pointer;display:none">
      <i class="bi bi-list"></i>
    </button>
    <span class="topbar-title"><?= isset($pageTitle) ? e($pageTitle) : 'Inicio' ?></span>
    <span class="topbar-spacer"></span>
  </header>

  <main id="page-content">

<?php if ($flash): ?>
<div class="je-alert je-alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> fade-up" role="alert">
  <i class="bi bi-<?= $flash['type'] === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill' ?> alert-icon"></i>
  <div><?= $flash['message'] ?></div>
  <button class="je-alert-close" onclick="this.closest('.je-alert').remove()">&times;</button>
</div>
<?php endif; ?>
