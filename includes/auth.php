<?php
// ── Sesión ───────────────────────────────────────────────────────────────────

function conexionSegura(): bool {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

function iniciarSesion(): void {
    if (session_status() !== PHP_SESSION_NONE) return;
    session_name('evaltrack_sesion');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => conexionSegura(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    // Cierre por inactividad (2 horas).
    if (isset($_SESSION['auth']['actividad']) && time() - $_SESSION['auth']['actividad'] > 7200) {
        unset($_SESSION['auth']);
    }
    if (isset($_SESSION['auth'])) $_SESSION['auth']['actividad'] = time();
}

function usuarioActual(): ?array {
    iniciarSesion();
    return $_SESSION['auth'] ?? null;
}

function esAdmin(): bool {
    return (usuarioActual()['rol'] ?? null) === 'admin';
}

const ROLES_PERSONAL = ['admin', 'instructor'];

function nombreRol(string $rol): string {
    return ['admin' => 'Administrador', 'instructor' => 'Instructor', 'aprendiz' => 'Aprendiz'][$rol] ?? $rol;
}

function iniciarSesionComo(string $rol, string $id, string $nombre, bool $debeCambiar = false): void {
    iniciarSesion();
    session_regenerate_id(true);
    $_SESSION['auth'] = [
        'rol'          => $rol,
        'id'           => $id,
        'nombre'       => $nombre,
        'debe_cambiar' => $debeCambiar,
        'actividad'    => time(),
    ];
}

function cerrarSesion(): void {
    iniciarSesion();
    $_SESSION = [];
    session_regenerate_id(true);
}

/**
 * Exige una sesión con alguno de los roles indicados. Sin sesión envía al login;
 * con un rol no permitido envía a la página de inicio de ese rol.
 */
function requireRol(string|array $roles): array {
    $usuario = usuarioActual();
    if (!$usuario) {
        redirect(base('login.php'));
    }
    if (!empty($usuario['debe_cambiar']) && basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'cambiar_clave.php') {
        redirect(base('cambiar_clave.php'));
    }
    if (in_array($usuario['rol'], ROLES_PERSONAL, true)) {
        // Refleja de inmediato si un administrador desactivó la cuenta o cambió su rol.
        $s = getDB()->prepare('SELECT rol FROM usuarios WHERE id = ? AND activo = 1');
        $s->execute([$usuario['id']]);
        $rolActual = $s->fetchColumn();
        if ($rolActual === false) {
            cerrarSesion();
            flash('danger', 'Tu cuenta fue desactivada. Contacta al administrador.');
            redirect(base('login.php'));
        }
        $usuario['rol'] = $_SESSION['auth']['rol'] = $rolActual;
    }
    if (!in_array($usuario['rol'], (array)$roles, true)) {
        redirect(paginaInicio($usuario['rol']));
    }
    return $usuario;
}

function paginaInicio(string $rol): string {
    return $rol === 'aprendiz' ? base('modules/portal/index.php') : base('index.php');
}

// ── Credenciales ─────────────────────────────────────────────────────────────

const MAX_INTENTOS_LOGIN = 5;
const VENTANA_INTENTOS_MIN = 15;

function loginBloqueado(string $identificador): bool {
    $s = getDB()->prepare(
        'SELECT COUNT(*) FROM intentos_login
         WHERE identificador = ? AND created_at > NOW() - INTERVAL ' . VENTANA_INTENTOS_MIN . ' MINUTE'
    );
    $s->execute([mb_strtolower($identificador)]);
    return (int)$s->fetchColumn() >= MAX_INTENTOS_LOGIN;
}

function registrarIntentoFallido(string $identificador): void {
    $db = getDB();
    $db->prepare('INSERT INTO intentos_login (identificador) VALUES (?)')->execute([mb_strtolower($identificador)]);
    $db->exec('DELETE FROM intentos_login WHERE created_at < NOW() - INTERVAL 1 DAY');
}

function limpiarIntentos(string $identificador): void {
    getDB()->prepare('DELETE FROM intentos_login WHERE identificador = ?')->execute([mb_strtolower($identificador)]);
}

/**
 * Valida usuario y contraseña de administradores e instructores.
 */
function autenticar(string $usuario, string $password): ?array {
    $db = getDB();
    $s = $db->prepare('SELECT id, nombre, rol, password_hash, debe_cambiar FROM usuarios WHERE usuario = ? AND activo = 1');
    $s->execute([$usuario]);
    $personal = $s->fetch();
    if (!$personal) {
        // Mantiene un tiempo de respuesta similar al de un usuario existente.
        password_verify($password, '$2y$10$KvTTPuh8cs7eLoxmfDXI7.ofDnM.ZWe7d4zW/kHj6IrKj98SrfFSy');
        return null;
    }
    if (!password_verify($password, $personal['password_hash'])) return null;

    $db->prepare('UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?')->execute([$personal['id']]);
    return [
        'rol'          => $personal['rol'],
        'id'           => (string)$personal['id'],
        'nombre'       => $personal['nombre'],
        'debe_cambiar' => (bool)$personal['debe_cambiar'],
    ];
}

/**
 * Los aprendices consultan sus juicios solo con el número de documento.
 */
function buscarAprendizPorDocumento(string $documento): ?array {
    $s = getDB()->prepare('SELECT numero_documento, nombre FROM aprendices WHERE numero_documento = ?');
    $s->execute([$documento]);
    return $s->fetch() ?: null;
}

/**
 * IP del visitante. Detrás del proxy del despliegue REMOTE_ADDR es una IP
 * privada; en ese caso se usa la primera IP de X-Forwarded-For.
 */
function ipCliente(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $reenviada = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '')[0]);
    $esPrivada = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    if ($esPrivada && filter_var($reenviada, FILTER_VALIDATE_IP)) return $reenviada;
    return $ip;
}

function validarNuevaClave(string $clave, string $confirmacion): ?string {
    if (mb_strlen($clave) < 8) return 'La contraseña debe tener al menos 8 caracteres.';
    if (!preg_match('/[A-Za-zÁÉÍÓÚáéíóúÑñ]/u', $clave) || !preg_match('/\d/', $clave)) {
        return 'La contraseña debe combinar letras y números.';
    }
    if (!hash_equals($clave, $confirmacion)) return 'Las contraseñas no coinciden.';
    return null;
}
