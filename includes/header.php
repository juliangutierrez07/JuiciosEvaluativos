<?php
require_once __DIR__ . '/functions.php';
// Cada página puede declarar $rolRequerido antes de incluir el encabezado.
$usuarioSesion = requireRol($rolRequerido ?? ROLES_PERSONAL);
$flash = getFlash();
$csrfToken = csrfToken();

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
<?php if ($usuarioSesion['rol'] === 'aprendiz'): ?>
    <li><a href="<?= base('modules/portal/index.php') ?>"<?= isActive('portal') ?>>
      <i class="bi bi-clipboard-check"></i> Mis juicios
    </a></li>
<?php else: ?>
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

    <li><a href="<?= base('modules/mapa/index.php') ?>"<?= isActive('mapa') ?>>
      <i class="bi bi-grid-3x3-gap-fill"></i> Mapa de calor
    </a></li>

<?php if ($usuarioSesion['rol'] === 'admin'): ?>
    <li class="nav-section-label">Administración</li>

    <li><a href="<?= base('modules/usuarios/index.php') ?>"<?= isActive('usuarios') ?>>
      <i class="bi bi-person-badge-fill"></i> Usuarios
    </a></li>
<?php endif; ?>

    <li><a href="<?= base('cambiar_clave.php') ?>"<?= isActiveRoot('cambiar_clave.php') ?>>
      <i class="bi bi-key-fill"></i> Cambiar contraseña
    </a></li>
<?php endif; ?>
  </ul>

  <div class="sidebar-footer">
    <div class="sidebar-user">
      <i class="bi bi-person-circle"></i>
      <div class="sidebar-user-text">
        <div class="sidebar-user-name"><?= e($usuarioSesion['nombre']) ?></div>
        <div class="sidebar-user-role"><?= e(nombreRol($usuarioSesion['rol'])) ?></div>
      </div>
    </div>
    <form method="POST" action="<?= base('logout.php') ?>">
      <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
      <button type="submit" class="je-btn je-btn-outline je-btn-sm w-100" style="justify-content:center">
        <i class="bi bi-box-arrow-right"></i> Cerrar sesión
      </button>
    </form>
  </div>
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
