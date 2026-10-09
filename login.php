<?php
require_once __DIR__ . '/includes/functions.php';

if ($usuario = usuarioActual()) {
    redirect(!empty($usuario['debe_cambiar']) ? base('cambiar_clave.php') : paginaInicio($usuario['rol']));
}

// Consultas de documento fallidas permitidas por IP antes de bloquear.
const MAX_CONSULTAS_FALLIDAS = 10;

$modo  = ($_POST['modo'] ?? $_GET['modo'] ?? '') === 'personal' ? 'personal' : 'aprendiz';
$error = '';
$documento = '';
$usuarioIngresado = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValido($_POST['csrf_token'] ?? null)) {
        $error = 'La sesión del formulario expiró. Inténtalo nuevamente.';
    } elseif ($modo === 'aprendiz') {
        $documento = preg_replace('/\D/', '', (string)($_POST['documento'] ?? ''));
        $claveIp   = 'ip:' . ipCliente();
        $s = getDB()->prepare(
            'SELECT COUNT(*) FROM intentos_login WHERE identificador = ? AND created_at > NOW() - INTERVAL ' . VENTANA_INTENTOS_MIN . ' MINUTE'
        );
        $s->execute([$claveIp]);

        if ($documento === '') {
            $error = 'Ingresa tu número de documento.';
        } elseif ((int)$s->fetchColumn() >= MAX_CONSULTAS_FALLIDAS) {
            $error = 'Demasiadas consultas con documentos no registrados. Espera ' . VENTANA_INTENTOS_MIN . ' minutos e inténtalo de nuevo.';
        } elseif ($aprendiz = buscarAprendizPorDocumento($documento)) {
            iniciarSesionComo('aprendiz', $aprendiz['numero_documento'], $aprendiz['nombre']);
            redirect(paginaInicio('aprendiz'));
        } else {
            registrarIntentoFallido($claveIp);
            $error = 'No encontramos juicios registrados para ese número de documento.';
        }
    } else {
        $usuarioIngresado = trim((string)($_POST['usuario'] ?? ''));
        $password         = (string)($_POST['password'] ?? '');

        if ($usuarioIngresado === '' || $password === '') {
            $error = 'Ingresa tu usuario y tu contraseña.';
        } elseif (loginBloqueado($usuarioIngresado)) {
            $error = 'Demasiados intentos fallidos. Espera ' . VENTANA_INTENTOS_MIN . ' minutos e inténtalo de nuevo.';
        } elseif ($cuenta = autenticar($usuarioIngresado, $password)) {
            limpiarIntentos($usuarioIngresado);
            iniciarSesionComo($cuenta['rol'], $cuenta['id'], $cuenta['nombre'], $cuenta['debe_cambiar']);
            redirect($cuenta['debe_cambiar'] ? base('cambiar_clave.php') : paginaInicio($cuenta['rol']));
        } else {
            registrarIntentoFallido($usuarioIngresado);
            $error = 'Usuario o contraseña incorrectos.';
        }
    }
}

$flash = getFlash();
$csrfToken = csrfToken();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Iniciar sesión - EvalTrack</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= base('assets/css/style.css') ?>">
</head>
<body>
<main class="auth-page">
  <div class="je-card auth-card fade-up">
    <div class="card-body">
      <div class="auth-brand">
        <div class="brand-icon"><i class="bi bi-mortarboard-fill"></i></div>
        <div>
          <h1>EvalTrack</h1>
          <p>Consulta de juicios evaluativos</p>
        </div>
      </div>

      <nav class="auth-tabs" aria-label="Tipo de ingreso">
        <a href="?modo=aprendiz" class="<?= $modo === 'aprendiz' ? 'active' : '' ?>"><i class="bi bi-person-fill"></i> Aprendiz</a>
        <a href="?modo=personal" class="<?= $modo === 'personal' ? 'active' : '' ?>"><i class="bi bi-person-badge-fill"></i> Instructor / Admin</a>
      </nav>

      <?php if ($flash): ?>
        <div class="je-alert je-alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> mb-3" role="alert">
          <div><?= $flash['message'] ?></div>
        </div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="je-alert je-alert-danger mb-3" role="alert">
          <i class="bi bi-exclamation-triangle-fill alert-icon"></i>
          <div><?= e($error) ?></div>
        </div>
      <?php endif; ?>

      <?php if ($modo === 'aprendiz'): ?>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="modo" value="aprendiz">
        <div class="mb-4">
          <label class="je-label" for="documento">Número de documento</label>
          <input class="je-input" type="text" id="documento" name="documento" value="<?= e($documento) ?>"
                 inputmode="numeric" pattern="[0-9 .]*" maxlength="20" autocomplete="off" required autofocus>
        </div>
        <button type="submit" class="je-btn je-btn-primary w-100" style="justify-content:center;padding:12px">
          <i class="bi bi-search"></i> Ver mis juicios
        </button>
      </form>
      <p class="auth-hint">
        <i class="bi bi-info-circle me-1"></i>
        Escribe tu número de documento, sin puntos ni espacios, para consultar tus juicios aprobados y pendientes.
      </p>
      <?php else: ?>
      <form method="POST" autocomplete="on">
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="modo" value="personal">
        <div class="mb-3">
          <label class="je-label" for="usuario">Usuario</label>
          <input class="je-input" type="text" id="usuario" name="usuario"
                 value="<?= e($usuarioIngresado) ?>" autocomplete="username" required autofocus>
        </div>
        <div class="mb-4">
          <label class="je-label" for="password">Contraseña</label>
          <input class="je-input" type="password" id="password" name="password"
                 autocomplete="current-password" required>
        </div>
        <button type="submit" class="je-btn je-btn-primary w-100" style="justify-content:center;padding:12px">
          <i class="bi bi-box-arrow-in-right"></i> Ingresar
        </button>
      </form>
      <?php endif; ?>
    </div>
  </div>
</main>
</body>
</html>
