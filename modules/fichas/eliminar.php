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

    // 1. Eliminar juicios de todos los aprendices de esta ficha
    //    (el CASCADE en FK lo haría al borrar aprendices, pero lo hacemos explícito)
    $db->prepare(
        'DELETE je FROM juicios_evaluativos je
         JOIN aprendices a ON je.numero_documento = a.numero_documento
         WHERE a.ficha_id = ?'
    )->execute([$fichaId]);

    // 2. Eliminar aprendices
    $db->prepare('DELETE FROM aprendices WHERE ficha_id = ?')->execute([$fichaId]);

    // 3. Eliminar ficha
    $db->prepare('DELETE FROM fichas WHERE id = ?')->execute([$fichaId]);

    $db->commit();

    flash('success', 'Ficha <strong>' . htmlspecialchars($ficha['numero']) . '</strong> eliminada correctamente junto con todos sus aprendices y juicios.');
} catch (Throwable $e) {
    $db->rollBack();
    flash('danger', 'Error al eliminar: ' . $e->getMessage());
}

redirect('index.php');
