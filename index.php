<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';
$r = getResumenGlobal();
$programas = getProgramas();
$db        = getDB();
?>

<div class="page-header fade-up">
  <h1><i class="bi bi-grid-1x2-fill me-2" style="color:#a78bfa"></i>Panel General</h1>
  <p>Resumen general de seguimiento academico</p>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3 fade-up">
    <div class="stat-card stat-violet">
      <div class="stat-icon"><i class="bi bi-people-fill"></i></div>
      <div>
        <div class="stat-num"><?= number_format($r['total']) ?></div>
        <div class="stat-label">Total Aprendices</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3 fade-up-2">
    <div class="stat-card stat-cyan">
      <div class="stat-icon"><i class="bi bi-person-check-fill"></i></div>
      <div>
        <div class="stat-num"><?= number_format($r['formacion']) ?></div>
        <div class="stat-label">En Formacion</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3 fade-up-3">
    <div class="stat-card stat-red">
      <div class="stat-icon"><i class="bi bi-person-x-fill"></i></div>
      <div>
        <div class="stat-num"><?= number_format($r['retiro']) ?></div>
        <div class="stat-label">Retiro Voluntario</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3 fade-up-4">
    <div class="stat-card stat-amber">
      <div class="stat-icon"><i class="bi bi-person-dash-fill"></i></div>
      <div>
        <div class="stat-num"><?= number_format($r['traslado']) ?></div>
        <div class="stat-label">Trasladados</div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4 justify-content-center">
  <div class="col-6 col-lg-4 mx-auto fade-up">
    <div class="stat-card stat-green">
      <div class="stat-icon"><i class="bi bi-check-circle-fill"></i></div>
      <div>
        <div class="stat-num"><?= number_format($r['aprobados']) ?></div>
        <div class="stat-label">Resultados de Aprendizaje Aprobados</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-4 mx-auto fade-up-2">
    <div class="stat-card stat-indigo">
      <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
      <div>
        <div class="stat-num"><?= number_format($r['pendientes']) ?></div>
        <div class="stat-label">Pendientes por evaluar</div>
      </div>
    </div>
  </div>
</div>

<?php if (!empty($programas)): ?>
<div class="je-card fade-up">
  <div class="card-header">
    <i class="bi bi-book-fill" style="color:#a78bfa"></i> Programas registrados
  </div>
  <div class="card-body p-0">
    <table class="je-table">
      <thead>
        <tr>
          <th>Programa</th>
          <th>Codigo</th>
          <th style="text-align:center">Fichas</th>
          <th style="text-align:center">Aprendices</th>
          <th>Avance</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($programas as $p):
          $nFichas = (int)$db->prepare('SELECT COUNT(*) FROM fichas WHERE programa_id=?')->execute([$p['id']]) ?
                     $db->query("SELECT COUNT(*) FROM fichas WHERE programa_id={$p['id']}")->fetchColumn() : 0;
          $stmtAp  = $db->prepare('SELECT COUNT(*) FROM aprendices a JOIN fichas f ON a.ficha_id=f.id WHERE f.programa_id=?');
          $stmtAp->execute([$p['id']]);
          $nAp = (int)$stmtAp->fetchColumn();

          $stmtJ = $db->prepare("SELECT SUM(je.estado='Aprobado') ap, COUNT(*) tot FROM juicios_evaluativos je JOIN resultados_aprendizaje ra ON je.resultado_id=ra.id JOIN competencias c ON ra.competencia_id=c.id WHERE c.programa_id=?");
          $stmtJ->execute([$p['id']]);
          $jRow = $stmtJ->fetch();
          $pctP = $jRow['tot'] > 0 ? round($jRow['ap'] / $jRow['tot'] * 100, 1) : 0;
        ?>
        <tr>
          <td style="font-weight:600"><?= e($p['nombre']) ?></td>
          <td><span class="je-badge je-badge-gray"><?= e($p['codigo']) ?></span></td>
          <td style="text-align:center"><?= $nFichas ?></td>
          <td style="text-align:center"><?= $nAp ?></td>
          <td style="min-width:140px"><?= barraProgreso((float)$pctP) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
