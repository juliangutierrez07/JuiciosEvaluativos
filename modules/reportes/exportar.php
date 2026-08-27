<?php
require_once __DIR__ . '/../../includes/functions.php';

$autoload = __DIR__ . '/../../vendor/autoload.php';
if (!file_exists($autoload)) {
    flash('danger', 'PHPSpreadsheet no instalado. Ejecuta <code>composer install</code>.');
    redirect('../reportes/consolidado.php');
}
require_once $autoload;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

$db = getDB();

// Mismos filtros del reporte
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
        a.tipo_documento, a.numero_documento, a.nombre, a.estado,
        f.numero AS ficha, p.nombre AS programa,
        COUNT(je.resultado_id)                                                 AS total_ra,
        SUM(je.estado='Aprobado')                                              AS ra_aprobados,
        SUM(je.estado='Pendiente')                                             AS ra_pendientes,
        ROUND(IFNULL(SUM(je.estado='Aprobado')/NULLIF(COUNT(je.resultado_id),0)*100,0),1) AS pct,
        MAX(je.fecha_juicio)                                                   AS ultimo_juicio,
        (SELECT je2.funcionario
           FROM juicios_evaluativos je2
          WHERE je2.numero_documento = a.numero_documento
            AND je2.fecha_juicio IS NOT NULL
          ORDER BY je2.fecha_juicio DESC, je2.id DESC
          LIMIT 1)                                                             AS ultimo_funcionario
    FROM aprendices a
    JOIN fichas    f ON a.ficha_id    = f.id
    JOIN programas p ON f.programa_id = p.id
    LEFT JOIN juicios_evaluativos je ON je.numero_documento = a.numero_documento
    WHERE " . implode(' AND ', $where) . "
    GROUP BY a.tipo_documento, a.numero_documento, a.nombre, a.estado, f.numero, p.nombre
    ORDER BY p.nombre, f.numero, a.nombre
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$datos = $stmt->fetchAll();

// ── Crear Excel ───────────────────────────────────────────────────────────────
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Reporte Consolidado');

// Encabezados
$headers = ['Tipo Doc','Documento','Aprendiz','Estado','Ficha','Programa','Resultados de Aprendizaje','Aprobados','Pendientes','% Cumplimiento','Último Juicio','Funcionario'];
foreach ($headers as $col => $h) {
    $cell = chr(65 + $col) . '1';
    $sheet->setCellValue($cell, $h);
}

// Estilo encabezado
$sheet->getStyle('A1:L1')->applyFromArray([
    'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF0D6EFD']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
]);

// Datos
foreach ($datos as $i => $row) {
    $fila = $i + 2;
    $sheet->setCellValue("A{$fila}", $row['tipo_documento']);
    $sheet->setCellValue("B{$fila}", $row['numero_documento']);
    $sheet->setCellValue("C{$fila}", $row['nombre']);
    $sheet->setCellValue("D{$fila}", $row['estado']);
    $sheet->setCellValue("E{$fila}", $row['ficha']);
    $sheet->setCellValue("F{$fila}", $row['programa']);
    $sheet->setCellValue("G{$fila}", (int)$row['total_ra']);
    $sheet->setCellValue("H{$fila}", (int)$row['ra_aprobados']);
    $sheet->setCellValue("I{$fila}", (int)$row['ra_pendientes']);
    $sheet->setCellValue("J{$fila}", (float)$row['pct']);
    $sheet->setCellValue("K{$fila}", $row['ultimo_juicio'] ? date('d/m/Y H:i', strtotime($row['ultimo_juicio'])) : '');
    $sheet->setCellValue("L{$fila}", $row['ultimo_funcionario'] ?? '');
}

// Autoajustar columnas
foreach (range('A','L') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Descargar
$filename = 'reporte_consolidado_' . date('Ymd_His') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
