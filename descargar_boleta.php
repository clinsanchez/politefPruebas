<?php
session_start();
require('fpdf.php'); // Asegúrate que la ruta a fpdf.php sea correcta

// Validación de sesión para generar el PDF
if (!isset($_SESSION['student']) || !isset($_SESSION['grade_table']) || !isset($_SESSION['student_info'])) {
    die("No se pudo generar la boleta. La información no está disponible. Por favor, regresa a la página de calificaciones e inténtalo de nuevo.");
}

// Obtener datos de la sesión
$student_name = $_SESSION['student']['name'] ?? 'Desconocido';
$grades = $_SESSION['grade_table'];
$info = $_SESSION['student_info'];

$matricula = $info['matricula'] ?? 'Desconocida';
// Variable que contiene la información de admisión (detallada o básica)
$admission_details = $info['admission_details'] ?? 'N/A';

// Ordenar las columnas de los parciales
$parciales = [];
if (!empty($grades)) {
    foreach ($grades as $materia => $datos) {
        foreach ($datos as $parcial => $cal) {
            $parciales[$parcial] = true;
        }
    }
    $parciales = array_keys($parciales);
    usort($parciales, function ($a, $b) {
        $orden = ['Parcial 1' => 1, 'Parcial 2' => 2, 'Parcial 3' => 3, 'Calificación Final' => 4];
        return ($orden[$a] ?? 99) - ($orden[$b] ?? 99);
    });
}

// Clase PDF personalizada para cabecera y pie de página
class PDF extends FPDF
{
    function Header()
    {
        $this->Image('Imagenes/logoPolitef.png', 10, 8, 25);
        $this->SetY(12);
        $this->SetX(40);
        $this->SetFont('Arial', 'B', 16);
        $this->Cell(0, 8, utf8_decode('Instituto Politécnico de la Frontera'), 0, 2, 'C');
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 8, utf8_decode('Boleta de Calificaciones'), 0, 2, 'C');
        $this->Ln(10);
    }

    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 5, utf8_decode("Este documento es solo de carácter informativo y no tiene validez oficial."), 0, 1, 'C');
        $pageWidth = $this->GetPageWidth();
        $this->Cell($pageWidth / 2 - 10, 5, utf8_decode("Instituto Politécnico de la Frontera"), 0, 0, 'L');
        $this->Cell($pageWidth / 2 - 10, 5, utf8_decode('Fecha de descarga: ') . date('d/m/Y'), 0, 1, 'R');
    }
}

$pdf = new PDF('L', 'mm', 'A4');
$pdf->SetAutoPageBreak(true, 15);
$pdf->AddPage();
$pdf->SetFont('Arial', '', 11);

// --- SECCIÓN DE INFO DEL ALUMNO ACTUALIZADA ---
$pdf->Cell(0, 7, utf8_decode("Nombre del Alumno: ") . utf8_decode($student_name), 0, 1);
$pdf->Cell(0, 7, utf8_decode("Matrícula: $matricula"), 0, 1);
// Usamos MultiCell para la admisión para prevenir que el texto largo se desborde
$pdf->MultiCell(0, 7, utf8_decode("Admisión: ") . utf8_decode($admission_details), 0, 'L');
$pdf->Ln(4);

// Encabezado de tabla
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(220, 220, 220);
$pdf->Cell(110, 8, utf8_decode('Materia'), 1, 0, 'C', true);
foreach ($parciales as $parcial) {
    $pdf->Cell(40, 8, utf8_decode($parcial), 1, 0, 'C', true);
}
$pdf->Ln();

// Contenido de la tabla
$pdf->SetFont('Arial', '', 9);
foreach ($grades as $materia => $calificaciones) {
    $y_inicial = $pdf->GetY();
    $x_inicial = $pdf->GetX();

    $pdf->MultiCell(110, 7, utf8_decode($materia), 1, 'L');
    $altura_fila = $pdf->GetY() - $y_inicial;

    $pdf->SetXY($x_inicial + 110, $y_inicial);

    foreach ($parciales as $parcial) {
        $nota = $calificaciones[$parcial] ?? '-';
        $pdf->Cell(40, $altura_fila, is_numeric($nota) ? number_format($nota, 1) : utf8_decode($nota), 1, 0, 'C');
    }
    
    $pdf->SetY($y_inicial + $altura_fila);
}

ob_clean();
$pdf->Output('I', 'boleta_calificaciones.pdf');
exit;
