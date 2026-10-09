<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';

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
    iniciarSesion();
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    iniciarSesion();
    if (!isset($_SESSION['flash'])) return null;
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $f;
}

// ── Protección de formularios sensibles ───────────────────────────────────────────────

function csrfToken(): string {
    static $token = null;
    if ($token !== null) return $token;

    $cookieToken = $_COOKIE['csrf_token'] ?? '';
    if (is_string($cookieToken) && preg_match('/^[a-f0-9]{64}$/', $cookieToken)) {
        return $token = $cookieToken;
    }

    $token = bin2hex(random_bytes(32));
    setcookie('csrf_token', $token, [
        'expires' => time() + 86400,
        'path' => '/',
        'secure' => conexionSegura(),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);

    return $token;
}

function csrfValido(?string $token): bool {
    $cookieToken = $_COOKIE['csrf_token'] ?? null;
    return is_string($token)
        && is_string($cookieToken)
        && preg_match('/^[a-f0-9]{64}$/', $token)
        && hash_equals($cookieToken, $token);
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

// Sofía Plus registra al evaluador como "CC 123456 - NOMBRE"; se muestra solo el nombre.
function nombreFuncionario(string $funcionario): string {
    return preg_replace('/^[A-Z]{1,3}\s*\d+\s*-\s*/u', '', trim($funcionario));
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
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '');
    $posModulo = strpos($script, '/modules/');

    if ($posModulo !== false) {
        // Funciona con la app en un subdirectorio y también en la raíz del dominio.
        $raiz = substr($script, 0, $posModulo);
    } else {
        $directorio = str_replace('\\', '/', dirname($script));
        $raiz = $directorio === '/' || $directorio === '.' ? '' : rtrim($directorio, '/');
    }

    return ($raiz ?: '') . '/' . ltrim($path, '/');
}
