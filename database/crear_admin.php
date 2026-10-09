<?php
// Uso: php database/crear_admin.php <usuario> <contraseña> ["Nombre completo"]
// Crea un administrador o actualiza la contraseña si el usuario ya existe.
require_once __DIR__ . '/../config/database.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

[$script, $usuario, $clave, $nombre] = array_pad($argv, 4, null);
if (!$usuario || !$clave) {
    fwrite(STDERR, "Uso: php database/crear_admin.php <usuario> <contraseña> [\"Nombre completo\"]\n");
    exit(1);
}
if (mb_strlen($clave) < 8) {
    fwrite(STDERR, "La contraseña debe tener al menos 8 caracteres.\n");
    exit(1);
}

guardarAdmin(getDB(), $usuario, $clave, $nombre ?: $usuario);
echo "Administrador '{$usuario}' guardado.\n";

function guardarAdmin(PDO $db, string $usuario, string $clave, string $nombre): void {
    $db->prepare(
        "INSERT INTO usuarios (usuario, nombre, rol, password_hash, debe_cambiar, activo) VALUES (?,?,'admin',?,0,1)
         ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), nombre = VALUES(nombre),
                                 rol = 'admin', debe_cambiar = 0, activo = 1"
    )->execute([$usuario, $nombre, password_hash($clave, PASSWORD_DEFAULT)]);
}
