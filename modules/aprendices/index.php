<?php
$pageTitle = 'Aprendices';
require_once __DIR__ . '/../../includes/header.php';

$db = getDB();

$fichaId    = (int)($_GET['ficha_id']    ?? 0);
$programaId = (int)($_GET['programa_id'] ?? 0);
$estado     = trim($_GET['estado']       ?? '');
$busqueda   = trim($_GET['q']            ?? '');
$juicio     = trim($_GET['juicio']       ?? '');

$where  = ['1=1'];
$params = [];
if ($fichaId)    { $where[] = 'a.ficha_id = ?';    $params[] = $fichaId; }
if ($programaId) { $where[] = 'f.programa_id = ?'; $params[] = $programaId; }
if ($estado)     { $where[] = 'a.estado = ?';       $params[] = $estado; }
if ($busqueda)   { $where[] = '(a.nombre LIKE ? OR a.numero_documento LIKE ?)'; $params[] = "%{$busqueda}%"; $params[] = "%{$busqueda}%"; }

$whereSQL  = implode(' AND ', $where);
$havingSQL = match($juicio) { 'aprobado' => 'HAVING pct = 100', 'pendiente' => 'HAVING pct < 100', default => '' };

$sql = "
    SELECT a.tipo_documento, a.numero_documento, a.nombre, a.estado,
           f.numero AS ficha, f.id AS ficha_id,
           p.nombre AS programa,
           COUNT(je.resultado_id) AS total_ra,
           SUM(je.estado = 'Aprobado') AS ra_aprobados,
           SUM(je.estado = 'Pendiente') AS ra_pendientes,
           ROUND(IFNULL(SUM(je.estado='Aprobado')/NULLIF(COUNT(je.resultado_id),0)*100,0),1) AS pct
    FROM aprendices a
    JOIN fichas    f  ON a.ficha_id    = f.id
    JOIN programas p  ON f.programa_id = p.id
    LEFT JOIN juicios_evaluativos je ON je.numero_documento = a.numero_documento
    WHERE {$whereSQL}
    GROUP BY a.tipo_documento, a.numero_documento, a.nombre, a.estado, f.numero, f.id, p.nombre
    {$havingSQL}
    ORDER BY a.nombre
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$aprendices = $stmt->fetchAll();

$tituloExtra = '';
if ($fichaId) {
    $fInfo = $db->prepare('SELECT f.numero, p.nombre FROM fichas f JOIN programas p ON f.programa_id=p.id WHERE f.id=?');
    $fInfo->execute([$fichaId]);
    $fRow = $fInfo->fetch();
    if ($fRow) $tituloExtra = ' — Ficha ' . e($fRow['numero']) . ' (' . e($fRow['nombre']) . ')';
}
$programas = getProgramas();
$estados   = ['En formación', 'Retiro Voluntario', 'Trasladado'];

$qBase = http_build_query(array_filter(['ficha_id'=>$fichaId?:null,'programa_id'=>$programaId?:null,'estado'=>$estado,'q'=>$busqueda]));
$urlTodos      = 'index.php' . ($qBase ? "?$qBase" : '');
$urlAprobados  = 'index.php?' . $qBase . ($qBase ? '&' : '') . 'juicio=aprobado';
$urlPendientes = 'index.php?' . $qBase . ($qBase ? '&' : '') . 'juicio=pendiente';
?>

<div class="page-header fade-up" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
  <div>
    <h1><i class="bi bi-people-fill me-2" style="color:#a78bfa"></i>Aprendices<?= $tituloExtra ?></h1>
    <p><?= count($aprendices) ?> aprendices encontrados</p>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <a href="<?= $urlTodos ?>"      class="je-btn je-btn-sm <?= $juicio==='' ? 'je-btn-primary' : 'je-btn-outline' ?>">Todos</a>

    <a href="<?= $urlPendientes ?>" class="je-btn je-btn-sm <?= $juicio==='pendiente'? 'je-btn-primary' : 'je-btn-outline' ?>"><i class="bi bi-hourglass-split"></i> Con pendientes</a>
  </div>
</div>

<!-- Filtros -->
<div class="je-card mb-4 fade-up-2">
  <div class="card-body">
    <form method="GET" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end">
      <div style="flex:1;min-width:180px">
        <label class="je-label">Buscar</label>
        <input type="text" name="q" class="je-input" placeholder="Nombre o documento" value="<?= e($busqueda) ?>" style="width:100%">
      </div>
      <div style="flex:1;min-width:180px">
        <label class="je-label">Programa</label>
        <select name="programa_id" class="je-input" style="width:100%">
          <option value="">Todos</option>
          <?php foreach ($programas as $p): ?>
            <option value="<?= $p['id'] ?>" <?= $programaId==$p['id']?'selected':'' ?>><?= e($p['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="flex:1;min-width:140px">
        <label class="je-label">Estado</label>
        <select name="estado" class="je-input" style="width:100%">
          <option value="">Todos</option>
          <?php foreach ($estados as $est): ?>
            <option value="<?= e($est) ?>" <?= $estado===$est?'selected':'' ?>><?= e($est) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if ($fichaId): ?><input type="hidden" name="ficha_id" value="<?= $fichaId ?>"><?php endif; ?>
      <?php if ($juicio):  ?><input type="hidden" name="juicio"   value="<?= e($juicio) ?>"><?php endif; ?>
      <div style="display:flex;gap:8px">
        <button type="submit" class="je-btn je-btn-primary je-btn-sm icon-only" title="Buscar" aria-label="Buscar">
          <i class="bi bi-search"></i>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Tabla -->
<div class="je-card fade-up-3">
  <div class="card-body p-0">
    <?php if (empty($aprendices)): ?>
      <p style="text-align:center;color:var(--text-muted);padding:3rem;margin:0">No se encontraron aprendices.</p>
    <?php else: ?>
      <div style="overflow-x:auto">
        <table class="je-table dt-table">
          <thead>
            <tr>
              <th>Documento</th><th>Nombre</th><th>Estado</th>
              <th>Ficha</th><th>Programa</th>
              <th style="text-align:center">Resultados de Aprendizaje</th>
              <th style="text-align:center">Aprobados</th>
              <th style="text-align:center">Pendientes</th>
              <th>Cumplimiento</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($aprendices as $ap): ?>
            <tr>
              <td>
                <span class="je-badge je-badge-gray" style="margin-right:4px"><?= e($ap['tipo_documento']) ?></span>
                <span style="font-weight:600"><?= e($ap['numero_documento']) ?></span>
              </td>
              <td style="font-weight:500"><?= e($ap['nombre']) ?></td>
              <td><?= badgeEstado($ap['estado']) ?></td>
              <td>
                <a href="../fichas/index.php?q=<?= e($ap['ficha']) ?>" style="color:#a78bfa;text-decoration:none;font-weight:600">
                  <?= e($ap['ficha']) ?>
                </a>
              </td>
              <td style="font-size:.8rem;color:var(--text-muted)"><?= e($ap['programa']) ?></td>
              <td style="text-align:center"><?= (int)$ap['total_ra'] ?></td>
              <td style="text-align:center;color:#34d399;font-weight:600"><?= (int)$ap['ra_aprobados'] ?></td>
              <td style="text-align:center;color:var(--text-muted)"><?= (int)$ap['ra_pendientes'] ?></td>
              <td style="min-width:120px"><?= barraProgreso((float)$ap['pct']) ?></td>
              <td>
                <a href="detalle.php?doc=<?= urlencode($ap['numero_documento']) ?>" class="je-btn je-btn-outline je-btn-sm" title="Ver detalle">
                  <i class="bi bi-eye-fill"></i>
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
