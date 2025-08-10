<?php
session_start();
require_once 'config.php'; // Carga la configuración de la BD

// Redirigir si no hay ninguna sesión iniciada
if (!isset($_SESSION['user_role'])) {
    header("Location: index.php");
    exit();
}

$user_role = $_SESSION['user_role'];
$user_name = $_SESSION['user_name'] ?? 'Usuario Desconocido'; 

$anuncios = [];
$db_error = '';

// Solo los estudiantes necesitan ver los anuncios en el dashboard
if ($user_role === 'student') {
    try {
        $pdo = new PDO($dsn, DB_USERNAME, DB_PASSWORD, $options);
        // Seleccionamos solo los anuncios que están marcados como 'activos'
        $stmt = $pdo->query("SELECT a.*, u.nombre as autor FROM anuncios a LEFT JOIN usuarios u ON a.usuario_id = u.id WHERE a.activo = 1 ORDER BY a.fecha_creacion DESC LIMIT 5");
        $anuncios = $stmt->fetchAll();
    } catch (PDOException $e) {
        $db_error = "No se pudieron cargar los anuncios en este momento.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Portal Politef</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Segoe+UI:wght@400;600&display=swap" rel="stylesheet">
    <style>
        /* Tus estilos existentes para el fondo y el diseño general */
        body, html { height: 100%; margin: 0; font-family: 'Inter', 'Segoe UI', sans-serif; }
        .bg-container { position: fixed; top: 0; left: 0; height: 100%; width: 100%; z-index: -1; overflow: hidden; }
        .bg-image { background-image: url('Imagenes/dashboard.jpg'); background-repeat: no-repeat; background-position: center center; background-attachment: fixed; background-size: cover; height: 100%; width: 100%; }
        .bg-container::before { content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.25); z-index: 1; }
        .navbar { background-color: rgba(255, 255, 255, 0.97); box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); }
        .navbar-brand img { height: 45px; }
        .navbar-text { color: #2a3a4a !important; font-weight: 600; }
        .main-content { padding: 2rem 1rem; position: relative; z-index: 2; }
        
        /* Estilos para la tarjeta de alumno (tu diseño original) */
        .dashboard-card { background-color: rgba(255, 255, 255, 0.98); padding: 2.5rem; border-radius: 16px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15); text-align: center; max-width: 480px; width: 100%; border: 1px solid #e0e0e0; margin: auto; }
        .card-title-custom { font-size: 1.75rem; font-weight: 700; color: #004985; margin-bottom: 2rem; }
        .dashboard-btn-link { text-decoration: none; display: block; margin-bottom: 1rem; }
        .dashboard-btn, .logout-btn { color: white; padding: 14px 22px; border: none; border-radius: 10px; width: 100%; transition: all 0.3s ease; font-size: 1.05rem; font-weight: 500; display: flex; align-items: center; justify-content: center; text-align: center; }
        .dashboard-btn .bi, .logout-btn .bi { margin-right: 10px; font-size: 1.2rem; }
        .btn-calificaciones { background-color: #00796b; } 
        .btn-calificaciones:hover { background-color: #005a4f; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0, 121, 107, 0.3); }
        .btn-pagos { background-color: #0288d1; } 
        .btn-pagos:hover { background-color: #016ca8; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(2, 136, 209, 0.3); }
        .btn-plataforma { background-color: #388e3c; } 
        .btn-plataforma:hover { background-color: #2a6e2f; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(56, 142, 60, 0.3); }
        .logout-btn { background-color: #f57c00; margin-top: 1rem; }
        .logout-btn:hover { background-color: #d86d00; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(245, 124, 0, 0.3); }

        /* Estilos para VISTA DE ADMIN */
        .admin-card { background: rgba(255, 255, 255, 0.98); border: none; border-radius: 16px; transition: transform 0.3s ease, box-shadow 0.3s ease; }
        .admin-card:hover { transform: translateY(-5px); box-shadow: 0 15px 35px rgba(0,0,0,0.2); }

        /* --- ESTILOS PARA EL CARRUSEL CON TARJETAS --- */
        #announcementCarousel {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 16px;
            padding: 1rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        }

        /* === AJUSTE PARA ALTURA FIJA DEL ANUNCIO === */
        #announcementCarousel .carousel-item {
            min-height: 520px; /* Altura mínima fija para cada anuncio. ¡Puedes ajustar este valor! */
        }

        #announcementCarousel .carousel-item .card {
            border: none;
            background: transparent;
            height: 100%; /* La tarjeta ocupa toda la altura del item */
            display: flex; /* Usamos flexbox para distribuir el espacio */
            flex-direction: column; /* Organizamos los elementos en una columna */
        }
        
        #announcementCarousel .carousel-item .card-body {
            flex-grow: 1; /* El cuerpo de la tarjeta crece para llenar el espacio sobrante */
            overflow-y: auto; /* Si el texto es muy largo, aparecerá una barra de scroll */
        }

        #announcementCarousel .carousel-item img {
            border-radius: 12px;
            max-height: 300px;   /* Altura máxima para la imagen */
            width: 100%;
            object-fit: contain; /* Mantiene la imagen completa sin recortar */
            background-color: #f0f2f5;
            flex-shrink: 0; /* Evita que la imagen se encoja */
        }

        .carousel-control-prev-icon,
        .carousel-control-next-icon {
            background-color: rgba(0, 0, 0, 0.5);
            border-radius: 50%;
        }
    </style>
</head>
<body>

<div class="bg-container"><div class="bg-image"></div></div>

<nav class="navbar navbar-expand-lg">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php"><img src="Imagenes/logoPolitef.png" alt="Logo Politef"></a>
        <span class="navbar-text ms-auto fw-bold"><i class="bi bi-person-circle"></i> <?= strtoupper(htmlspecialchars($user_name)) ?></span>
    </div>
</nav>

<div class="main-content">
    <?php if ($user_role === 'admin'): ?>
    <!-- ======================= VISTA DE ADMINISTRADOR ======================= -->
    <div class="container">
        <div class="text-center mb-5 text-white">
            <h1 class="display-4 fw-bold">Panel de Administración</h1>
            <p class="lead">Gestiona los recursos del portal.</p>
        </div>
        <div class="row justify-content-center g-4">
            <div class="col-md-5"><a href="admin_usuarios.php" class="text-decoration-none"><div class="card text-center h-100 shadow-lg admin-card"><div class="card-body p-5"><i class="bi bi-people-fill display-1 text-primary"></i><h3 class="card-title mt-3">Usuarios</h3><p class="card-text text-muted">Crear, editar y gestionar cuentas de administrador.</p></div></div></a></div>
            <div class="col-md-5"><a href="admin_anuncios.php" class="text-decoration-none"><div class="card text-center h-100 shadow-lg admin-card"><div class="card-body p-5"><i class="bi bi-megaphone-fill display-1 text-success"></i><h3 class="card-title mt-3">Anuncios</h3><p class="card-text text-muted">Publicar y gestionar los anuncios para los alumnos.</p></div></div></a></div>
        </div>
        <div class="text-center mt-5"><a href="logout.php" class="btn btn-warning btn-lg"><i class="bi bi-box-arrow-right"></i> Cerrar Sesión</a></div>
    </div>

    <?php else: ?>
    <!-- ======================= VISTA DE ALUMNO (CON CARRUSEL DE TARJETAS) ======================= -->
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-5">
                <div class="dashboard-card" style="max-width: 100%;">
                    <h2 class="card-title-custom">Portal Alumno</h2> 
                    <a href="calificaciones.php" class="dashboard-btn-link"><button class="dashboard-btn btn-calificaciones"><i class="bi bi-clipboard-data"></i> Calificaciones</button></a>
                    <a href="pagos.php" class="dashboard-btn-link"><button class="dashboard-btn btn-pagos"><i class="bi bi-cash-coin"></i> Pagos Pendientes</button></a>
                    <a href="http://plataforma.politefjrz.com/login/index.php" target="_blank" class="dashboard-btn-link"><button class="dashboard-btn btn-plataforma"><i class="bi bi-laptop"></i> Plataforma Digital</button></a>
                    <form method="post" action="logout.php" class="mt-3"><button class="logout-btn" type="submit"><i class="bi bi-box-arrow-right"></i> Cerrar Sesión</button></form>
                </div>
            </div>
            <div class="col-lg-7">
                <h3 class="mb-3 text-white fw-bold"><i class="bi bi-info-circle-fill"></i> Tablón de Anuncios</h3>
                
                <?php if (!empty($anuncios)): ?>
                    <div id="announcementCarousel" class="carousel slide" data-bs-ride="carousel">
                        <div class="carousel-inner">
                            <?php foreach ($anuncios as $index => $anuncio): ?>
                                <div class="carousel-item <?= $index === 0 ? 'active' : '' ?>">
                                    <div class="card">
                                        <?php if (!empty($anuncio['imagen_url']) && file_exists($anuncio['imagen_url'])): ?>
                                            <img src="<?= htmlspecialchars($anuncio['imagen_url']) ?>" class="card-img-top" alt="Imagen del anuncio">
                                        <?php endif; ?>
                                        <div class="card-body">
                                            <h5 class="card-title fw-bold"><?= htmlspecialchars($anuncio['titulo']) ?></h5>
                                            <p class="card-text"><?= nl2br(htmlspecialchars($anuncio['contenido'])) ?></p>
                                        </div>
                                        <div class="card-footer text-muted small">
                                            Publicado el <?= date('d/m/Y', strtotime($anuncio['fecha_creacion'])) ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if (count($anuncios) > 1): // Solo mostrar controles si hay más de un anuncio ?>
                        <button class="carousel-control-prev" type="button" data-bs-target="#announcementCarousel" data-bs-slide="prev">
                            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Anterior</span>
                        </button>
                        <button class="carousel-control-next" type="button" data-bs-target="#announcementCarousel" data-bs-slide="next">
                            <span class="carousel-control-next-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Siguiente</span>
                        </button>
                        <?php endif; ?>
                    </div>
                <?php elseif ($db_error): ?>
                     <div class="alert alert-warning"><?= $db_error ?></div>
                <?php else: ?>
                    <div class="alert alert-light">No hay anuncios para mostrar en este momento.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
