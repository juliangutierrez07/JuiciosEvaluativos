<?php
/**
 * Detalle de juicios de un aprendiz. Lo usan la vista del administrador y el
 * portal del aprendiz.
 *
 * Requiere: $db, $ap (fila del aprendiz con ficha, programa y programa_id),
 * $paramsBase (parámetros GET que identifican la página) y, opcionalmente,
 * $breadcrumb (bool).
 */
$doc = $ap['numero_documento'];
$urlPagina = fn(array $extra = []) => '?' . http_build_query(array_merge($paramsBase, $extra));

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
        c.id AS competencia_id,
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

// ── Filtros de la tabla de juicios ──────────────────────────────────────────
const SIN_INSTRUCTOR = 'sin-instructor';

$filtroJuicio = trim($_GET['juicio'] ?? '');
if (!in_array($filtroJuicio, ['Aprobado', 'Pendiente'], true)) $filtroJuicio = '';
$filtroCompetencia = (int)($_GET['competencia'] ?? 0);
$filtroInstructor  = trim((string)($_GET['instructor'] ?? ''));
$filtroTexto       = trim((string)($_GET['q'] ?? ''));

$opcionesCompetencia = array_column($juiciosDetalle, 'competencia', 'competencia_id');
$opcionesInstructor  = array_unique(array_filter(array_column($juiciosDetalle, 'funcionario')));
usort($opcionesInstructor, fn($x, $y) => strcmp(nombreFuncionario($x), nombreFuncionario($y)));
$haySinInstructor = in_array(null, array_column($juiciosDetalle, 'funcionario'), true);

if (!isset($opcionesCompetencia[$filtroCompetencia])) $filtroCompetencia = 0;
if ($filtroInstructor !== SIN_INSTRUCTOR && !in_array($filtroInstructor, $opcionesInstructor, true)) $filtroInstructor = '';

$juiciosFiltrados = array_values(array_filter($juiciosDetalle, function ($j) use ($filtroJuicio, $filtroCompetencia, $filtroInstructor, $filtroTexto) {
    if ($filtroJuicio && $j['estado'] !== $filtroJuicio) return false;
    if ($filtroCompetencia && (int)$j['competencia_id'] !== $filtroCompetencia) return false;
    if ($filtroInstructor === SIN_INSTRUCTOR && $j['funcionario'] !== null) return false;
    if ($filtroInstructor !== '' && $filtroInstructor !== SIN_INSTRUCTOR && $j['funcionario'] !== $filtroInstructor) return false;
    if ($filtroTexto !== '') {
        $texto = $j['competencia'] . ' ' . $j['resultado'] . ' ' . ($j['funcionario'] ?? '');
        if (mb_stripos($texto, $filtroTexto) === false) return false;
    }
    return true;
}));

// Filtros activos que deben conservarse al cambiar uno de ellos.
$filtrosActivos = array_filter([
    'juicio'      => $filtroJuicio,
    'competencia' => $filtroCompetencia ?: '',
    'instructor'  => $filtroInstructor,
    'q'           => $filtroTexto,
], fn($v) => $v !== '');

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
$urlDetalle = $urlPagina(array_diff_key($filtrosActivos, ['juicio' => 1]));
$urlAprobados = $urlPagina(array_merge($filtrosActivos, ['juicio' => 'Aprobado']));
$urlPendientes = $urlPagina(array_merge($filtrosActivos, ['juicio' => 'Pendiente']));
?>

<?php if (!empty($breadcrumb)): ?>
<!-- Breadcrumb -->
<ol class="je-breadcrumb">
  <li><a href="index.php"><i class="bi bi-people-fill me-1"></i>Aprendices</a></li>
  <li class="active"><?= e($ap['nombre']) ?></li>
</ol>
<?php endif; ?>

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
  <a href="<?= e($urlDetalle) ?>" class="col-6 col-lg-3 fade-up stat-card-link <?= $filtroJuicio === '' ? 'active' : '' ?>">
    <div class="stat-card stat-indigo">
      <div class="stat-icon"><i class="bi bi-list-check"></i></div>
      <div><div class="stat-num"><?= $totalRA ?></div><div class="stat-label">Resultados de Aprendizaje</div></div>
    </div>
  </a>
  <a href="<?= e($urlAprobados) ?>" class="col-6 col-lg-3 fade-up-2 stat-card-link <?= $filtroJuicio === 'Aprobado' ? 'active' : '' ?>">
    <div class="stat-card stat-green">
      <div class="stat-icon"><i class="bi bi-check-circle-fill"></i></div>
      <div><div class="stat-num"><?= $aprobados ?></div><div class="stat-label">Aprobados</div></div>
    </div>
  </a>
  <a href="<?= e($urlPendientes) ?>" class="col-6 col-lg-3 fade-up-3 stat-card-link <?= $filtroJuicio === 'Pendiente' ? 'active' : '' ?>">
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
    <span class="juicios-conteo"><?= count($juiciosFiltrados) ?> de <?= count($juiciosDetalle) ?> resultados</span>
  </div>
  <form method="GET" class="juicios-filtros">
    <?php foreach ($paramsBase as $nombre => $valor): ?>
      <input type="hidden" name="<?= e($nombre) ?>" value="<?= e($valor) ?>">
    <?php endforeach; ?>
    <div class="filtro-campo">
      <label class="je-label" for="f-juicio">Juicio</label>
      <select class="je-input w-100" id="f-juicio" name="juicio" onchange="this.form.submit()">
        <option value="">Todos</option>
        <option value="Aprobado" <?= $filtroJuicio === 'Aprobado' ? 'selected' : '' ?>>Aprobados</option>
        <option value="Pendiente" <?= $filtroJuicio === 'Pendiente' ? 'selected' : '' ?>>Pendientes</option>
      </select>
    </div>
    <div class="filtro-campo filtro-ancho">
      <label class="je-label" for="f-competencia">Competencia</label>
      <select class="je-input w-100" id="f-competencia" name="competencia" onchange="this.form.submit()">
        <option value="">Todas las competencias</option>
        <?php foreach ($opcionesCompetencia as $idComp => $nombreComp): ?>
          <option value="<?= (int)$idComp ?>" <?= $filtroCompetencia === (int)$idComp ? 'selected' : '' ?>><?= e($nombreComp) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="filtro-campo">
      <label class="je-label" for="f-instructor">Instructor</label>
      <select class="je-input w-100" id="f-instructor" name="instructor" onchange="this.form.submit()">
        <option value="">Todos los instructores</option>
        <?php foreach ($opcionesInstructor as $instructor): ?>
          <option value="<?= e($instructor) ?>" <?= $filtroInstructor === $instructor ? 'selected' : '' ?>><?= e(nombreFuncionario($instructor)) ?></option>
        <?php endforeach; ?>
        <?php if ($haySinInstructor): ?>
          <option value="<?= SIN_INSTRUCTOR ?>" <?= $filtroInstructor === SIN_INSTRUCTOR ? 'selected' : '' ?>>Sin instructor (por evaluar)</option>
        <?php endif; ?>
      </select>
    </div>
    <div class="filtro-campo filtro-ancho">
      <label class="je-label" for="f-q">Buscar</label>
      <div style="display:flex;gap:6px">
        <input class="je-input w-100" type="search" id="f-q" name="q" value="<?= e($filtroTexto) ?>" placeholder="Resultado, competencia o instructor">
        <button type="submit" class="je-btn je-btn-primary je-btn-sm icon-only" title="Buscar" aria-label="Buscar"><i class="bi bi-search"></i></button>
      </div>
    </div>
    <?php if ($filtrosActivos): ?>
      <a href="<?= e($urlPagina()) ?>" class="je-btn je-btn-outline je-btn-sm filtro-limpiar"><i class="bi bi-x-lg"></i> Limpiar filtros</a>
    <?php endif; ?>
  </form>
  <div class="card-body p-0">
    <?php if (empty($juiciosFiltrados)): ?>
      <p style="text-align:center;color:var(--text-muted);padding:3rem;margin:0">No hay juicios evaluativos que coincidan con los filtros.</p>
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
              <td class="table-muted"><?= $j['funcionario'] ? e(nombreFuncionario($j['funcionario'])) : 'Sin instructor' ?></td>
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
    <a href="<?= e($urlPagina()) ?>" class="je-btn je-btn-sm <?= $filtroComp===''?'je-btn-primary':'je-btn-outline' ?>">Todas</a>
    <a href="<?= e($urlPagina(['filtro' => 'aprobado'])) ?>" class="je-btn je-btn-sm <?= $filtroComp==='aprobado'?'je-btn-primary':'je-btn-outline' ?>"><i class="bi bi-check-circle"></i> Aprobadas</a>
    <a href="<?= e($urlPagina(['filtro' => 'pendiente'])) ?>" class="je-btn je-btn-sm <?= $filtroComp==='pendiente'?'je-btn-primary':'je-btn-outline' ?>"><i class="bi bi-hourglass-split"></i> Pendientes</a>
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

