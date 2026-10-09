<?php
$pageTitle = 'Mis juicios evaluativos';
$rolRequerido = 'aprendiz';
require_once __DIR__ . '/../../includes/header.php';

$db = getDB();
$stmt = $db->prepare(
    'SELECT a.*, f.numero AS ficha, p.nombre AS programa, p.id AS programa_id
     FROM aprendices a
     JOIN fichas    f ON a.ficha_id    = f.id
     JOIN programas p ON f.programa_id = p.id
     WHERE a.numero_documento = ?'
);
$stmt->execute([$usuarioSesion['id']]);
$ap = $stmt->fetch();

if (!$ap): ?>
  <div class="je-alert je-alert-info fade-up" role="alert">
    <i class="bi bi-info-circle-fill alert-icon"></i>
    <div>Todavía no hay información académica registrada para tu documento. Consulta con tu instructor.</div>
  </div>
<?php else:
    $paramsBase = [];
    require __DIR__ . '/../../includes/vista_aprendiz.php';
endif;

require_once __DIR__ . '/../../includes/footer.php';
