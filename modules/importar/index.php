<?php
$pageTitle = 'Importar Excel';
require_once __DIR__ . '/../../includes/header.php';
$db = getDB();
?>

<div class="import-page">
  <div class="page-header import-header fade-up">
    <h1><i class="bi bi-cloud-upload-fill me-2" style="color:#a78bfa"></i>Importar Excel</h1>
    <p>El sistema detecta automáticamente el programa y la ficha desde el archivo.</p>
  </div>

  <div class="row g-4 justify-content-center">
    <div class="col-lg-8 col-xl-7 fade-up">
      <div class="je-card import-card">
        <div class="card-header"><i class="bi bi-upload"></i> Subir archivo</div>
        <div class="card-body">
          <form action="procesar.php" method="POST" enctype="multipart/form-data">
            <label class="je-label">Archivo Excel <span style="color:#f87171">*</span></label>
            <div class="drop-zone mb-4">
              <i class="dz-icon bi bi-cloud-upload"></i>
              <p class="fw-600" style="color:var(--text-main);font-weight:600">Arrastra tu archivo aquí o haz clic para buscar</p>
              <p class="dz-filename">Ningún archivo seleccionado</p>
            </div>
            <input type="file" id="archivoExcel" name="archivoExcel" class="d-none" accept=".xlsx,.xls" required>
            <button type="submit" class="je-btn je-btn-primary w-100" style="justify-content:center;padding:13px">
              <i class="bi bi-cloud-upload-fill"></i> Cargue archivo
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
