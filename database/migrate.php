<?php
require_once __DIR__ . '/../config/database.php';

$intentos = 30;
while ($intentos-- > 0) {
    try {
        $db = getDB();
        break;
    } catch (Throwable $e) {
        if ($intentos === 0) throw $e;
        sleep(2);
    }
}

$sql = file_get_contents(__DIR__ . '/schema.sql');
if ($sql === false) {
    throw new RuntimeException('No se pudo leer database/schema.sql');
}

// La base ya es creada por el servicio MySQL de Dokploy.
$sql = preg_replace('/CREATE DATABASE.*?;\s*/is', '', $sql, 1);
$sql = preg_replace('/USE\s+[^;]+;\s*/i', '', $sql, 1);

foreach (array_filter(array_map('trim', explode(';', $sql))) as $sentencia) {
    $db->exec($sentencia);
}

// Columnas agregadas después de la primera versión del esquema.
$columnasNuevas = [
    // Los usuarios creados antes de existir los roles eran administradores.
    ['usuarios', 'rol', [
        "ALTER TABLE usuarios ADD COLUMN rol enum('admin','instructor') NOT NULL DEFAULT 'admin' AFTER nombre",
        "ALTER TABLE usuarios MODIFY rol enum('admin','instructor') NOT NULL DEFAULT 'instructor'",
    ]],
    ['usuarios', 'debe_cambiar', ['ALTER TABLE usuarios ADD COLUMN debe_cambiar tinyint(1) NOT NULL DEFAULT 0 AFTER password_hash']],
    ['usuarios', 'activo', ['ALTER TABLE usuarios ADD COLUMN activo tinyint(1) NOT NULL DEFAULT 1 AFTER debe_cambiar']],
    ['importaciones', 'usuario_id', ['ALTER TABLE importaciones ADD COLUMN usuario_id int unsigned DEFAULT NULL AFTER ficha_id, ADD KEY idx_import_usuario (usuario_id)']],
];
$existe = $db->prepare(
    'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
);
foreach ($columnasNuevas as [$tabla, $columna, $sentencias]) {
    $existe->execute([$tabla, $columna]);
    if ((int)$existe->fetchColumn()) continue;
    foreach ($sentencias as $sentencia) $db->exec($sentencia);
}

echo "Estructura de base de datos verificada.\n";

// Primer administrador para despliegues: solo se crea si todavía no hay ninguno.
$adminUsuario = getenv('ADMIN_USER') ?: '';
$adminClave   = getenv('ADMIN_PASS') ?: '';
if ($adminUsuario !== '' && $adminClave !== '' && (int)$db->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'admin'")->fetchColumn() === 0) {
    $db->prepare("INSERT INTO usuarios (usuario, nombre, rol, password_hash) VALUES (?,?,'admin',?)")
       ->execute([$adminUsuario, getenv('ADMIN_NAME') ?: $adminUsuario, password_hash($adminClave, PASSWORD_DEFAULT)]);
    echo "Administrador inicial '{$adminUsuario}' creado.\n";
}
