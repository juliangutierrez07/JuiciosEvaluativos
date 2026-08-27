<?php
require_once __DIR__ . '/../../includes/functions.php';

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

$sqlJuicios = "
    SELECT c.nombre AS competencia, c.id AS competencia_id, je.estado,
           COUNT(je.resultado_id) OVER (PARTITION BY c.id) AS total_en_comp,
           SUM(je.estado='Aprobado') OVER (PARTITION BY c.id) AS aprobados_en_comp
    FROM juicios_evaluativos je
    JOIN resultados_aprendizaje ra ON je.resultado_id = ra.id
    JOIN competencias c            ON ra.competencia_id = c.id
    WHERE je.numero_documento = ? AND c.programa_id = ?
    ORDER BY c.nombre
";
$stmt2 = $db->prepare($sqlJuicios);
$stmt2->execute([$doc, $ap['programa_id']]);
$todosRA = $stmt2->fetchAll();

$sqlDetalleJuicios = "
    SELECT
        c.nombre AS competencia,
        ra.descripcion AS resultado,
        je.estado,
        je.fecha_juicio,
        je.funcionario
    FROM juicios_evaluativos je
    JOIN resultados_aprendizaje ra ON je.resultado_id = ra.id
    JOIN competencias c            ON ra.competencia_id = c.id
    WHERE je.numero_documento = ? AND c.programa_id = ?
    ORDER BY c.nombre, ra.descripcion
";
$stmtDetalle = $db->prepare($sqlDetalleJuicios);
$stmtDetalle->execute([$doc, $ap['programa_id']]);
$juiciosDetalle = $stmtDetalle->fetchAll();
$filtroJuicio = trim($_GET['juicio'] ?? '');
if (!in_array($filtroJuicio, ['Aprobado', 'Pendiente'], true)) {
    $filtroJuicio = '';
}
$juiciosFiltrados = $filtroJuicio
    ? array_values(array_filter($juiciosDetalle, fn($j) => $j['estado'] === $filtroJuicio))
    : $juiciosDetalle;

$porComp = [];
foreach ($todosRA as $row) {
    $porComp[$row['competencia_id']]['nombre']    = $row['competencia'];
    $porComp[$row['competencia_id']]['total']     = $row['total_en_comp'];
    $porComp[$row['competencia_id']]['aprobados'] = $row['aprobados_en_comp'];
}

$totalRA = count($todosRA);
$aprobados = $pendientes = 0;
foreach ($todosRA as $r) { $r['estado']==='Aprobado' ? $aprobados++ : $pendientes++; }
$pct = $totalRA > 0 ? round($aprobados / $totalRA * 100, 1) : 0;
$pctColor = $pct >= 75 ? '#34d399' : ($pct >= 40 ? '#fbbf24' : '#f87171');
$barColor = $pct >= 75 ? 'green' : ($pct >= 40 ? 'amber' : 'red');

$mejorComp = null; $mejorPct = -1;
foreach ($porComp as $comp) {
    $p = $comp['total'] > 0 ? round($comp['aprobados'] / $comp['total'] * 100, 1) : 0;
    if ($p > $mejorPct) { $mejorPct = $p; $mejorComp = $comp['nombre']; }
}
$filtroComp = trim($_GET['filtro'] ?? '');
$urlDetalle = '?doc=' . urlencode($doc);
$urlAprobados = $urlDetalle . '&juicio=Aprobado';
$urlPendientes = $urlDetalle . '&juicio=Pendiente';
?>

<!-- Breadcrumb -->
<ol class="je-breadcrumb">
  <li><a href="index.php"><i class="bi bi-people-fill me-1"></i>Aprendices</a></li>
  <li class="active"><?= e($ap['nombre']) ?></li>
</ol>

<!-- Cabecera aprendiz -->
<div class="je-card mb-4 fade-up" style="background:linear-gradient(135deg,rgba(124,58,237,.12),rgba(79,70,229,.06));border-color:rgba(124,58,237,.3)">
  <div class="card-body" style="padding:24px">
    <div style="display:flex;align-items:flex-start;flex-wrap:wrap;gap:20px;justify-content:space-between">
      <div>
        <div style="display:flex;align-items:center;gap:16px;margin-bottom:12px">
          <div style="width:56px;height:56px;background:linear-gradient(135deg,var(--accent),#4f46e5);border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;font-weight:800;color:#fff;flex-shrink:0">
            <?= mb_strtoupper(mb_substr($ap['nombre'], 0, 1)) ?>
          </div>
          <div>
            <h2 style="font-size:1.25rem;font-weight:700;color:#fff;margin:0"><?= e($ap['nombre']) ?></h2>
            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:8px;align-items:center">
              <span class="je-badge je-badge-gray"><i class="bi bi-card-text"></i> <?= e($ap['tipo_documento']) ?> <?= e($ap['numero_documento']) ?></span>
              <?= badgeEstado($ap['estado']) ?>
              <span class="je-badge je-badge-cyan"><i class="bi bi-journal-text"></i> Ficha <?= e($ap['ficha']) ?></span>
              <span style="font-size:.75rem;color:var(--text-muted)"><i class="bi bi-book me-1"></i><?= e($ap['programa']) ?></span>
            </div>
          </div>
        </div>
      </div>
      <div style="text-align:right;min-width:160px">
        <div style="font-size:2.5rem;font-weight:800;color:<?= $pctColor ?>;line-height:1"><?= $pct ?>%</div>
        <div style="font-size:.72rem;color:var(--text-muted);margin:6px 0 8px;font-weight:600;text-transform:uppercase;letter-spacing:.06em">Cumplimiento global</div>
        <?= barraProgreso((float)$pct) ?>
      </div>
    </div>
  </div>
</div>

<!-- Stats aprendiz -->
<div class="row g-3 mb-4">
  <a href="<?= $urlDetalle ?>" class="col-6 col-lg-3 fade-up stat-card-link <?= $filtroJuicio === '' ? 'active' : '' ?>">
    <div class="stat-card stat-indigo">
      <div class="stat-icon"><i class="bi bi-list-check"></i></div>
      <div><div class="stat-num"><?= $totalRA ?></div><div class="stat-label">Resultados de Aprendizaje</div></div>
    </div>
  </a>
  <a href="<?= $urlAprobados ?>" class="col-6 col-lg-3 fade-up-2 stat-card-link <?= $filtroJuicio === 'Aprobado' ? 'active' : '' ?>">
    <div class="stat-card stat-green">
      <div class="stat-icon"><i class="bi bi-check-circle-fill"></i></div>
      <div><div class="stat-num"><?= $aprobados ?></div><div class="stat-label">Aprobados</div></div>
    </div>
  </a>
  <a href="<?= $urlPendientes ?>" class="col-6 col-lg-3 fade-up-3 stat-card-link <?= $filtroJuicio === 'Pendiente' ? 'active' : '' ?>">
    <div class="stat-card stat-amber">
      <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
      <div><div class="stat-num"><?= $pendientes ?></div><div class="stat-label">Pendientes</div></div>
    </div>
  </a>
  <div class="col-6 col-lg-3 fade-up-4">
    <div class="stat-card stat-violet">
      <div class="stat-icon"><i class="bi bi-trophy-fill"></i></div>
      <div>
        <div class="stat-num" style="font-size:1.1rem;line-height:1.3"><?= $mejorComp ? e(mb_substr($mejorComp,0,22).(mb_strlen($mejorComp)>22?'…':'')) : '—' ?></div>
        <div class="stat-label">Mayor aprobación (<?= $mejorPct ?>%)</div>
      </div>
    </div>
  </div>
</div>

<!-- Juicios evaluativos -->
<div class="je-card mb-4 fade-up-2 apprentice-judgements">
  <div class="card-header">
    <i class="bi bi-clipboard-check" style="color:#a78bfa"></i> Juicios evaluativos
    <?php if ($filtroJuicio): ?>
      <span class="je-badge <?= $filtroJuicio === 'Aprobado' ? 'je-badge-green' : 'je-badge-gray' ?> ms-2"><?= e($filtroJuicio) ?></span>
    <?php endif; ?>
  </div>
  <div class="card-body p-0">
    <?php if (empty($juiciosFiltrados)): ?>
      <p style="text-align:center;color:var(--text-muted);padding:3rem;margin:0">No hay juicios evaluativos para este filtro.</p>
    <?php else: ?>
      <div class="table-responsive">
        <table class="je-table mb-0">
          <thead>
            <tr>
              <th>Competencia</th>
              <th>Resultado de aprendizaje</th>
              <th>Juicio</th>
              <th>Fecha</th>
              <th>Evaluador</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($juiciosFiltrados as $j): ?>
            <tr>
              <td class="table-muted"><?= e($j['competencia']) ?></td>
              <td><?= e($j['resultado']) ?></td>
              <td><?= badgeJuicio($j['estado']) ?></td>
              <td class="table-muted">
                <?= $j['fecha_juicio'] ? date('d/m/Y', strtotime($j['fecha_juicio'])) : '-' ?>
              </td>
              <td class="table-muted"><?= $j['funcionario'] ? e($j['funcionario']) : '-' ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Competencias -->
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:16px" class="fade-up">
  <div style="font-size:.85rem;font-weight:600;color:var(--text-main)"><i class="bi bi-list-check me-2" style="color:#a78bfa"></i>Competencias</div>
  <div style="display:flex;gap:8px">
    <a href="?doc=<?= urlencode($doc) ?>" class="je-btn je-btn-sm <?= $filtroComp===''?'je-btn-primary':'je-btn-outline' ?>">Todas</a>
    <a href="?doc=<?= urlencode($doc) ?>&filtro=aprobado" class="je-btn je-btn-sm <?= $filtroComp==='aprobado'?'je-btn-primary':'je-btn-outline' ?>"><i class="bi bi-check-circle"></i> Aprobadas</a>
    <a href="?doc=<?= urlencode($doc) ?>&filtro=pendiente" class="je-btn je-btn-sm <?= $filtroComp==='pendiente'?'je-btn-primary':'je-btn-outline' ?>"><i class="bi bi-hourglass-split"></i> Pendientes</a>
  </div>
</div>

<?php
$compFiltradas = array_filter($porComp, function($comp) use ($filtroComp) {
    $p = $comp['total'] > 0 ? ($comp['aprobados'] / $comp['total'] * 100) : 0;
    if ($filtroComp === 'aprobado')  return $p == 100;
    if ($filtroComp === 'pendiente') return $p < 100;
    return true;
});

if (empty($compFiltradas)): ?>
  <p style="text-align:center;color:var(--text-muted);padding:3rem;margin:0">No hay competencias para mostrar.</p>
<?php endif; ?>

<div class="fade-up-2">
<?php foreach ($compFiltradas as $comp):
  $pctComp  = $comp['total'] > 0 ? round($comp['aprobados'] / $comp['total'] * 100, 1) : 0;
  $pendComp = $comp['total'] - $comp['aprobados'];
  $barC     = $pctComp >= 75 ? 'green' : ($pctComp >= 40 ? 'amber' : 'red');
?>
<div class="je-card mb-3">
  <div class="card-body" style="padding:18px 22px">
    <div style="display:flex;align-items:center;flex-wrap:wrap;gap:16px">
      <div style="flex:1;min-width:220px">
        <div style="font-weight:600;color:var(--text-main);margin-bottom:4px"><?= e($comp['nombre']) ?></div>
        <div style="font-size:.75rem;color:var(--text-muted)"><?= $comp['total'] ?> resultados de aprendizaje</div>
      </div>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <span class="je-badge je-badge-green"><i class="bi bi-check-circle"></i> <?= (int)$comp['aprobados'] ?> Aprobados</span>
        <span class="je-badge je-badge-gray"><i class="bi bi-hourglass-split"></i> <?= (int)$pendComp ?> Pendientes</span>
      </div>
      <div style="min-width:160px;text-align:right">
        <?= barraProgreso((float)$pctComp) ?>
      </div>
    </div>
  </div>
</div>
<?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
