<?php
require_once __DIR__ . '/../../includes/functions.php';
requireRol(ROLES_PERSONAL);

$db  = getDB();
$doc = trim($_GET['doc'] ?? '');
if (!$doc) redirect('../fichas/index.php');

$stmt = $db->prepare(
    'SELECT a.*, f.numero AS ficha, p.nombre AS programa, p.id AS programa_id
     FROM aprendices a
     JOIN fichas    f ON a.ficha_id    = f.id
     JOIN programas p ON f.programa_id = p.id
     WHERE a.numero_documento = ?'
);
$stmt->execute([$doc]);
$ap = $stmt->fetch();
if (!$ap) { flash('danger','Aprendiz no encontrado.'); redirect('index.php'); }

$pageTitle = $ap['nombre'];
require_once __DIR__ . '/../../includes/header.php';

$paramsBase = ['doc' => $doc];
$breadcrumb = true;
require __DIR__ . '/../../includes/vista_aprendiz.php';
?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
