<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once 'ripcord.php';

if (!isset($_SESSION['student'])) {
    header("Location: index.php");
    exit();
}

$student = $_SESSION['student'];
$student_id = $student['id'];
$student_name = $student['name'];

$url = "http://31.220.31.26:8069/xmlrpc/2/";
$db = "politef";
$username = "mtro.guillermo.sanchez@gmail.com";
$password = "P@ssword1974";

$common = ripcord::client("{$url}common");
$uid = $common->authenticate($db, $username, $password, []);
$models = ripcord::client("{$url}object");

$fees = $models->execute_kw($db, $uid, $password,
    'op.student.fees.details', 'search_read',
    [[['student_id', '=', $student_id]]],
    ['fields' => ['date', 'invoice_description', 'amount', 'discount', 'invoice_id']]
);

$invoice_ids = [];
foreach ($fees as $fee) {
    if (is_array($fee['invoice_id']) && isset($fee['invoice_id'][0])) {
        $invoice_ids[] = $fee['invoice_id'][0];
    }
}

$invoice_status = [];
if (!empty($invoice_ids)) {
    $invoices = $models->execute_kw($db, $uid, $password,
        'account.move', 'read',
        [$invoice_ids],
        ['fields' => ['id', 'payment_state', 'before_payment_reference', 'amount_residual']]
    );

    foreach ($invoices as $inv) {
        if ($inv['payment_state'] == 'not_paid') {
            $invoice_status[$inv['id']] = $inv['before_payment_reference'] ?? '';
        }
    }
}




$invoice_data = [];
foreach ($fees as $fee) {
    $invoice_id = $fee['invoice_id'][0];
    foreach ($invoices as $inv) {
        if ($inv['id'] === $invoice_id) {
            $importe_total = $fee['amount'];  // tomado desde op.student.fees.details
            $importe_residual = isset($inv['amount_residual']) ? $inv['amount_residual'] : 0;
            $invoice_data[] = [
                'date' => $fee['date'],
                'description' => $fee['invoice_description'],
                'amount' => "$" . number_format($importe_total, 2) . " (total) / $" . number_format($importe_residual, 2) . " (residual)",
                'reference' => $inv['before_payment_reference'] ?? ''
            ];
            break;
        }
    }
}
$_SESSION['fees_data']

 = [];

foreach ($fees as $fee) {
    $inv_id = is_array($fee['invoice_id']) ? $fee['invoice_id'][0] : null;
    if (isset($invoice_status[$inv_id])) {
        $_SESSION['fees_data'][] = [
            'fecha' => $fee['date'],
            'concepto' => $fee['invoice_description'],
            'referencia' => $invoice_status[$inv_id],
            'importe' => $fee['amount'],
        ];
    }
}

// Obtener detalles del estudiante (gr_no y name)
$student_details = $models->execute_kw($db, $uid, $password,
    'op.student', 'read',
    [[$student_id]],
    ['fields' => ['name', 'gr_no']]
);
$det = $student_details[0];

// Buscar la admisión activa del estudiante
$admission = $models->execute_kw($db, $uid, $password,
    'op.admission', 'search_read',
    [[['student_id', '=', $student_id]]],
    ['fields' => ['course_id', 'batch_id'], 'limit' => 1]
);

$ad = $admission[0] ?? [];

$_SESSION['student_info'] = [
    'name' => $det['name'] ?? '',
    'matricula' => $det['gr_no'] ?? '',
    'grupo' => is_array($ad['batch_id']) ? ($ad['batch_id'][1] ?? '') : '',
    'ciclo' => '', //is_array($ad['academic_year_id']) ? ($ad['academic_year_id'][1] ?? '') : '',
    'seccion' => is_array($ad['course_id']) ? ($ad['course_id'][1] ?? '') : ''
];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pagos Pendientes</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="estilos.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
<div class="container">
    <div class="tabla">
        <h2>Pagos pendientes de <?= htmlspecialchars($student_name) ?></h2>

        <?php
        $hay_pagos = false;
        foreach ($fees as $fee):
            $inv_id = is_array($fee['invoice_id']) ? $fee['invoice_id'][0] : null;
            if (isset($invoice_status[$inv_id])):
                if (!$hay_pagos) {
                    echo '<table><thead><tr><th>Fecha</th><th>Descripción</th><th>Importe</th><th>Referencia</th></tr></thead><tbody>';
                    $hay_pagos = true;
                }
                ?>
                <tr>
                    <td><?= htmlspecialchars($fee['date']) ?></td>
                    <td><?= htmlspecialchars($fee['invoice_description']) ?></td>
                    <td>$<?= number_format($fee['amount'], 2) ?></td>
                    <td><?= htmlspecialchars($invoice_status[$inv_id]) ?></td>
                </tr>
            <?php endif;
        endforeach;

        if ($hay_pagos) {
            echo '</tbody></table>';
        } else {
            echo "<p>No hay pagos pendientes.</p>";
        }
        ?>

        <div style="display: flex; justify-content: flex-end; margin-top: 20px;">
            <a href="descargar_pdf.php?id=<?= htmlspecialchars($student_id) ?>" 
               class="btn btn-danger" style="display: flex; align-items: center;" target="_blank">
                <i class="bi bi-file-earmark-pdf-fill" style="margin-right: 8px;"></i> Descargar Papeleta
            </a>
        </div>

        <a href="dashboard.php" class="btn mt-2">Regresar</a>
    </div>
</div>
</body>
</html>
