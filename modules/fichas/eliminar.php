<?php
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('index.php');

if (!csrfValido($_POST['csrf_token'] ?? null)) {
    flash('danger', 'La solicitud de eliminación expiró o no es válida. Inténtalo nuevamente.');
    redirect('index.php');
}

$fichaId = (int)($_POST['ficha_id'] ?? 0);
if (!$fichaId) {
    flash('danger', 'Ficha no válida.');
    redirect('index.php');
}

$db = getDB();

// Verificar que existe
$stmt = $db->prepare('SELECT numero FROM fichas WHERE id = ?');
$stmt->execute([$fichaId]);
$ficha = $stmt->fetch();
if (!$ficha) {
    flash('danger', 'La ficha no existe.');
    redirect('index.php');
}

try {
    $db->beginTransaction();

    // Las claves foráneas eliminan en cascada aprendices, juicios e importaciones.
    $eliminar = $db->prepare('DELETE FROM fichas WHERE id = ?');
    $eliminar->execute([$fichaId]);
    if ($eliminar->rowCount() !== 1) {
        throw new RuntimeException('No se pudo confirmar la eliminación de la ficha.');
    }

    $db->commit();

    flash('success', 'Ficha <strong>' . htmlspecialchars($ficha['numero']) . '</strong> eliminada correctamente junto con todos sus aprendices y juicios.');
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    flash('danger', 'Error al eliminar: ' . $e->getMessage());
}

redirect('index.php');
