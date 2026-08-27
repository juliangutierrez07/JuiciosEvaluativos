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

echo "Estructura de base de datos verificada.\n";

