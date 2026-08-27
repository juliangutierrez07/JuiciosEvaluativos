<?php
require_once __DIR__ . '/../config/database.php';

// ── Helpers de salida ────────────────────────────────────────────────────────

function e(string $v): string {
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

// ── Mensajes flash ───────────────────────────────────────────────────────────

function flash(string $type, string $message): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['flash'])) return null;
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $f;
}

// ── Protección de formularios sensibles ───────────────────────────────────────────────

function csrfToken(): string {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfValido(?string $token): bool {
    if (session_status() === PHP_SESSION_NONE) session_start();
    return isset($_SESSION['csrf_token'])
        && is_string($token)
        && hash_equals($_SESSION['csrf_token'], $token);
}

// ── Consultas frecuentes ─────────────────────────────────────────────────────

function getProgramas(): array {
    return getDB()->query('SELECT * FROM programas ORDER BY nombre')->fetchAll();
}

function getFichasPorPrograma(int $programaId): array {
    $s = getDB()->prepare('SELECT * FROM fichas WHERE programa_id = ? ORDER BY numero');
    $s->execute([$programaId]);
    return $s->fetchAll();
}

// ── Dashboard: resumen global ────────────────────────────────────────────────

function getResumenGlobal(): array {
    $db = getDB();
    $total     = (int)$db->query('SELECT COUNT(*) FROM aprendices')->fetchColumn();
    $formacion = (int)$db->query("SELECT COUNT(*) FROM aprendices WHERE estado='En formación'")->fetchColumn();
    $retiro    = (int)$db->query("SELECT COUNT(*) FROM aprendices WHERE estado='Retiro Voluntario'")->fetchColumn();
    $traslado  = (int)$db->query("SELECT COUNT(*) FROM aprendices WHERE estado='Trasladado'")->fetchColumn();
    $aprobados = (int)$db->query("SELECT COUNT(*) FROM juicios_evaluativos WHERE estado='Aprobado'")->fetchColumn();
    $pendientes= (int)$db->query("SELECT COUNT(*) FROM juicios_evaluativos WHERE estado='Pendiente'")->fetchColumn();

    return compact('total','formacion','retiro','traslado','aprobados','pendientes');
}

// ── Badges visuales (por color según diagrama) ───────────────────────────────

function badgeEstado(string $estado): string {
    $clases = [
        'En formación'     => 'je-badge je-badge-violet',
        'Retiro Voluntario'=> 'je-badge je-badge-red',
        'Trasladado'       => 'je-badge je-badge-amber',
    ];
    $c = $clases[$estado] ?? 'je-badge je-badge-gray';
    return '<span class="' . $c . '">' . e($estado) . '</span>';
}

function badgeJuicio(string $estado): string {
    $c = $estado === 'Aprobado' ? 'je-badge je-badge-green' : 'je-badge je-badge-gray';
    return '<span class="' . $c . '">' . e($estado) . '</span>';
}

function barraProgreso(float $pct): string {
    $color = $pct >= 75 ? 'green' : ($pct >= 40 ? 'amber' : 'red');
    return <<<HTML
    <div class="progress-pill {$color}" title="{$pct}% completado">
      <span class="progress-dot"></span>
      <strong>{$pct}%</strong>
    </div>
    HTML;
}

// ── Ruta base dinámica ────────────────────────────────────────────────────────

function base(string $path = ''): string {
    $depth = substr_count(str_replace('\\', '/', $_SERVER['PHP_SELF']), '/') - 2;
    return str_repeat('../', max(0, $depth)) . ltrim($path, '/');
}
