/* Sistema JE – Scripts globales (nuevo diseño) */

$(function () {

  /* ── DataTables dark ──────────────────────────────────── */
  if ($.fn.DataTable) {
    $('.dt-table').DataTable({
      language: { url: 'https://cdn.datatables.net/plug-ins/1.13.8/i18n/es-ES.json' },
      pageLength: 25,
      responsive: true,
      dom: '<"dt-top"lf>rt<"dt-bottom"ip>',
    });
  }

  /* ── Drag & Drop zona de carga ────────────────────────── */
  const zone  = document.querySelector('.drop-zone');
  const input = document.getElementById('archivoExcel');

  if (zone && input) {
    zone.addEventListener('click', () => input.click());
    zone.addEventListener('dragover',  e => { e.preventDefault(); zone.classList.add('is-dragover'); });
    zone.addEventListener('dragleave', ()  => zone.classList.remove('is-dragover'));
    zone.addEventListener('drop', e => {
      e.preventDefault();
      zone.classList.remove('is-dragover');
      if (e.dataTransfer.files.length) {
        setFile(e.dataTransfer.files[0]);
        const dt = new DataTransfer();
        dt.items.add(e.dataTransfer.files[0]);
        input.files = dt.files;
      }
    });
    input.addEventListener('change', function () {
      if (this.files[0]) setFile(this.files[0]);
    });
    function setFile(file) {
      zone.classList.add('has-file');
      zone.querySelector('.dz-icon').className = 'dz-icon bi bi-file-earmark-excel-fill';
      zone.querySelector('.dz-filename').textContent = file.name;
    }
  }

  /* ── Sidebar toggle móvil ─────────────────────────────── */
  const toggle  = document.getElementById('sidebar-toggle');
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('sidebar-overlay');

  function checkMobile() {
    if (window.innerWidth <= 768) {
      toggle && (toggle.style.display = 'flex');
    } else {
      toggle && (toggle.style.display = 'none');
      sidebar && sidebar.classList.remove('open');
      overlay && overlay.classList.remove('show');
    }
  }
  checkMobile();
  window.addEventListener('resize', checkMobile);

  toggle && toggle.addEventListener('click', () => {
    sidebar.classList.toggle('open');
    overlay.classList.toggle('show');
  });
  overlay && overlay.addEventListener('click', () => {
    sidebar.classList.remove('open');
    overlay.classList.remove('show');
  });

  /* ── Animación de barras de progreso ──────────────────── */
  document.querySelectorAll('.je-progress-bar').forEach(bar => {
    const w = bar.style.width;
    bar.style.width = '0';
    setTimeout(() => { bar.style.width = w; }, 150);
  });

  /* ── Confirmación eliminar ────────────────────────────── */
  document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', function (e) {
      if (!confirm(this.dataset.confirm || '¿Confirmar esta acción?')) e.preventDefault();
    });
  });

});
