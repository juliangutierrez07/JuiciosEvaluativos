<?php
$pageTitle = 'Mapa de calor';
require_once __DIR__ . '/../../includes/header.php';

$db = getDB();
$programas = getProgramas();
$programaId = (int)($_GET['programa_id'] ?? 0);
$fichaId = (int)($_GET['ficha_id'] ?? 0);

if (!$programaId && $programas) {
    foreach ($programas as $programa) {
        $stmt = $db->prepare('SELECT COUNT(*) FROM competencias WHERE programa_id = ?');
        $stmt->execute([$programa['id']]);
        if ((int)$stmt->fetchColumn() > 0) {
            $programaId = (int)$programa['id'];
            break;
        }
    }
}

$fichas = $programaId ? getFichasPorPrograma($programaId) : [];
if ($fichaId && !in_array($fichaId, array_map(fn($f) => (int)$f['id'], $fichas), true)) $fichaId = 0;

$competencias = $aprendices = $resultados = [];
if ($programaId) {
    $stmt = $db->prepare('SELECT id, nombre FROM competencias WHERE programa_id = ? ORDER BY nombre');
    $stmt->execute([$programaId]);
    $competencias = $stmt->fetchAll();

    $filtroFicha = $fichaId ? ' AND f.id = ?' : '';
    $params = $fichaId ? [$programaId, $fichaId] : [$programaId];
    $stmt = $db->prepare("SELECT a.numero_documento, a.nombre, a.estado, f.numero AS ficha
        FROM aprendices a JOIN fichas f ON f.id=a.ficha_id
        WHERE f.programa_id=?{$filtroFicha} ORDER BY f.numero,a.nombre");
    $stmt->execute($params);
    $aprendices = $stmt->fetchAll();

    $stmt = $db->prepare("SELECT je.numero_documento,c.id competencia_id,COUNT(*) total,
        SUM(je.estado='Aprobado') aprobados
        FROM juicios_evaluativos je
        JOIN resultados_aprendizaje ra ON ra.id=je.resultado_id
        JOIN competencias c ON c.id=ra.competencia_id
        JOIN aprendices a ON a.numero_documento=je.numero_documento
        JOIN fichas f ON f.id=a.ficha_id
        WHERE c.programa_id=? AND f.programa_id=?" . ($fichaId ? ' AND f.id=?' : '') . "
        GROUP BY je.numero_documento,c.id");
    $stmt->execute($fichaId ? [$programaId,$programaId,$fichaId] : [$programaId,$programaId]);
    foreach ($stmt->fetchAll() as $fila) {
        $resultados[$fila['numero_documento']][(int)$fila['competencia_id']] = [
            'total'=>(int)$fila['total'], 'aprobados'=>(int)$fila['aprobados']
        ];
    }
}

$conteo = ['verde'=>0,'amarillo'=>0,'rojo'=>0,'sin_datos'=>0];
foreach ($aprendices as $aprendiz) foreach ($competencias as $competencia) {
    $dato = $resultados[$aprendiz['numero_documento']][(int)$competencia['id']] ?? null;
    if (!$dato || !$dato['total']) { $conteo['sin_datos']++; continue; }
    $pct = $dato['aprobados']/$dato['total']*100;
    $conteo[$pct>=100?'verde':($pct>=50?'amarillo':'rojo')]++;
}
?>

<div class="page-header heatmap-heading fade-up">
  <h1><i class="bi bi-grid-3x3-gap-fill me-2"></i>Mapa de calor por competencias</h1>
  <p>Identifica fortalezas y competencias que requieren acompañamiento.</p>
</div>

<div class="je-card heatmap-filters mb-4 fade-up-2"><div class="card-body">
  <form method="GET" class="row g-3 align-items-end">
    <div class="col-lg-6"><label class="je-label" for="programa_id">Programa</label>
      <select class="je-input" id="programa_id" name="programa_id" onchange="this.form.ficha_id.value='';this.form.submit()">
        <?php if (!$programas): ?><option value="">No hay programas registrados</option><?php endif; ?>
        <?php foreach ($programas as $programa): ?><option value="<?= (int)$programa['id'] ?>" <?= $programaId===(int)$programa['id']?'selected':'' ?>><?= e($programa['nombre']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-lg-4"><label class="je-label" for="ficha_id">Ficha</label>
      <select class="je-input" id="ficha_id" name="ficha_id"><option value="">Todas las fichas</option>
        <?php foreach ($fichas as $ficha): ?><option value="<?= (int)$ficha['id'] ?>" <?= $fichaId===(int)$ficha['id']?'selected':'' ?>><?= e($ficha['numero']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-lg-2"><button class="je-btn je-btn-primary w-100" type="submit"><i class="bi bi-funnel-fill"></i> Aplicar</button></div>
  </form>
</div></div>

<div class="heatmap-summary fade-up-3">
  <div class="heatmap-stat green"><strong><?= number_format($conteo['verde']) ?></strong><span>Completadas</span></div>
  <div class="heatmap-stat yellow"><strong><?= number_format($conteo['amarillo']) ?></strong><span>En progreso</span></div>
  <div class="heatmap-stat red"><strong><?= number_format($conteo['rojo']) ?></strong><span>Críticas</span></div>
  <div class="heatmap-stat empty"><strong><?= number_format($conteo['sin_datos']) ?></strong><span>Sin datos</span></div>
</div>

<div class="heatmap-legend fade-up-3">
  <span><i class="legend-dot green"></i>100% aprobado</span><span><i class="legend-dot yellow"></i>50–99%</span>
  <span><i class="legend-dot red"></i>Menos del 50%</span><span><i class="legend-dot empty"></i>Sin resultados</span>
</div>

<div class="je-card heatmap-card fade-up-4">
<?php if (!$competencias): ?><div class="empty-state"><i class="bi bi-grid"></i><p>El programa no tiene competencias registradas.</p></div>
<?php elseif (!$aprendices): ?><div class="empty-state"><i class="bi bi-people"></i><p>No hay aprendices para los filtros seleccionados.</p></div>
<?php else: ?><div class="heatmap-scroll"><table class="heatmap-table"><thead><tr>
  <th class="sticky-col learner-col">Aprendiz</th><th class="sticky-col ficha-col">Ficha</th>
  <?php foreach ($competencias as $i=>$competencia): ?><th title="<?= e($competencia['nombre']) ?>"><span>C<?= $i+1 ?></span></th><?php endforeach; ?>
</tr></thead><tbody>
<?php foreach ($aprendices as $aprendiz): ?><tr>
  <td class="sticky-col learner-col"><a href="<?= base('modules/aprendices/detalle.php?doc='.urlencode($aprendiz['numero_documento'])) ?>"><?= e($aprendiz['nombre']) ?></a><small><?= e($aprendiz['numero_documento']) ?></small></td>
  <td class="sticky-col ficha-col"><span class="je-badge je-badge-gray"><?= e($aprendiz['ficha']) ?></span></td>
  <?php foreach ($competencias as $competencia):
    $dato=$resultados[$aprendiz['numero_documento']][(int)$competencia['id']]??null;
    $pct=$dato&&$dato['total']?round($dato['aprobados']/$dato['total']*100):null;
    $clase=$pct===null?'empty':($pct>=100?'green':($pct>=50?'yellow':'red'));
    $detalle=$dato?"{$dato['aprobados']} de {$dato['total']} aprobados":'Sin resultados registrados'; ?>
    <td class="heat-cell <?= $clase ?>" title="<?= e($competencia['nombre'].' · '.$detalle) ?>"><?= $pct===null?'—':$pct.'%' ?></td>
  <?php endforeach; ?>
</tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?>
</div>

<?php if ($competencias): ?><div class="je-card competence-key mt-4 fade-up-4">
  <div class="card-header"><i class="bi bi-list-check"></i> Referencia de competencias</div><div class="card-body"><div class="competence-key-grid">
  <?php foreach ($competencias as $i=>$competencia): ?><div><strong>C<?= $i+1 ?></strong><span><?= e($competencia['nombre']) ?></span></div><?php endforeach; ?>
  </div></div>
</div><?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
