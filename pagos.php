<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

require_once 'odoo_client.php';

if (!isset($_SESSION['student'])) {
    header("Location: index.php");
    exit();
}

$student = $_SESSION['student'];
$student_id = $student['id'];
$student_name = $student['name'];

$odoo = new OdooClient();

// LÓGICA USANDO amount_residual DESDE account.move
try {
    // Primero obtenemos los IDs de las facturas del estudiante
    $fees = $odoo->search_read(
        'op.student.fees.details',
        [['student_id', '=', $student_id]],
        ['fields' => ['invoice_id']]
    );

    $invoice_ids = [];
    if (is_array($fees)) {
        foreach ($fees as $fee) {
            if (is_array($fee['invoice_id']) && isset($fee['invoice_id'][0])) {
                $invoice_ids[] = $fee['invoice_id'][0];
            }
        }
    }
    
    $invoices = $odoo->read(
        'account.move',
        array_unique($invoice_ids), // Asegurarse que no haya IDs duplicados
        ['id', 'payment_state', 'before_payment_reference', 'invoice_line_ids', 'invoice_date', 'amount_residual']
    );

    $_SESSION['fees_data'] = [];

    if (is_array($invoices)) {
        foreach ($invoices as $inv) {
            if (
                isset($inv['payment_state']) &&
                in_array($inv['payment_state'], ['not_paid', 'partial']) &&
                isset($inv['id']) &&
                isset($inv['amount_residual']) &&
                floatval($inv['amount_residual']) > 0
            ) {
                $invoice_id = $inv['id'];
                $line_ids = $inv['invoice_line_ids'] ?? [];
                $reference = $inv['before_payment_reference'] ?? 'SIN-REF-' . $invoice_id; // Fallback para referencia
                $fecha = $inv['invoice_date'] ?? 'N/A';
                $residual = floatval($inv['amount_residual']);

                $conceptos = [];
                if (is_array($line_ids) && count($line_ids) > 0) {
                    $lines = $odoo->read(
                        'account.move.line',
                        $line_ids,
                        ['name']
                    );
                    if (is_array($lines)) {
                        foreach ($lines as $line) {
                            if (!empty($line['name']) && strpos($line['name'], 'Discount') === false) { // Omitir líneas de descuento
                                $conceptos[] = $line['name'];
                            }
                        }
                    }
                }

                $_SESSION['fees_data'][] = [
                    'fecha'     => $fecha,
                    'concepto'  => empty($conceptos) ? 'Pago de colegiatura' : implode('<br>', $conceptos),
                    'importe'   => $residual,
                    'referencia' => $reference
                ];
            }
        }
    } else {
        error_log("No se pudieron obtener las facturas con invoice_ids: " . json_encode($invoice_ids));
    }
} catch (Exception $e) {
    error_log("ERROR AL PROCESAR FACTURAS (amount_residual): " . $e->getMessage());
    $_SESSION['fees_data'] = [];
}

// ... (El resto de tu lógica para obtener student_info y ultima_actualizacion permanece igual) ...
$student_details = $odoo->read('op.student', [$student_id], ['name', 'gr_no']);
$det = $student_details[0] ?? []; 

$admission = $odoo->search_read(
    'op.admission',
    [['student_id', '=', $student_id]],
    ['fields' => ['course_id', 'batch_id'], 'limit' => 1]
);
$ad = $admission[0] ?? [];

$_SESSION['student_info'] = [
    'name' => $det['name'] ?? '',
    'matricula' => $det['gr_no'] ?? '',
    'grupo' => (isset($ad['batch_id']) && is_array($ad['batch_id'])) ? ($ad['batch_id'][1] ?? '') : '',
    'ciclo' => '',
    'seccion' => (isset($ad['course_id']) && is_array($ad['course_id'])) ? ($ad['course_id'][1] ?? '') : ''
];

$ultima_actualizacion = "Fecha no disponible";
try {
    $actualizacion = $odoo->search_read(
        'load.payment.bank.state.model',
        [],
        ['fields' => ['date'], 'limit' => 1, 'order' => 'write_date desc']
    );

    if (!empty($actualizacion) && isset($actualizacion[0]['date']) && $actualizacion[0]['date']) {
        $dt = new DateTime($actualizacion[0]['date']);
        $meses = ['01'=>'enero','02'=>'febrero','03'=>'marzo','04'=>'abril','05'=>'mayo','06'=>'junio','07'=>'julio','08'=>'agosto','09'=>'septiembre','10'=>'octubre','11'=>'noviembre','12'=>'diciembre'];
        $mes_nombre = $meses[$dt->format('m')] ?? $dt->format('m');
        $ultima_actualizacion = $dt->format("j") . " de $mes_nombre de " . $dt->format("Y");
    }
} catch (Exception $e) {
    $ultima_actualizacion = "Error al obtener fecha";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pagos Pendientes - <?= htmlspecialchars($student_name) ?> - Politef</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Segoe+UI:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="estilos.css">
    <style>
        /* Tus estilos CSS existentes aquí */
        body {
            font-family: 'Inter', 'Segoe UI', Arial, sans-serif;
            background: url('Imagenes/dashboard.jpg') no-repeat center center fixed;
            background-size: cover;
            margin: 0;
            color: #343a40;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        .navbar {
            background-color: rgba(255, 255, 255, 0.97);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: sticky; top: 0; z-index: 1030;
        }
        .navbar-brand img { height: 48px; }
        .card-container {
            background-color: rgba(255, 255, 255, 0.98);
            border-radius: 16px;
            padding: 2rem 2.5rem;
            margin-top: 2.5rem;
            margin-bottom: 2.5rem;
            box-shadow: 0 12px 35px rgba(0, 50, 100, 0.1), 0 3px 8px rgba(0,0,0,0.06);
        }
        .card-container h2 { color: #005A9C; font-weight: 700; }
        .last-update { font-size: 0.9rem; color: #5a6268; text-align: right; margin-bottom: 1.5rem; }
        
        /* --- CÓDIGO CSS RESTAURADO --- */
        .clabe-info-box {
            background-color: #f0f7ff;
            border-left: 5px solid #005a9c;
            padding: 1rem 1.5rem;
            margin: 1.5rem 0;
            border-radius: 8px;
            text-align: center;
        }
        .clabe-info-box p { margin: 0; font-size: 1rem; color: #333; }
        .clabe-info-box .clabe-number {
            font-weight: 600; font-size: 1.2rem; color: #003366;
            margin-top: 5px; display: block; font-family: monospace;
        }
        /* --- FIN DEL CÓDIGO RESTAURADO --- */

        .table thead.table-dark th { background-color: #2c3e50; text-align: center; }
        .table tbody td { vertical-align: middle; text-align: center; }
        .table tbody td:nth-child(2) { text-align: left; } /* Concepto a la izquierda */
        .actions-toolbar { display: flex; justify-content: space-between; align-items: center; margin-top: 2.5rem; }
        .footer { text-align: center; padding: 1.8rem 0; background-color: rgba(44, 62, 80, 0.95); color: rgba(255, 255, 255, 0.85); margin-top: auto; }
        
        .btn-pagar {
            background-color: #28a745; border-color: #28a745; color: white;
            font-weight: 500; padding: 0.375rem 0.75rem; border-radius: 20px;
            transition: all 0.3s ease;
        }
        .btn-pagar:hover {
            background-color: #218838; border-color: #1e7e34;
            transform: translateY(-1px); box-shadow: 0 4px 10px rgba(40, 167, 69, 0.25);
        }
        .btn-pagar .bi { margin-right: 0.4rem; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php"><img src="Imagenes/logoPolitef.png" alt="Logo Politef"></a>
        <span class="navbar-text ms-auto">Alumno: <?= strtoupper(htmlspecialchars($student_name)) ?></span>
    </div>
</nav>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-10 col-xl-9 card-container">
            <h2 class="text-center mb-3">Pagos Pendientes</h2>
            <p class="last-update"><i class="bi bi-clock-history"></i> Última actualización: <strong><?= htmlspecialchars($ultima_actualizacion) ?></strong></p>

            <!-- BLOQUE DE INFORMACIÓN CLABE RESTAURADO -->
            <div class="clabe-info-box">
                <p>Para pagos por transferencia, utilice la siguiente información:</p>
                <p>Cuenta a nombre de: POLITECNICO DE LA FRONTERA</p>
                <span class="clabe-number">CLABE: 002164460100681188</span>
            </div>

            <?php
            if (isset($_GET['error'])) {
                echo "<div class='alert alert-danger'>Error al procesar el pago: " . htmlspecialchars($_GET['error']) . "</div>";
            }
            if (isset($_SESSION['fees_data']) && !empty($_SESSION['fees_data'])) {
            ?>
                <div class="table-responsive">
                    <table class="table table-hover table-bordered">
                        <thead class="table-dark">
                            <tr>
                                <th>Fecha Límite</th>
                                <th>Concepto</th>
                                <th>Importe</th>
                                <th>Referencia</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($_SESSION['fees_data'] as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['fecha'] ?? 'N/A') ?></td>
                                <td><?= $row['concepto'] ?? 'N/A' ?></td>
                                <td>$<?= number_format(floatval($row['importe'] ?? 0), 2) ?></td>
                                <td><?= htmlspecialchars($row['referencia'] ?? 'N/A') ?></td>
                                <td>
                                    <!-- FORMULARIO AJUSTADO -->
                                    <form action="procesar_pago.php" method="post" style="margin:0;">
                                        <input type="hidden" name="amount" value="<?= htmlspecialchars($row['importe'] ?? 0) ?>">
                                        <input type="hidden" name="order_description" value="<?= strip_tags($row['concepto'] ?? 'Pago Politef') ?>">
                                        <input type="hidden" name="order_reference" value="<?= htmlspecialchars($row['referencia'] ?? '') ?>">
                                        <button type="submit" class="btn btn-pagar btn-sm">
                                            <i class="bi bi-credit-card-fill"></i> Pagar con Tarjeta
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php
            } else {
                echo "<div class='alert alert-info text-center mt-3'><i class='bi bi-info-circle-fill me-2'></i>No hay pagos pendientes registrados.</div>";
            }
            ?>
            <div class="actions-toolbar">
                <a href="dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left-circle-fill"></i> Regresar</a>
                <a href="descargar_pdf.php?id=<?= htmlspecialchars($student_id) ?>" class="btn btn-danger" target="_blank"><i class="bi bi-file-earmark-pdf-fill"></i> Descargar Papeleta</a>
            </div>
        </div>
    </div>
</div>

<footer class="footer">
    &copy; <?= date("Y"); ?> <a href="https://politefalumnos.com" target="_blank">Politef Alumnos</a>. Todos los derechos reservados.
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
