<?php
session_start();
require_once 'config.php';

// --- Seguridad: Verificar si el usuario es administrador ---
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

$user_name = $_SESSION['user_name'] ?? 'Administrador';
$success_message = '';
$error_message = '';
$upload_dir = 'uploads/'; // Directorio para guardar las imágenes

try {
    $pdo = new PDO($dsn, DB_USERNAME, DB_PASSWORD, $options);

    // --- Lógica para MANEJAR ACCIONES ---

    // Acción: ELIMINAR un anuncio
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
        // Primero, obtener la URL de la imagen para borrar el archivo
        $stmt = $pdo->prepare("SELECT imagen_url FROM anuncios WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $anuncio = $stmt->fetch();
        if ($anuncio && !empty($anuncio['imagen_url']) && file_exists($anuncio['imagen_url'])) {
            unlink($anuncio['imagen_url']); // Borrar el archivo de imagen del servidor
        }

        // Luego, borrar el registro de la base de datos
        $stmt = $pdo->prepare("DELETE FROM anuncios WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        header("Location: admin_anuncios.php?success=" . urlencode("Anuncio eliminado correctamente."));
        exit();
    }

    // Acción: CREAR o ACTUALIZAR un anuncio
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = $_POST['id'] ?? null;
        $titulo = trim($_POST['titulo']);
        $contenido = trim($_POST['contenido']);
        $activo = isset($_POST['activo']) ? 1 : 0;
        $imagen_actual = $_POST['imagen_actual'] ?? '';
        $imagen_url = $imagen_actual;

        if (empty($titulo) || empty($contenido)) {
            $error_message = "El título y el contenido son obligatorios.";
        } else {
            // Lógica para subir la imagen
            if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] == 0) {
                $file_name = time() . '_' . basename($_FILES['imagen']['name']);
                $target_file = $upload_dir . $file_name;
                
                // Mover el archivo subido al directorio de uploads
                if (move_uploaded_file($_FILES['imagen']['tmp_name'], $target_file)) {
                    $imagen_url = $target_file;
                    // Si estamos actualizando y había una imagen anterior, borrarla
                    if (!empty($imagen_actual) && file_exists($imagen_actual)) {
                        unlink($imagen_actual);
                    }
                } else {
                    $error_message = "Hubo un error al subir la imagen.";
                }
            }

            if (empty($error_message)) {
                if ($id) {
                    // ACTUALIZAR
                    $stmt = $pdo->prepare("UPDATE anuncios SET titulo = ?, contenido = ?, imagen_url = ?, activo = ? WHERE id = ?");
                    $stmt->execute([$titulo, $contenido, $imagen_url, $activo, $id]);
                    $success_message = "Anuncio actualizado correctamente.";
                } else {
                    // CREAR
                    $stmt = $pdo->prepare("INSERT INTO anuncios (titulo, contenido, imagen_url, activo, usuario_id) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$titulo, $contenido, $imagen_url, $activo, $_SESSION['user_id']]);
                    $success_message = "Anuncio creado correctamente.";
                }
                header("Location: admin_anuncios.php?success=" . urlencode($success_message));
                exit();
            }
        }
    }

    // --- Lógica para OBTENER DATOS para la página ---
    $anuncios = $pdo->query("SELECT a.*, u.nombre as autor FROM anuncios a LEFT JOIN usuarios u ON a.usuario_id = u.id ORDER BY a.fecha_creacion DESC")->fetchAll();

    $anuncio_a_editar = null;
    if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
        $stmt = $pdo->prepare("SELECT * FROM anuncios WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $anuncio_a_editar = $stmt->fetch();
    }
    
    if(isset($_GET['success'])) $success_message = $_GET['success'];

} catch (PDOException $e) {
    $error_message = "Error de conexión con la base de datos: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Administrar Anuncios - Portal Politef</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Segoe+UI:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body, html { height: 100%; margin: 0; font-family: 'Inter', 'Segoe UI', sans-serif; }
        .bg-container { position: fixed; top: 0; left: 0; height: 100%; width: 100%; z-index: -1; overflow: hidden; }
        .bg-image { background-image: url('Imagenes/dashboard.jpg'); background-repeat: no-repeat; background-position: center center; background-attachment: fixed; background-size: cover; height: 100%; width: 100%; }
        .bg-container::before { content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.35); z-index: 1; }
        .navbar { background-color: rgba(255, 255, 255, 0.97); box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); }
        .navbar-brand img { height: 45px; }
        .main-content { position: relative; z-index: 2; padding-top: 2rem; padding-bottom: 2rem; }
        .card { background-color: rgba(255, 255, 255, 0.98); }
    </style>
</head>
<body>

<div class="bg-container">
    <div class="bg-image"></div>
</div>

<nav class="navbar navbar-expand-lg">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php">
            <img src="Imagenes/logoPolitef.png" alt="Logo Politef">
        </a>
        <span class="navbar-text ms-auto fw-bold">
            <i class="bi bi-person-circle"></i> <?= strtoupper(htmlspecialchars($user_name)) ?>
        </span>
    </div>
</nav>

<div class="main-content container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="text-white fw-bold"><i class="bi bi-megaphone-fill"></i> Administrar Anuncios</h3>
        <a href="dashboard.php" class="btn btn-light"><i class="bi bi-arrow-left"></i> Regresar al Dashboard</a>
    </div>

    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert"><?= htmlspecialchars($success_message) ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>
    <?php endif; ?>
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert"><?= htmlspecialchars($error_message) ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>
    <?php endif; ?>

    <div class="card shadow-sm mb-4">
        <div class="card-header"><h4 class="mb-0"><?= $anuncio_a_editar ? 'Editar Anuncio' : 'Crear Nuevo Anuncio' ?></h4></div>
        <div class="card-body">
            <form action="admin_anuncios.php" method="post" enctype="multipart/form-data">
                <input type="hidden" name="id" value="<?= $anuncio_a_editar['id'] ?? '' ?>">
                <input type="hidden" name="imagen_actual" value="<?= htmlspecialchars($anuncio_a_editar['imagen_url'] ?? '') ?>">
                <div class="mb-3">
                    <label for="titulo" class="form-label">Título</label>
                    <input type="text" class="form-control" id="titulo" name="titulo" value="<?= htmlspecialchars($anuncio_a_editar['titulo'] ?? '') ?>" required>
                </div>
                <div class="mb-3">
                    <label for="contenido" class="form-label">Contenido</label>
                    <textarea class="form-control" id="contenido" name="contenido" rows="5" required><?= htmlspecialchars($anuncio_a_editar['contenido'] ?? '') ?></textarea>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="imagen" class="form-label">Imagen (Opcional)</label>
                        <input class="form-control" type="file" id="imagen" name="imagen" accept="image/jpeg, image/png, image/gif">
                        <?php if ($anuncio_a_editar && !empty($anuncio_a_editar['imagen_url'])): ?>
                            <small class="form-text text-muted">Imagen actual: <a href="<?= htmlspecialchars($anuncio_a_editar['imagen_url']) ?>" target="_blank">Ver imagen</a>. Subir una nueva la reemplazará.</small>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6 mb-3 d-flex align-items-center">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="activo" name="activo" value="1" <?= ($anuncio_a_editar['activo'] ?? 1) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="activo">Anuncio Activo / Visible</label>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary"><?= $anuncio_a_editar ? 'Actualizar Anuncio' : 'Crear Anuncio' ?></button>
                <?php if ($anuncio_a_editar): ?>
                    <a href="admin_anuncios.php" class="btn btn-secondary">Cancelar Edición</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header"><h4 class="mb-0">Anuncios Existentes</h4></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Título</th>
                            <th>Estado</th>
                            <th>Autor</th>
                            <th>Fecha</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($anuncios as $anuncio): ?>
                        <tr>
                            <td><?= htmlspecialchars($anuncio['titulo']) ?></td>
                            <td>
                                <?php if ($anuncio['activo']): ?>
                                    <span class="badge bg-success">Activo</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactivo</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($anuncio['autor'] ?? 'N/A') ?></td>
                            <td><?= date('d/m/Y', strtotime($anuncio['fecha_creacion'])) ?></td>
                            <td class="text-end">
                                <a href="?action=edit&id=<?= $anuncio['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil-fill"></i> Editar</a>
                                <a href="?action=delete&id=<?= $anuncio['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Estás seguro? Se eliminará el anuncio y su imagen.');"><i class="bi bi-trash-fill"></i> Eliminar</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
