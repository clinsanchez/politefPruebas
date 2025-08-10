<?php
require_once 'fpdf.php';
session_start();

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    die("ID no válido");
}

if (!isset($_SESSION['fees_data']) || !isset($_SESSION['student_info'])) {
    die("No hay datos disponibles para generar el PDF.");
}

$pagos = $_SESSION['fees_data'];
$datos = $_SESSION['student_info'];

$pdf = new FPDF('L', 'mm', 'A4');
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 12);

$pdf->Image('Imagenes/logoPolitef.png', 10, 10, 30);

$margen_x = (297 - 240) / 2;

$pdf->SetXY($margen_x, 12);
$pdf->Cell(240, 10, utf8_decode('INSTITUTO POLITÉCNICO DE LA FRONTERA / CAMPUS SURORIENTE'), 0, 1, 'C');
$pdf->SetX($margen_x);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(240, 6, utf8_decode('PAPELETA DE PAGOS'), 0, 1, 'C');
$pdf->Ln(4);

$pdf->SetX($margen_x);
$pdf->Cell(240, 6, utf8_decode("Nombre: {$datos['name']}"), 0, 1);
$pdf->SetX($margen_x);
$pdf->Cell(240, 6, utf8_decode("Matrícula: {$datos['matricula']}   Grupo: {$datos['grupo']}"), 0, 1);
$pdf->SetX($margen_x);
$pdf->Cell(240, 6, utf8_decode("Ciclo: {$datos['ciclo']}   Sección: {$datos['seccion']}"), 0, 1);
$pdf->Ln(4);

$pdf->SetFont('Arial', '', 9);
$pdf->SetX($margen_x);
$pdf->MultiCell(240, 5, utf8_decode(
    "* PAGO ÚNICAMENTE POR TRANSFERENCIA.
" .
    "RECARGO $150 PESOS MENSUALES DESPUÉS DE LA FECHA LÍMITE.
" .
    "ASEGÚRESE DE INGRESAR CORRECTAMENTE LA CLABE, CONCEPTO Y EL IMPORTE.
" .
    "LA INSTITUCIÓN NO SE HACE RESPONSABLE DE PERCANCES OCURRIDOS POR ERRORES MANUALES AL HACER LA TRANSFERENCIA.
" .
    "Cuenta a nombre de: POLITÉCNICO DE LA FRONTERA
" .
    "CLABE: 002164460100681188"
));
$pdf->Ln(2);

$pdf->SetFont('Arial', 'B', 10);
$pdf->SetX($margen_x);
$pdf->Cell(50, 8, 'FECHA LIMITE', 1);
$pdf->Cell(100, 8, 'PAGOS A CUBRIR', 1);
$pdf->Cell(60, 8, 'CONCEPTO DE PAGO', 1);
$pdf->Cell(30, 8, 'IMPORTE', 1);
$pdf->Ln();

$pdf->SetFont('Arial', '', 10);
foreach ($pagos as $pago) {
    $x = $pdf->GetX();
    $y = $pdf->GetY();
    $pdf->SetX($margen_x);
    $pdf->MultiCell(50, 8, $pago['fecha'], 1, 'L');
    $pdf->SetXY($margen_x + 50, $y);
    $pdf->MultiCell(100, 8, utf8_decode(str_replace("<br>", "\n", $pago['concepto'])), 1, 'L');
    $altura_concepto = $pdf->GetY() - $y;
    $pdf->SetXY($margen_x + 150, $y);
    $pdf->MultiCell(60, 8, $pago['referencia'], 1, 'L');
    $pdf->SetXY($margen_x + 210, $y);
    $pdf->MultiCell(30, 8, '$' . number_format($pago['importe'], 2), 1, 'R');
    $pdf->SetY($y + max($altura_concepto, 8));
}

$pdf->Ln(6);
$pdf->SetX($margen_x);
$pdf->SetFont('Arial', '', 9);
$pdf->MultiCell(240, 5, utf8_decode("NOTA:
* En caso de poner la numeración incorrecta en el concepto o motivo, el importe se devolverá a su cuenta, cuide las fechas.
* Los pagos son del 1 al 5 de cada mes. Después, recuerde el recargo de $150 pesos.
* Los pagos se reflejan los viernes de cada semana."));

$pdf->Ln(2);
$pdf->SetDrawColor(100, 100, 100);
$pdf->Line($margen_x, $pdf->GetY(), $margen_x + 240, $pdf->GetY());
$pdf->Ln(2);

$pdf->SetFont('Arial', '', 9);
$pdf->SetX($margen_x);
$pdf->Cell(60, 6, 'Tel: 6566470434', 0, 0, 'L');
$pdf->Cell(80, 6, 'ipf.control_esc@hotmail.com', 0, 0, 'L');
$pdf->Cell(50, 6, 'https://politefjrz.com/', 0, 0, 'L');
$pdf->Cell(50, 6, 'RFC: PFR990219EZ4', 0, 1, 'L');

$pdf->Output("I", "pago_estudiante_$id.pdf");
?>
