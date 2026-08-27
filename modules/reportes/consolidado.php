<?php
if (function_exists('opcache_reset')) opcache_reset();
$pageTitle = 'Reporte Consolidado';
require_once __DIR__ . '/../../includes/header.php';

$db = getDB();

$filtroPrograma = (int)($_GET['programa_id'] ?? 0);
$filtroFicha    = trim($_GET['ficha']        ?? '');
$filtroEstado   = trim($_GET['estado']       ?? '');

$where  = ['1=1'];
$params = [];
if ($filtroPrograma) { $where[] = 'p.id = ?';      $params[] = $filtroPrograma; }
if ($filtroFicha)    { $where[] = 'f.numero = ?';  $params[] = $filtroFicha;    }
if ($filtroEstado)   { $where[] = 'a.estado = ?';  $params[] = $filtroEstado;   }

$sql = "
    SELECT
        a.tipo_documento,
        a.numero_documento,
        a.nombre                                                           AS aprendiz_nombre,
        a.estado                                                           AS aprendiz_estado,
        f.numero                                                           AS ficha,
        p.nombre                                                           AS programa,
        COUNT(je.resultado_id)                                             AS total_ra,
        SUM(je.estado = 'Aprobado')                                        AS ra_aprobados,
        SUM(je.estado = 'Pendiente')                                       AS ra_pendientes,
        ROUND(IFNULL(SUM(je.estado='Aprobado')/NULLIF(COUNT(je.resultado_id),0)*100,0),1) AS pct,
        MAX(je.fecha_juicio)                                               AS ultimo_juicio,
        (SELECT je2.funcionario
           FROM juicios_evaluativos je2
          WHERE je2.numero_documento = a.numero_documento
            AND je2.fecha_juicio IS NOT NULL
          ORDER BY je2.fecha_juicio DESC, je2.id DESC
          LIMIT 1)                                                         AS ultimo_funcionario
    FROM aprendices a
    JOIN fichas    f  ON a.ficha_id    = f.id
    JOIN programas p  ON f.programa_id = p.id
    LEFT JOIN juicios_evaluativos je ON je.numero_documento = a.numero_documento
    WHERE " . implode(' AND ', $where) . "
    GROUP BY a.tipo_documento, a.numero_documento, a.nombre, a.estado,
             f.numero, p.nombre
    ORDER BY p.nombre, f.numero, a.nombre
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$aprendices = $stmt->fetchAll();

$totalAp   = count($aprendices);
$totalRA   = array_sum(array_column($aprendices, 'total_ra'));
$totalAp2  = array_sum(array_column($aprendices, 'ra_aprobados'));
$totalPend = array_sum(array_column($aprendices, 'ra_pendientes'));
$pctGlobal = $totalRA > 0 ? round($totalAp2 / $totalRA * 100, 1) : 0;

$programas = getProgramas();
$estados   = ['En formación', 'Retiro Voluntario', 'Trasladado'];
?>

<div class="consolidado-page">
  <div class="page-header consolidado-hero d-flex justify-content-between align-items-start gap-3 flex-wrap fade-up">
    <div>
      <h4><i class="bi bi-table me-2"></i>Reporte Consolidado</h4>
      <p>
        Cada aprendiz aparece <strong>una sola vez</strong> con el resumen de sus juicios evaluativos.
        <?php if ($filtroPrograma || $filtroFicha || $filtroEstado): ?>
          <span class="je-badge je-badge-cyan ms-2"><?= $totalAp ?> aprendices con filtro activo</span>
        <?php endif; ?>
      </p>
    </div>
    <a href="exportar.php?<?= http_build_query($_GET) ?>" class="je-btn je-btn-success">
      <i class="bi bi-file-earmark-excel"></i>Exportar Excel
    </a>
  </div>

  <div class="consolidado-stats mb-4 fade-up-2">
    <div>
      <div class="stat-card stat-violet">
        <div class="stat-icon"><i class="bi bi-people-fill"></i></div>
        <div>
          <div class="stat-num"><?= number_format($totalAp) ?></div>
          <div class="stat-label">Aprendices únicos</div>
        </div>
      </div>
    </div>
    <div>
      <div class="stat-card stat-cyan">
        <div class="stat-icon"><i class="bi bi-list-check"></i></div>
        <div>
          <div class="stat-num"><?= number_format($totalRA) ?></div>
          <div class="stat-label">Resultados de Aprendizaje evaluados</div>
        </div>
      </div>
    </div>
    <div>
      <div class="stat-card stat-green">
        <div class="stat-icon"><i class="bi bi-check2-circle"></i></div>
        <div>
          <div class="stat-num"><?= number_format($totalAp2) ?></div>
          <div class="stat-label">Resultados de Aprendizaje Aprobados</div>
        </div>
      </div>
    </div>
    <div>
      <div class="stat-card stat-amber">
        <div class="stat-icon"><i class="bi bi-graph-up-arrow"></i></div>
        <div>
          <div class="stat-num"><?= $pctGlobal ?>%</div>
          <div class="stat-label">Cumplimiento general</div>
        </div>
      </div>
    </div>
  </div>

  <div class="je-card consolidado-filter mb-4 fade-up-3">
    <div class="card-header">
      <i class="bi bi-funnel-fill"></i> Filtros del reporte
    </div>
    <div class="card-body">
      <form method="GET" class="row g-3 align-items-end">
        <div class="col-md-3">
          <label class="je-label">Programa</label>
          <select name="programa_id" class="je-input">
            <option value="">Todos los programas</option>
            <?php foreach ($programas as $p): ?>
              <option value="<?= $p['id'] ?>" <?= $filtroPrograma == $p['id'] ? 'selected' : '' ?>><?= e($p['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <label class="je-label">Ficha</label>
          <input type="text" name="ficha" class="je-input" placeholder="Nro. ficha" value="<?= e($filtroFicha) ?>">
        </div>
        <div class="col-md-3">
          <label class="je-label">Estado aprendiz</label>
          <select name="estado" class="je-input">
            <option value="">Todos</option>
            <?php foreach ($estados as $est): ?>
              <option value="<?= e($est) ?>" <?= $filtroEstado === $est ? 'selected' : '' ?>><?= e($est) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-auto filter-actions">
          <button type="submit" class="je-btn je-btn-primary je-btn-sm icon-only" title="Buscar" aria-label="Buscar">
            <i class="bi bi-search"></i>
          </button>
        </div>
      </form>
    </div>
  </div>

  <div class="je-card consolidado-table fade-up-4">
    <div class="card-header">
      <i class="bi bi-layout-text-window-reverse"></i> Resultados consolidados
    </div>
    <div class="card-body p-0">
      <?php if (empty($aprendices)): ?>
        <div class="empty-state">
          <i class="bi bi-inbox"></i>
          <p>No hay datos con los filtros actuales.</p>
        </div>
      <?php else: ?>
        <div class="table-responsive consolidado-scroll">
          <table class="je-table dt-table mb-0">
            <thead>
              <tr>
                <th>Documento</th>
                <th>Aprendiz</th>
                <th>Estado</th>
                <th>Ficha</th>
                <th>Programa</th>
                <th class="text-center">Resultados de Aprendizaje</th>
                <th class="text-center">Aprobados</th>
                <th class="text-center">Pendientes</th>
                <th style="min-width:130px">Cumplimiento</th>
                <th>Último juicio</th>
                <th>Funcionario</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($aprendices as $ap): ?>
              <tr>
                <td class="doc-cell">
                  <a href="../aprendices/detalle.php?doc=<?= urlencode($ap['numero_documento']) ?>" class="doc-link" title="Ver informacion del aprendiz">
                    <span class="je-badge je-badge-gray"><?= e($ap['tipo_documento']) ?></span><?= e($ap['numero_documento']) ?>
                  </a>
                </td>
                <td><?= e($ap['aprendiz_nombre']) ?></td>
                <td><?= badgeEstado($ap['aprendiz_estado']) ?></td>
                <td><?= e($ap['ficha']) ?></td>
                <td class="table-muted"><?= e($ap['programa']) ?></td>
                <td class="text-center"><?= (int)$ap['total_ra'] ?></td>
                <td class="text-center text-ok"><?= (int)$ap['ra_aprobados'] ?></td>
                <td class="text-center table-muted"><?= (int)$ap['ra_pendientes'] ?></td>
                <td><?= barraProgreso((float)$ap['pct']) ?></td>
                <td class="table-muted">
                  <?= $ap['ultimo_juicio'] ? date('d/m/Y', strtotime($ap['ultimo_juicio'])) : '-' ?>
                </td>
                <td class="table-muted"><?= $ap['ultimo_funcionario'] ? e($ap['ultimo_funcionario']) : '-' ?></td>
                <td>
                  <a href="../aprendices/detalle.php?doc=<?= urlencode($ap['numero_documento']) ?>" class="je-btn je-btn-outline je-btn-sm icon-only" title="Ver detalle">
                    <i class="bi bi-eye"></i>
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
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
