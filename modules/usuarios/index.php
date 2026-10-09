<?php
$pageTitle = 'Usuarios';
$rolRequerido = 'admin';
require_once __DIR__ . '/../../includes/header.php';

$usuarios = getDB()->query(
    'SELECT u.id, u.usuario, u.nombre, u.rol, u.activo, u.debe_cambiar, u.ultimo_acceso,
            COUNT(i.id) AS cargas
     FROM usuarios u
     LEFT JOIN importaciones i ON i.usuario_id = u.id
     GROUP BY u.id, u.usuario, u.nombre, u.rol, u.activo, u.debe_cambiar, u.ultimo_acceso
     ORDER BY u.activo DESC, u.rol, u.nombre'
)->fetchAll();
?>

<div class="page-header fade-up">
  <h1><i class="bi bi-person-badge-fill me-2" style="color:#a78bfa"></i>Usuarios</h1>
  <p>Administradores e instructores que pueden ingresar al sistema.</p>
</div>

<div class="row g-4">
  <div class="col-xl-4 fade-up">
    <div class="je-card">
      <div class="card-header"><i class="bi bi-person-plus-fill"></i> Nuevo usuario</div>
      <div class="card-body">
        <form method="POST" action="accion.php">
          <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
          <input type="hidden" name="accion" value="crear">
          <div class="mb-3">
            <label class="je-label" for="nombre">Nombre completo</label>
            <input class="je-input w-100" id="nombre" name="nombre" maxlength="255" required>
          </div>
          <div class="mb-3">
            <label class="je-label" for="usuario">Usuario para ingresar</label>
            <input class="je-input w-100" id="usuario" name="usuario" maxlength="60"
                   pattern="[A-Za-z0-9._\-]{3,60}" title="3 a 60 caracteres: letras, números, punto, guion o guion bajo" required>
          </div>
          <div class="mb-3">
            <label class="je-label" for="rol">Rol</label>
            <select class="je-input w-100" id="rol" name="rol">
              <option value="instructor">Instructor: carga y consulta juicios</option>
              <option value="admin">Administrador: además elimina fichas y gestiona usuarios</option>
            </select>
          </div>
          <div class="mb-4">
            <label class="je-label" for="clave">Contraseña temporal</label>
            <input class="je-input w-100" type="password" id="clave" name="clave" minlength="8" autocomplete="new-password" required>
          </div>
          <button type="submit" class="je-btn je-btn-primary w-100" style="justify-content:center">
            <i class="bi bi-check-lg"></i> Crear usuario
          </button>
          <p class="auth-hint">El usuario deberá cambiar la contraseña temporal en su primer ingreso.</p>
        </form>
      </div>
    </div>
  </div>

  <div class="col-xl-8 fade-up-2">
    <div class="je-card">
      <div class="card-body p-0">
        <div style="overflow-x:auto">
          <table class="je-table mb-0">
            <thead>
              <tr>
                <th>Nombre</th><th>Usuario</th><th>Rol</th><th>Estado</th>
                <th style="text-align:center">Cargas</th><th>Último acceso</th><th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($usuarios as $u):
                $esYo = (string)$u['id'] === $usuarioSesion['id']; ?>
              <tr style="<?= $u['activo'] ? '' : 'opacity:.55' ?>">
                <td style="font-weight:500"><?= e($u['nombre']) ?><?= $esYo ? ' <span class="table-muted">(tú)</span>' : '' ?></td>
                <td><?= e($u['usuario']) ?></td>
                <td>
                  <span class="je-badge <?= $u['rol'] === 'admin' ? 'je-badge-violet' : 'je-badge-cyan' ?>"><?= e(nombreRol($u['rol'])) ?></span>
                </td>
                <td>
                  <?php if (!$u['activo']): ?>
                    <span class="je-badge je-badge-red">Inactivo</span>
                  <?php elseif ($u['debe_cambiar']): ?>
                    <span class="je-badge je-badge-amber">Clave temporal</span>
                  <?php else: ?>
                    <span class="je-badge je-badge-green">Activo</span>
                  <?php endif; ?>
                </td>
                <td style="text-align:center"><?= (int)$u['cargas'] ?></td>
                <td class="table-muted"><?= $u['ultimo_acceso'] ? date('d/m/Y H:i', strtotime($u['ultimo_acceso'])) : 'Nunca' ?></td>
                <td>
                  <?php if (!$esYo): ?>
                  <div style="display:flex;gap:6px;justify-content:flex-end">
                    <form method="POST" action="accion.php">
                      <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                      <input type="hidden" name="accion" value="restablecer">
                      <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                      <button type="submit" class="je-btn je-btn-outline je-btn-sm" title="Restablecer contraseña"
                              data-confirm="Se generará una contraseña temporal para <?= e($u['nombre']) ?>. ¿Continuar?">
                        <i class="bi bi-key-fill"></i>
                      </button>
                    </form>
                    <form method="POST" action="accion.php">
                      <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                      <input type="hidden" name="accion" value="<?= $u['activo'] ? 'desactivar' : 'activar' ?>">
                      <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                      <?php if ($u['activo']): ?>
                        <button type="submit" class="je-btn je-btn-danger je-btn-sm" title="Desactivar"
                                data-confirm="<?= e($u['nombre']) ?> no podrá volver a ingresar. ¿Continuar?">
                          <i class="bi bi-person-x-fill"></i>
                        </button>
                      <?php else: ?>
                        <button type="submit" class="je-btn je-btn-success je-btn-sm" title="Activar">
                          <i class="bi bi-person-check-fill"></i>
                        </button>
                      <?php endif; ?>
                    </form>
                  </div>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
