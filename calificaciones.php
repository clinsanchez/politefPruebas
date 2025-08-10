<?php
session_start();
require_once 'odoo_client.php';

// Redirigir si no hay sesión de estudiante
if (!isset($_SESSION['student'])) {
    header("Location: index.php");
    exit();
}

$student = $_SESSION['student'];
$student_id = $student['id'];
$student_name = $student['name'];

$odoo = new OdooClient();

// --- OBTENER INFORMACIÓN DEL ESTUDIANTE (VERSIÓN A PRUEBA DE ERRORES) ---
$student_info_full = $odoo->search_read('op.student', [['id', '=', $student_id]], ['fields' => ['gr_no', 'state']]);

if (empty($student_info_full)) {
    die("Error crítico: No se pudo encontrar la información del estudiante. Por favor, cierre sesión y vuelva a intentarlo.");
}
$student_data = $student_info_full[0];
$estado = $student_data['state'] ?? '';
$matricula = $student_data['gr_no'] ?? 'Desconocido';

// Si el estudiante está dado de baja, mostrar mensaje y salir
if (in_array(strtolower($estado), ['baja temporal', 'baja definitiva'])) {
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Acceso Restringido</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head><body>';
    echo '<div class="container mt-5"><div class="alert alert-warning text-center p-4 shadow-lg" role="alert"><h4 class="alert-heading">Acceso restringido</h4><p>Actualmente tu estado es <strong>' . htmlspecialchars($estado) . '</strong>. Para visualizar tus calificaciones, es necesario regularizar tu situación académica y financiera.</p><hr><a href="dashboard.php" class="btn btn-warning">Regresar</a></div></div>';
    echo '</body></html>';
    exit();
}

// --- LÓGICA PARA OBTENER CALIFICACIONES Y DATOS DE ADMISIÓN (MÉTODO DEFINITIVO) ---
$grades_data_raw = $odoo->search_read('op.student.grades', [['student_id', '=', $student_id]], ['fields' => ['grade', 'evaluation_to_faculties_id']]);

$grade_table = [];
$evals_data_map = [];
$admission_details = 'No disponible'; // Valor por defecto

if (is_array($grades_data_raw) && !empty($grades_data_raw)) {
    $eval_ids = [];
    foreach ($grades_data_raw as $grade) {
        if (isset($grade['evaluation_to_faculties_id'][0])) {
            $eval_ids[] = $grade['evaluation_to_faculties_id'][0];
        }
    }
    
    if (!empty($eval_ids)) {
        // --- SECCIÓN MODIFICADA: AHORA PEDIMOS 'admission_register_id' ---
        $evals_raw = $odoo->read(
            'op.evaluation.to.faculties', 
            array_unique($eval_ids), 
            ['subject_id', 'evaluation_schedule_id', 'admission_register_id'] // Campo clave añadido
        );

        $admission_found = false;
        foreach ($evals_raw as $eval) {
            $evals_data_map[$eval['id']] = $eval;
            
            // Intentar obtener el nombre de la admisión de la primera evaluación que lo tenga
            if (!$admission_found && isset($eval['admission_register_id'][1]) && !empty($eval['admission_register_id'][1])) {
                $admission_details = $eval['admission_register_id'][1];
                $admission_found = true; // Lo encontramos, no necesitamos buscar más
            }
        }
    }

    // Procesar las calificaciones (sin cambios aquí)
    foreach ($grades_data_raw as $grade) {
        if (isset($grade['evaluation_to_faculties_id'][0])) {
            $eval_id = $grade['evaluation_to_faculties_id'][0];
            
            if (isset($evals_data_map[$eval_id])) {
                $eval_details = $evals_data_map[$eval_id];
                $subject = $eval_details['subject_id'][1] ?? 'Materia Desconocida';
                $schedule = $eval_details['evaluation_schedule_id'][1] ?? 'Parcial Desconocido';
                $raw_parcial = strtoupper(trim(explode("/", $schedule)[0]));

                $parcial = match (true) {
                    str_contains($raw_parcial, 'CALIFICACION FINAL') => 'Calificación Final',
                    str_contains($raw_parcial, 'PARCIAL 1') => 'Parcial 1',
                    str_contains($raw_parcial, 'PARCIAL 2') => 'Parcial 2',
                    str_contains($raw_parcial, 'PARCIAL 3') => 'Parcial 3',
                    default => $raw_parcial,
                };

                if (!empty($subject) && $parcial !== 'PARCIAL DESCONOCIDO') {
                    $grade_table[$subject][$parcial] = $grade['grade'];
                }
            }
        }
    }
}


// --- GUARDAR DATOS ACTUALIZADOS EN LA SESIÓN PARA EL PDF ---
$_SESSION['student_info'] = [
    'name'              => $student_name,
    'matricula'         => $matricula,
    'admission_details' => $admission_details, // El dato correcto ahora estará aquí
];
$_SESSION['grade_table'] = $grade_table;

// Ordenar parciales para la tabla
$parciales_ordenados = [];
if(!empty($grade_table)) {
    $parciales_temp = [];
    foreach ($grade_table as $materia => $calificaciones) {
        foreach ($calificaciones as $parcial => $calificacion) {
            $parciales_temp[$parcial] = true;
        }
    }
    $parciales_ordenados = array_keys($parciales_temp);
    usort($parciales_ordenados, function($a, $b) {
        $orden = [
            'Parcial 1' => 1,
            'Parcial 2' => 2,
            'Parcial 3' => 3,
            'Calificación Final' => 4
        ];
        $pos_a = $orden[$a] ?? 99;
        $pos_b = $orden[$b] ?? 99;
        return $pos_a <=> $pos_b;
    });
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Calificaciones - <?php echo htmlspecialchars($student_name); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="estilos.css">
</head>
<body>
<div class="container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3>Calificaciones de <?php echo htmlspecialchars($student_name); ?></h3>
        <a href="descargar_boleta.php" class="btn btn-primary" target="_blank">
            <i class="bi bi-file-earmark-pdf-fill"></i> Descargar Boleta en PDF
        </a>
    </div>

    <?php if (!empty($grade_table)): ?>
    <div class="table-responsive">
        <table class="table table-bordered table-striped">
            <thead class="table-dark">
                <tr>
                    <th>Materia</th>
                    <?php foreach ($parciales_ordenados as $parcial): ?>
                        <th class="text-center"><?php echo htmlspecialchars($parcial); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($grade_table as $materia => $calificaciones): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($materia); ?></td>
                        <?php foreach ($parciales_ordenados as $parcial): ?>
                            <td class="text-center">
                                <?php
                                    $nota = $calificaciones[$parcial] ?? '-';
                                    echo htmlspecialchars($nota);
                                ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <div class="alert alert-info">No hay calificaciones para mostrar.</div>
    <?php endif; ?>

    <div class="mt-4">
        <a href="dashboard.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Regresar al Dashboard
        </a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
