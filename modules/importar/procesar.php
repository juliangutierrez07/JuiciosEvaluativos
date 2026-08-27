<?php
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('index.php');

if (empty($_FILES['archivoExcel']['tmp_name'])) {
    flash('danger', 'No se recibió ningún archivo.');
    redirect('index.php');
}

// ── PHPSpreadsheet ───────────────────────────────────────────────────────────
$autoload = __DIR__ . '/../../vendor/autoload.php';
if (!file_exists($autoload)) {
    flash('danger', 'PHPSpreadsheet no instalado. Ejecuta <code>composer install</code> en la raíz del proyecto.');
    redirect('index.php');
}
require_once $autoload;

use PhpOffice\PhpSpreadsheet\IOFactory;

try {
    $spreadsheet = IOFactory::load($_FILES['archivoExcel']['tmp_name']);
} catch (Throwable $e) {
    flash('danger', 'No se pudo leer el Excel: ' . $e->getMessage());
    redirect('index.php');
}

$filas        = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
$originalName = basename($_FILES['archivoExcel']['name']);

// ── Auto-detectar fila de encabezados ────────────────────────────────────────
$idxEncabezado = autoDetectarFilaEncabezado($filas); // índice 0-based de la fila header

// ── Auto-detectar Programa y Ficha desde las filas superiores ────────────────
['programa' => $programaNombre, 'ficha' => $fichaNro] = detectarProgramaYFicha($filas, $idxEncabezado);

if (!$programaNombre) {
    flash('danger', 'No se encontró el nombre del programa en el Excel. Asegúrate de que alguna celda contenga la palabra <strong>Denominación</strong> o <strong>Programa</strong> seguida del nombre.');
    redirect('index.php');
}
if (!$fichaNro) {
    flash('danger', 'No se encontró el número de ficha en el Excel. Asegúrate de que alguna celda contenga la palabra <strong>Ficha</strong> seguida del número.');
    redirect('index.php');
}

// ── Detectar columnas por encabezado ─────────────────────────────────────────
$encabezados = array_values($filas[$idxEncabezado] ?? []);
$col = mapearColumnas($encabezados);

$columnasRequeridas = [
    'num_doc'      => 'Número de documento',
    'nombre'       => 'Nombre',
    'competencia'  => 'Competencia',
    'resultado_ra' => 'Resultado de aprendizaje',
    'juicio'       => 'Juicio evaluativo',
];
$faltantes = [];
foreach ($columnasRequeridas as $campo => $etiqueta) {
    if ($col[$campo] === null) $faltantes[] = $etiqueta;
}
if ($faltantes) {
    flash('danger', 'El Excel no contiene estas columnas obligatorias: <strong>' . e(implode(', ', $faltantes)) . '</strong>. Revisa la fila de encabezados.');
    redirect('index.php');
}

// ── Preparar BD ──────────────────────────────────────────────────────────────
$db = getDB();

// Crear o recuperar programa
$db->prepare('INSERT IGNORE INTO programas (nombre, codigo) VALUES (?,?)')->execute([$programaNombre, generarCodigo($programaNombre)]);
$stmtProg = $db->prepare('SELECT id FROM programas WHERE nombre = ?');
$stmtProg->execute([$programaNombre]);
$programaId = (int)$stmtProg->fetchColumn();

// Crear o recuperar ficha
$stmt = $db->prepare('SELECT id FROM fichas WHERE numero = ?');
$stmt->execute([$fichaNro]);
$ficha = $stmt->fetch();
if ($ficha) {
    $fichaId = (int)$ficha['id'];
} else {
    $db->prepare('INSERT INTO fichas (programa_id, numero) VALUES (?,?)')->execute([$programaId, $fichaNro]);
    $fichaId = (int)$db->lastInsertId();
}

// Sentencias preparadas reutilizables
$insAprendiz = $db->prepare(
    'INSERT INTO aprendices (numero_documento, ficha_id, tipo_documento, nombre, estado)
     VALUES (?,?,?,?,?)
     ON DUPLICATE KEY UPDATE estado=VALUES(estado), nombre=VALUES(nombre), ficha_id=VALUES(ficha_id)'
);

$insComp = $db->prepare('INSERT IGNORE INTO competencias (programa_id, nombre) VALUES (?,?)');
$getComp = $db->prepare('SELECT id FROM competencias WHERE nombre=? AND programa_id=?');

$insRA = $db->prepare('INSERT IGNORE INTO resultados_aprendizaje (competencia_id, descripcion) VALUES (?,?)');
$getRA = $db->prepare('SELECT id FROM resultados_aprendizaje WHERE descripcion=? AND competencia_id=?');

$insJuicio = $db->prepare(
    'INSERT INTO juicios_evaluativos (numero_documento, resultado_id, estado, fecha_juicio, funcionario)
     VALUES (?,?,?,?,?)
     ON DUPLICATE KEY UPDATE estado=VALUES(estado), fecha_juicio=VALUES(fecha_juicio), funcionario=VALUES(funcionario)'
);

// ── Procesar fila a fila (desde la fila siguiente al encabezado) ──────────────
$totalFilas = 0;
$procesadas = 0;
$omitidas   = 0;

$db->beginTransaction();
try {
    foreach ($filas as $i => $row) {
        if ($i <= $idxEncabezado) continue; // saltar encabezado y filas anteriores
        $row = array_values($row);
        if (esFilaVacia($row)) continue;

        $totalFilas++;

        $tipDoc    = normalizarTipoDoc(trim((string)($row[$col['tipo_doc']]    ?? '')));
        $numDoc    = trim((string)($row[$col['num_doc']]                        ?? ''));
        $nombre    = mb_strtoupper(trim((string)($row[$col['nombre']]           ?? '')));
        $apellidos = mb_strtoupper(trim((string)($col['apellidos'] !== null ? ($row[$col['apellidos']] ?? '') : '')));
        $nombreCompleto = trim($nombre . ($apellidos ? ' ' . $apellidos : ''));
        $estado    = normalizarEstado(trim((string)($row[$col['estado']]        ?? '')));
        $comp      = trim((string)($row[$col['competencia']]                    ?? '')) ?: 'Sin especificar';
        $raDesc    = trim((string)($row[$col['resultado_ra']]                   ?? ''));
        $juicio    = normalizarJuicio(trim((string)($row[$col['juicio']]        ?? '')));
        $fecha     = parsearFecha(trim((string)($row[$col['fecha']]             ?? '')));
        $func      = trim((string)($row[$col['funcionario']]                    ?? ''));

        if (!$numDoc || !$nombreCompleto || !$raDesc) { $omitidas++; continue; }

        if ($juicio !== 'Aprobado') { $fecha = null; $func = null; }

        // Aprendiz (numero_documento es PK)
        $insAprendiz->execute([$numDoc, $fichaId, $tipDoc, $nombreCompleto, $estado]);

        // Competencia
        $insComp->execute([$programaId, $comp]);
        $getComp->execute([$comp, $programaId]);
        $compId = (int)$getComp->fetchColumn();

        // Resultado de Aprendizaje
        $insRA->execute([$compId, $raDesc]);
        $getRA->execute([$raDesc, $compId]);
        $raId = (int)$getRA->fetchColumn();

        // Juicio
        $insJuicio->execute([$numDoc, $raId, $juicio, $fecha, $func ?: null]);
        $procesadas++;
    }
    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    flash('danger', 'Error durante la importación: ' . $e->getMessage());
    redirect('index.php');
}

// ── Log ──────────────────────────────────────────────────────────────────────
$db->prepare(
    'INSERT INTO importaciones (nombre_archivo, programa_id, ficha_id, total_filas, filas_procesadas, filas_omitidas, estado)
     VALUES (?,?,?,?,?,?,?)'
)->execute([
    $originalName, $programaId, $fichaId, $totalFilas, $procesadas, $omitidas,
    $omitidas > 0 ? 'Con errores' : 'Exitoso'
]);

flash('success', "Importación completada — Programa: <strong>" . htmlspecialchars($programaNombre) . "</strong> · Ficha: <strong>{$fichaNro}</strong> · <strong>{$procesadas}</strong> registros guardados, <strong>{$omitidas}</strong> omitidos de <strong>{$totalFilas}</strong> filas leídas.");
redirect('index.php');

// ════════════════════════════════════════════════════════════════════════════
// FUNCIONES AUXILIARES
// ════════════════════════════════════════════════════════════════════════════

function autoDetectarFilaEncabezado(array $filas): int {
    $keywords = ['tipo','numero','nombre','apellido','estado','competencia','resultado','juicio','fecha','funcionario','documento','identificacion'];
    $mejorIdx   = 0;
    $mejorScore = 0;

    foreach ($filas as $i => $row) {
        $score = 0;
        foreach ($row as $cell) {
            $v = quitarTildes(mb_strtolower(trim((string)$cell)));
            foreach ($keywords as $kw) {
                if (str_contains($v, $kw)) { $score++; break; }
            }
        }
        if ($score > $mejorScore) {
            $mejorScore = $score;
            $mejorIdx   = $i;
        }
        if ($i > 30) break; // no escanear más allá de la fila 30
    }

    return $mejorIdx; // 0-based
}

function detectarProgramaYFicha(array $filas, int $idxEncabezado): array {
    $programa = '';
    $ficha    = '';
    $limite   = min($idxEncabezado + 1, count($filas));

    for ($i = 0; $i < $limite; $i++) {
        $row = array_values($filas[$i]);

        foreach ($row as $j => $cell) {
            $celda    = trim((string)$cell);
            $celdaLow = quitarTildes(mb_strtolower($celda));

            // ── Ficha ─────────────────────────────────────────────────────────
            if (!$ficha && (str_contains($celdaLow, 'ficha') || str_contains($celdaLow, 'grupo'))) {
                if (preg_match('/\b(\d{4,8})\b/', $celda, $m)) {
                    $ficha = $m[1];
                }
                if (!$ficha) {
                    for ($k = $j + 1; $k < count($row); $k++) {
                        $v = trim((string)($row[$k] ?? ''));
                        if ($v !== '' && preg_match('/^\d{4,8}$/', $v)) { $ficha = $v; break; }
                    }
                }
            }

            // ── Programa / Denominación ───────────────────────────────────────
            if (!$programa && (str_contains($celdaLow, 'programa') || str_contains($celdaLow, 'denominaci'))) {
                $sinPrefijo = trim(preg_replace('/^(programa|denominaci[oó]n(\s+de\s+formaci[oó]n)?)[:\s\-]*/iu', '', $celda));
                if (mb_strlen($sinPrefijo) > 3) {
                    $programa = $sinPrefijo;
                }
                if (!$programa) {
                    for ($k = $j + 1; $k < count($row); $k++) {
                        $v = trim((string)($row[$k] ?? ''));
                        if (mb_strlen($v) > 3 && !is_numeric($v)) { $programa = $v; break; }
                    }
                }
            }

            if ($programa && $ficha) break 2;
        }
    }

    return ['programa' => $programa, 'ficha' => $ficha];
}

function mapearColumnas(array $headers): array {
    // null = columna no encontrada
    $map = [
        'tipo_doc'    => null,
        'num_doc'     => null,
        'nombre'      => null,
        'apellidos'   => null,
        'estado'      => null,
        'competencia' => null,
        'resultado_ra'=> null,
        'juicio'      => null,
        'fecha'       => null,
        'funcionario' => null,
    ];

    $patrones = [
        'tipo_doc'     => ['tipo de doc','tipo doc','tipo_doc','tipo de'],
        'num_doc'      => ['numero de doc','numero doc','num doc','no. doc','no doc','identificacion','numero de identificacion','numero de'],
        'nombre'       => ['nombre aprendiz','nombre del aprendiz','nombre'],
        'apellidos'    => ['apellido'],
        'estado'       => ['estado academico','estado del aprendiz','estado'],
        'competencia'  => ['competencia'],
        'resultado_ra' => ['resultado de aprendizaje','resultado aprendizaje','resultado'],
        'juicio'       => ['juicio de evaluacion','juicio evaluativo','juicio','evaluacion','por evaluar'],
        'fecha'        => ['fecha y hora','fecha juicio','fecha del juicio','fecha'],
        'funcionario'  => ['funcionario que registro','funcionario','instructor','docente','evaluador'],
    ];

    foreach ($patrones as $campo => $claves) {
        foreach ($headers as $idx => $h) {
            $hNorm = quitarTildes(mb_strtolower(trim((string)$h)));
            foreach ($claves as $clave) {
                if (str_contains($hNorm, $clave)) { $map[$campo] = $idx; break 2; }
            }
        }
    }
    return $map;
}

function quitarTildes(string $v): string {
    return str_replace(
        ['á','é','í','ó','ú','ü','ñ','Á','É','Í','Ó','Ú','Ü','Ñ'],
        ['a','e','i','o','u','u','n','a','e','i','o','u','u','n'],
        $v
    );
}

function generarCodigo(string $nombre): string {
    $palabras = explode(' ', strtoupper($nombre));
    $codigo   = implode('', array_map(fn($p) => mb_substr($p, 0, 3), array_slice($palabras, 0, 3)));
    return mb_substr($codigo, 0, 50) ?: 'PRG';
}

function esFilaVacia(array $row): bool {
    return empty(array_filter($row, fn($v) => trim((string)$v) !== ''));
}

function normalizarTipoDoc(string $v): string {
    $v = strtoupper(trim($v));
    return in_array($v, ['CC','TI']) ? $v : 'CC';
}

function normalizarEstado(string $v): string {
    $v = quitarTildes(mb_strtolower(trim($v)));
    $v = preg_replace('/\s+/', ' ', $v);

    if (str_contains($v, 'retiro')    ||
        str_contains($v, 'retirado')  ||
        str_contains($v, 'abandono')  ||
        str_contains($v, 'cancelado') ||
        str_contains($v, 'desercion')) return 'Retiro Voluntario';

    if (str_contains($v, 'traslad')) return 'Trasladado';

    return 'En formación';
}

function normalizarJuicio(string $v): string {
    $v = quitarTildes(mb_strtolower(trim($v)));
    $v = preg_replace('/\s+/', ' ', $v);

    if (preg_match('/\b(no|sin)\s+aprobado\b/', $v)
        || str_contains($v, 'reprobado')
        || str_contains($v, 'no competente')
        || str_contains($v, 'por evaluar')
        || str_contains($v, 'pendiente')) {
        return 'Pendiente';
    }

    return preg_match('/\baprobado\b/', $v) ? 'Aprobado' : 'Pendiente';
}

function parsearFecha(string $v): ?string {
    $v = trim($v);
    if ($v === '') return null;
    if (is_numeric($v)) {
        $ts = ((float)$v - 25569) * 86400;
        return gmdate('Y-m-d H:i:s', (int)round($ts));
    }

    // Algunos reportes escriben "a", "p", "a. m." o "p. m.".
    $v = preg_replace_callback('/\s+([ap])\.?\s*m?\.?$/iu', fn($m) => ' ' . (mb_strtolower($m[1]) === 'a' ? 'AM' : 'PM'), $v);
    $formatos = [
        '!d/m/Y h:i:s A', '!d/m/Y h:i A',
        '!d/m/Y H:i:s', '!d/m/Y H:i', '!d/m/Y',
        '!Y-m-d h:i:s A', '!Y-m-d h:i A',
        '!Y-m-d H:i:s', '!Y-m-d H:i', '!Y-m-d',
    ];
    foreach ($formatos as $fmt) {
        $dt = DateTime::createFromFormat($fmt, $v);
        $errores = DateTime::getLastErrors();
        if ($dt && ($errores === false || ($errores['warning_count'] === 0 && $errores['error_count'] === 0))) {
            return $dt->format('Y-m-d H:i:s');
        }
    }
    return null;
}
