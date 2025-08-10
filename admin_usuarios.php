<?php
session_start();
require_once 'config.php';

// --- Seguridad: Verificar si el usuario es administrador ---
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: dashboard.php"); // Redirigir si no es admin
    exit();
}

$user_name = $_SESSION['user_name'] ?? 'Administrador';
$success_message = '';
$error_message = '';

try {
    $pdo = new PDO($dsn, DB_USERNAME, DB_PASSWORD, $options);

    // --- Lógica para MANEJAR ACCIONES (Crear, Actualizar, Eliminar) ---
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
        if ($_GET['id'] == $_SESSION['user_id']) {
            $error_message = "Error: No puedes eliminar tu propia cuenta.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            $success_message = "Usuario eliminado correctamente.";
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = $_POST['id'] ?? null;
        $nombre = trim($_POST['nombre']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];

        if (empty($nombre) || empty($email)) {
            $error_message = "El nombre y el email son obligatorios.";
        } else {
            // Verificar si el email ya existe (solo si es un usuario nuevo o si se está cambiando el email)
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
            $stmt->execute([$email, $id ?? 0]);
            if ($stmt->fetch()) {
                $error_message = "Error: El correo electrónico ya está en uso por otro usuario.";
            } else {
                if ($id) {
                    if (!empty($password)) {
                        $password_hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE usuarios SET nombre = ?, email = ?, password = ? WHERE id = ?");
                        $stmt->execute([$nombre, $email, $password_hash, $id]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE usuarios SET nombre = ?, email = ? WHERE id = ?");
                        $stmt->execute([$nombre, $email, $id]);
                    }
                    $success_message = "Usuario actualizado correctamente.";
                } else {
                    if (empty($password)) {
                        $error_message = "La contraseña es obligatoria para crear un nuevo usuario.";
                    } else {
                        $password_hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, email, password, rol) VALUES (?, ?, ?, 'admin')");
                        $stmt->execute([$nombre, $email, $password_hash]);
                        $success_message = "Usuario creado correctamente.";
                    }
                }
            }
        }
        // Para evitar recargar el formulario, pasamos los mensajes por la URL si no hubo error grave de duplicado
        if(empty($error_message)){
            header("Location: admin_usuarios.php?success=" . urlencode($success_message));
            exit();
        }
    }

    // --- Lógica para OBTENER DATOS para mostrar en la página ---
    $usuarios = $pdo->query("SELECT id, nombre, email, fecha_creacion FROM usuarios ORDER BY nombre")->fetchAll();

    $usuario_a_editar = null;
    if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
        $stmt = $pdo->prepare("SELECT id, nombre, email FROM usuarios WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $usuario_a_editar = $stmt->fetch();
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
    <title>Administrar Usuarios - Portal Politef</title>
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
        <h3 class="text-white fw-bold"><i class="bi bi-people-fill"></i> Administrar Usuarios</h3>
        <a href="dashboard.php" class="btn btn-light"><i class="bi bi-arrow-left"></i> Regresar al Dashboard</a>
    </div>

    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($success_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($error_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm mb-4">
        <div class="card-header">
            <h4 class="mb-0"><?= $usuario_a_editar ? 'Editar Usuario' : 'Crear Nuevo Usuario' ?></h4>
        </div>
        <div class="card-body">
            <form action="admin_usuarios.php" method="post">
                <input type="hidden" name="id" value="<?= $usuario_a_editar['id'] ?? '' ?>">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="nombre" class="form-label">Nombre Completo</label>
                        <input type="text" class="form-control" id="nombre" name="nombre" value="<?= htmlspecialchars($usuario_a_editar['nombre'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="email" class="form-label">Correo Electrónico</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($usuario_a_editar['email'] ?? '') ?>" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Contraseña</label>
                    <input type="password" class="form-control" id="password" name="password" <?= $usuario_a_editar ? '' : 'required' ?>>
                    <?php if ($usuario_a_editar): ?>
                        <small class="form-text text-muted">Deja este campo en blanco para no cambiar la contraseña.</small>
                    <?php endif; ?>
                </div>
                
                <?php if ($usuario_a_editar): ?>
                    <button type="submit" class="btn btn-primary">Actualizar Usuario</button>
                    <a href="admin_usuarios.php" class="btn btn-secondary">Cancelar Edición</a>
                <?php else: ?>
                    <button type="submit" class="btn btn-success">Crear Usuario</button>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header">
            <h4 class="mb-0">Usuarios Administradores</h4>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Nombre</th>
                            <th>Email</th>
                            <th>Fecha de Creación</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios as $usuario): ?>
                        <tr>
                            <td><?= htmlspecialchars($usuario['nombre']) ?></td>
                            <td><?= htmlspecialchars($usuario['email']) ?></td>
                            <td><?= date('d/m/Y', strtotime($usuario['fecha_creacion'])) ?></td>
                            <td class="text-end">
                                <a href="?action=edit&id=<?= $usuario['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil-fill"></i> Editar</a>
                                <?php if ($usuario['id'] != $_SESSION['user_id']): ?>
                                <a href="?action=delete&id=<?= $usuario['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Estás seguro de que quieres eliminar a este usuario? Esta acción no se puede deshacer.');"><i class="bi bi-trash-fill"></i> Eliminar</a>
                                <?php endif; ?>
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
