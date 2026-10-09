<?php
require_once __DIR__ . '/includes/functions.php';

$usuario = usuarioActual();
if (!$usuario) redirect(base('login.php'));
// Los aprendices ingresan solo con su documento y no tienen contraseña.
if ($usuario['rol'] === 'aprendiz') redirect(paginaInicio('aprendiz'));

$obligatorio = !empty($usuario['debe_cambiar']);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $actual       = (string)($_POST['actual'] ?? '');
    $nueva        = (string)($_POST['nueva'] ?? '');
    $confirmacion = (string)($_POST['confirmacion'] ?? '');
    $db = getDB();

    if (!csrfValido($_POST['csrf_token'] ?? null)) {
        $error = 'La sesión del formulario expiró. Inténtalo nuevamente.';
    } elseif (!$obligatorio && !autenticarActual($usuario, $actual)) {
        $error = 'La contraseña actual no es correcta.';
    } elseif ($mensaje = validarNuevaClave($nueva, $confirmacion)) {
        $error = $mensaje;
    } else {
        $db->prepare('UPDATE usuarios SET password_hash = ?, debe_cambiar = 0 WHERE id = ?')
           ->execute([password_hash($nueva, PASSWORD_DEFAULT), $usuario['id']]);
        iniciarSesionComo($usuario['rol'], $usuario['id'], $usuario['nombre']);
        flash('success', 'Contraseña actualizada correctamente.');
        redirect(paginaInicio($usuario['rol']));
    }
}

function autenticarActual(array $usuario, string $clave): bool {
    $s = getDB()->prepare('SELECT usuario FROM usuarios WHERE id = ?');
    $s->execute([$usuario['id']]);
    $login = $s->fetchColumn();
    return $login !== false && autenticar($login, $clave) !== null;
}

$pageTitle = 'Cambiar contraseña';
$rolRequerido = $usuario['rol'];
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header fade-up">
  <h1><i class="bi bi-key-fill me-2" style="color:#a78bfa"></i>Cambiar contraseña</h1>
  <p><?= $obligatorio ? 'Es tu primer ingreso: crea una contraseña propia para continuar.' : 'Actualiza la contraseña de tu cuenta.' ?></p>
</div>

<div class="row justify-content-center">
  <div class="col-lg-6 col-xl-5 fade-up">
    <div class="je-card">
      <div class="card-body" style="padding:28px">
        <?php if ($error): ?>
          <div class="je-alert je-alert-danger mb-3" role="alert">
            <i class="bi bi-exclamation-triangle-fill alert-icon"></i>
            <div><?= e($error) ?></div>
          </div>
        <?php endif; ?>

        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
          <?php if (!$obligatorio): ?>
          <div class="mb-3">
            <label class="je-label" for="actual">Contraseña actual</label>
            <input class="je-input w-100" type="password" id="actual" name="actual" autocomplete="current-password" required>
          </div>
          <?php endif; ?>
          <div class="mb-3">
            <label class="je-label" for="nueva">Nueva contraseña</label>
            <input class="je-input w-100" type="password" id="nueva" name="nueva" autocomplete="new-password" minlength="8" required>
          </div>
          <div class="mb-4">
            <label class="je-label" for="confirmacion">Confirmar nueva contraseña</label>
            <input class="je-input w-100" type="password" id="confirmacion" name="confirmacion" autocomplete="new-password" minlength="8" required>
          </div>
          <button type="submit" class="je-btn je-btn-primary w-100" style="justify-content:center;padding:12px">
            <i class="bi bi-check-lg"></i> Guardar contraseña
          </button>
        </form>

        <p class="auth-hint">Mínimo 8 caracteres, con letras y números.</p>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
