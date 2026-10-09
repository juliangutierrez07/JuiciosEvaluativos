<?php
require_once __DIR__ . '/../../includes/functions.php';
$admin = requireRol('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('index.php');

if (!csrfValido($_POST['csrf_token'] ?? null)) {
    flash('danger', 'La solicitud expiró o no es válida. Inténtalo nuevamente.');
    redirect('index.php');
}

$db     = getDB();
$accion = (string)($_POST['accion'] ?? '');

if ($accion === 'crear') {
    $nombre  = trim((string)($_POST['nombre'] ?? ''));
    $usuario = trim((string)($_POST['usuario'] ?? ''));
    $rol     = in_array($_POST['rol'] ?? '', ROLES_PERSONAL, true) ? $_POST['rol'] : 'instructor';
    $clave   = (string)($_POST['clave'] ?? '');

    $error = null;
    if ($nombre === '') {
        $error = 'El nombre es obligatorio.';
    } elseif (!preg_match('/^[A-Za-z0-9._-]{3,60}$/', $usuario)) {
        $error = 'El usuario debe tener de 3 a 60 caracteres: letras, números, punto, guion o guion bajo.';
    } elseif (mb_strlen($clave) < 8) {
        $error = 'La contraseña temporal debe tener al menos 8 caracteres.';
    } else {
        $s = $db->prepare('SELECT (SELECT COUNT(*) FROM usuarios WHERE usuario = ?) + (SELECT COUNT(*) FROM aprendices WHERE numero_documento = ?)');
        $s->execute([$usuario, $usuario]);
        // Un usuario igual al documento de un aprendiz impediría que ese aprendiz ingrese.
        if ((int)$s->fetchColumn() > 0) $error = 'Ese usuario ya está en uso. Elige otro.';
    }

    if ($error) {
        flash('danger', e($error));
    } else {
        $db->prepare('INSERT INTO usuarios (usuario, nombre, rol, password_hash, debe_cambiar) VALUES (?,?,?,?,1)')
           ->execute([$usuario, $nombre, $rol, password_hash($clave, PASSWORD_DEFAULT)]);
        flash('success', nombreRol($rol) . ' <strong>' . e($nombre) . '</strong> creado. Ingresa con el usuario <strong>'
            . e($usuario) . '</strong> y la contraseña temporal que definiste.');
    }
    redirect('index.php');
}

$id = (int)($_POST['id'] ?? 0);
$s = $db->prepare('SELECT id, usuario, nombre FROM usuarios WHERE id = ?');
$s->execute([$id]);
$objetivo = $s->fetch();

if (!$objetivo) {
    flash('danger', 'El usuario no existe.');
    redirect('index.php');
}
if ((string)$objetivo['id'] === $admin['id']) {
    flash('danger', 'No puedes modificar tu propia cuenta desde aquí. Usa "Cambiar contraseña".');
    redirect('index.php');
}

switch ($accion) {
    case 'restablecer':
        $temporal = substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(12))), 0, 10) . random_int(10, 99);
        $db->prepare('UPDATE usuarios SET password_hash = ?, debe_cambiar = 1 WHERE id = ?')
           ->execute([password_hash($temporal, PASSWORD_DEFAULT), $id]);
        limpiarIntentos($objetivo['usuario']);
        flash('success', 'Contraseña temporal de <strong>' . e($objetivo['nombre']) . '</strong>: <code>' . e($temporal)
            . '</code><br>Entrégasela; se mostrará solo esta vez y deberá cambiarla al ingresar.');
        break;

    case 'desactivar':
    case 'activar':
        $db->prepare('UPDATE usuarios SET activo = ? WHERE id = ?')->execute([$accion === 'activar' ? 1 : 0, $id]);
        flash('success', 'Usuario <strong>' . e($objetivo['nombre']) . '</strong> ' . ($accion === 'activar' ? 'activado' : 'desactivado') . '.');
        break;

    default:
        flash('danger', 'Acción no válida.');
}

redirect('index.php');
