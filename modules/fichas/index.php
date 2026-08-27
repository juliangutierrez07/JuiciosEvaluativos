<?php
$pageTitle = 'Buscar Ficha';
require_once __DIR__ . '/../../includes/header.php';

$db         = getDB();
$programas  = getProgramas();
$busqueda   = trim($_GET['q']            ?? '');
$programaId = (int)($_GET['programa_id'] ?? 0);

$fichas = [];
if ($busqueda || $programaId) {
    $where  = ['1=1']; $params = [];
    if ($busqueda)   { $where[] = 'f.numero LIKE ?'; $params[] = "%{$busqueda}%"; }
    if ($programaId) { $where[] = 'f.programa_id = ?'; $params[] = $programaId; }
    $sql  = 'SELECT f.*, p.nombre AS programa FROM fichas f JOIN programas p ON f.programa_id = p.id WHERE ' . implode(' AND ', $where) . ' ORDER BY f.numero';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $fichas = $stmt->fetchAll();
}
?>

<div class="page-header fade-up">
  <h1><i class="bi bi-journal-text me-2" style="color:#22d3ee"></i>Buscar Ficha</h1>
  <p>Busca una ficha para ver sus aprendices y el estado del programa.</p>
</div>

<!-- Búsqueda -->
<div class="je-card mb-4 fade-up-2">
  <div class="card-body">
    <form method="GET" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end">
      <div style="flex:1;min-width:180px">
        <label class="je-label">Número de ficha</label>
        <input type="text" name="q" class="je-input" placeholder="Ej: 2993456" value="<?= e($busqueda) ?>" style="width:100%">
      </div>
      <div style="flex:1;min-width:200px">
        <label class="je-label">Programa</label>
        <select name="programa_id" class="je-input" style="width:100%">
          <option value="">Todos los programas</option>
          <?php foreach ($programas as $p): ?>
            <option value="<?= $p['id'] ?>" <?= $programaId==$p['id']?'selected':'' ?>><?= e($p['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="display:flex;gap:8px">
        <button type="submit" class="je-btn je-btn-primary je-btn-sm"><i class="bi bi-search"></i> Buscar</button>
        <a href="index.php" class="je-btn je-btn-outline je-btn-sm"><i class="bi bi-x-circle"></i> Limpiar</a>
      </div>
    </form>
  </div>
</div>

<?php
function tarjetaFichaNew(array $f, array $estados, int $totalAp): void { ?>
<div class="je-card mb-3">
  <div class="card-body" style="padding:20px 24px">
    <div style="display:flex;align-items:center;flex-wrap:wrap;gap:20px">

      <!-- Ícono + número -->
      <div style="display:flex;align-items:center;gap:14px;flex:0 0 auto">
        <div style="width:52px;height:52px;background:rgba(6,182,212,.12);border-radius:14px;display:flex;align-items:center;justify-content:center;border:1px solid rgba(6,182,212,.25)">
          <i class="bi bi-journal-text" style="font-size:1.4rem;color:#22d3ee"></i>
        </div>
        <div>
          <div style="font-size:1.5rem;font-weight:800;color:#22d3ee;line-height:1"><?= e($f['numero']) ?></div>
          <div style="font-size:.76rem;color:var(--text-muted);margin-top:3px"><?= e($f['programa']) ?></div>
        </div>
      </div>

      <!-- Conteo -->
      <div style="text-align:center;flex:0 0 auto">
        <div style="font-size:1.6rem;font-weight:800;color:var(--text-main)"><?= $totalAp ?></div>
        <div style="font-size:.72rem;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:.06em">aprendices</div>
      </div>

      <!-- Badges estado -->
      <div style="display:flex;flex-wrap:wrap;gap:8px;flex:1">
        <?php
        $estadosDef = [
            'En formación'      => ['je-badge-violet', 'bi-person-check'],
            'Retiro Voluntario' => ['je-badge-red',    'bi-person-x'],
            'Trasladado'        => ['je-badge-amber',  'bi-person-dash'],
        ];
        foreach ($estadosDef as $est => [$cls, $icon]):
            $n = $estados[$est] ?? 0;
        ?>
          <span class="je-badge <?= $cls ?>" style="font-size:.78rem;padding:6px 14px">
            <i class="bi <?= $icon ?>"></i> <?= $n ?> <?= e($est) ?>
          </span>
        <?php endforeach; ?>
      </div>

      <!-- Botones -->
      <div style="display:flex;gap:8px;flex:0 0 auto">
        <a href="../aprendices/index.php?ficha_id=<?= $f['id'] ?>" class="je-btn je-btn-primary je-btn-sm">
          <i class="bi bi-people-fill"></i> Ver aprendices
        </a>
        <button type="button" class="je-btn je-btn-danger je-btn-sm"
                data-bs-toggle="modal" data-bs-target="#modalEliminar"
                data-ficha-id="<?= $f['id'] ?>"
                data-ficha-numero="<?= e($f['numero']) ?>"
                data-ficha-total="<?= $totalAp ?>">
          <i class="bi bi-trash"></i>
        </button>
      </div>
    </div>
  </div>
</div>
<?php }
?>

<!-- Resultados -->
<?php if ($busqueda || $programaId): ?>
  <?php if (empty($fichas)): ?>
    <div class="je-alert je-alert-info fade-up"><i class="bi bi-info-circle-fill alert-icon"></i><div>No se encontraron fichas con esos criterios.</div></div>
  <?php else: ?>
    <div class="fade-up-3">
      <?php foreach ($fichas as $f):
        $stmt2 = $db->prepare("SELECT estado, COUNT(*) AS total FROM aprendices WHERE ficha_id=? GROUP BY estado");
        $stmt2->execute([$f['id']]);
        $estadosF = $stmt2->fetchAll(PDO::FETCH_KEY_PAIR);
        tarjetaFichaNew($f, $estadosF, array_sum($estadosF));
      endforeach; ?>
    </div>
  <?php endif; ?>

<?php else: ?>
  <?php if (!empty($programas)): ?>
    <?php foreach ($programas as $p):
      $todasFichas = getFichasPorPrograma((int)$p['id']);
      if (empty($todasFichas)) continue;
    ?>
    <div class="mb-4 fade-up">
      <div style="font-size:.72rem;font-weight:600;color:var(--text-dim);text-transform:uppercase;letter-spacing:.1em;margin-bottom:12px">
        <i class="bi bi-book me-1"></i> <?= e($p['nombre']) ?>
      </div>
      <?php foreach ($todasFichas as $f):
        $f['programa'] = $p['nombre'];
        $stmt2 = $db->prepare("SELECT estado, COUNT(*) AS total FROM aprendices WHERE ficha_id=? GROUP BY estado");
        $stmt2->execute([$f['id']]);
        $estadosF = $stmt2->fetchAll(PDO::FETCH_KEY_PAIR);
        tarjetaFichaNew($f, $estadosF, array_sum($estadosF));
      endforeach; ?>
    </div>
    <?php endforeach; ?>
  <?php else: ?>
    <p style="text-align:center;color:var(--text-muted);padding:4rem 0">No hay fichas registradas aún. Importa un Excel para comenzar.</p>
  <?php endif; ?>
<?php endif; ?>

<!-- Modal eliminar -->
<div class="modal fade" id="modalEliminar" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header" style="background:rgba(239,68,68,.1);border-color:rgba(239,68,68,.3)">
        <h5 class="modal-title" style="color:#f87171"><i class="bi bi-exclamation-triangle-fill me-2"></i>Eliminar ficha</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" style="padding:24px">
        <p style="margin-bottom:8px;color:var(--text-main)">¿Estás seguro de eliminar la <strong id="modalFichaNro"></strong>?</p>
        <p style="color:#f87171;font-size:.85rem;margin:0">Esta acción eliminará también <strong id="modalFichaTotal"></strong> aprendices y todos sus juicios. <u>No se puede deshacer.</u></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="je-btn je-btn-outline" data-bs-dismiss="modal">Cancelar</button>
        <form method="POST" action="eliminar.php" style="display:inline">
          <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
          <input type="hidden" name="ficha_id" id="modalFichaId">
          <button type="submit" class="je-btn je-btn-danger"><i class="bi bi-trash-fill"></i> Sí, eliminar todo</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
document.getElementById('modalEliminar').addEventListener('show.bs.modal', function(e) {
  const btn = e.relatedTarget;
  document.getElementById('modalFichaId').value       = btn.dataset.fichaId;
  document.getElementById('modalFichaNro').textContent = 'Ficha ' + btn.dataset.fichaNumero;
  document.getElementById('modalFichaTotal').textContent = btn.dataset.fichaTotal;
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
