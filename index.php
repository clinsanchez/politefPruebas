<?php
session_start();
// Carga la configuración de la base de datos y las constantes
require_once 'config.php'; 
// Carga el cliente para la conexión a Odoo
require_once 'odoo_client.php';

// Redirigir si ya existe una sesión de cualquier tipo de usuario
if (isset($_SESSION['user_role'])) {
    header("Location: dashboard.php");
    exit();
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $form_user = $_POST['username']; // Puede ser email de admin o matrícula de alumno
    $form_pass = $_POST['password']; // Puede ser contraseña de admin o CURP de alumno

    try {
        // --- PASO 1: Intentar autenticar como Administrador desde la base de datos local ---
        $pdo = new PDO($dsn, DB_USERNAME, DB_PASSWORD, $options);
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
        $stmt->execute([$form_user]);
        $admin_user = $stmt->fetch();

        // Verificar si se encontró un usuario y si la contraseña cifrada coincide
        if ($admin_user && password_verify($form_pass, $admin_user['password'])) {
            // ¡Éxito! El usuario es un administrador.
            session_regenerate_id(true); // Previene la fijación de sesiones
            $_SESSION['user_id'] = $admin_user['id'];
            $_SESSION['user_name'] = $admin_user['nombre'];
            $_SESSION['user_role'] = 'admin';
            header("Location: dashboard.php");
            exit();
        }

        // --- PASO 2: Si no es admin, intentar autenticar como Alumno desde Odoo ---
        $odoo = new OdooClient();
        $students = $odoo->search_read('op.student', [['gr_no', '=', $form_user]], ['fields' => ['id', 'name', 'curp']]);

        if (!empty($students)) {
            $student = $students[0];
            // Se verifica que el campo 'curp' exista y no sea falso antes de comparar
            if (isset($student['curp']) && $student['curp'] && strtolower(trim($student['curp'])) == strtolower(trim($form_pass))) {
                // ¡Éxito! El usuario es un alumno.
                session_regenerate_id(true);
                $_SESSION['student'] = [
                    'id' => $student['id'],
                    'name' => $student['name']
                ];
                $_SESSION['user_name'] = $student['name'];
                $_SESSION['user_role'] = 'student';
                header("Location: dashboard.php");
                exit();
            }
        }

        // --- PASO 3: Si ninguna de las autenticaciones anteriores tuvo éxito ---
        $error = "Credenciales inválidas.";

    } catch (PDOException $e) {
        // Captura errores de conexión con la base de datos local
        $error = "Error de conexión con el servidor. Por favor, intente más tarde.";
        // Para depuración, puedes registrar el error real en un archivo de log en el servidor
        // error_log("Error de PDO: " . $e->getMessage());
    } catch (Exception $e) {
        // Captura errores de conexión con Odoo
        $error = "Error de conexión con Odoo. Por favor, intente más tarde.";
        // error_log("Error de Odoo: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Login - Politef Alumnos</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome para iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Segoe+UI:wght@400;600&display=swap" rel="stylesheet">
    <style>
        /* Estilos base y de fondo */
        body, html {
            height: 100%;
            margin: 0;
            font-family: 'Inter', 'Segoe UI', sans-serif; /* Fuente principal Inter, fallback Segoe UI */
            color: #333; /* Color de texto por defecto */
            overflow-x: hidden; /* Prevenir scroll horizontal */
        }

        .bg-container { /* Contenedor para la imagen de fondo y la superposición */
            position: fixed;
            top: 0;
            left: 0;
            height: 100%;
            width: 100%;
            z-index: -1; /* Detrás de todo el contenido */
            overflow: hidden;
        }

        .bg-image {
            /* Asegúrate que banner.jpg esté en la ruta correcta o usa una URL completa */
            background-image: url('banner.jpg'); 
            background-size: cover;
            background-position: center;
            height: 100%;
            width: 100%;
            filter: blur(3px); /* Opcional: un leve desenfoque para que el formulario resalte más */
            transform: scale(1.05); /* Evitar bordes por el blur */
        }

        /* Superposición de gradiente opcional sobre la imagen de fondo */
        .bg-container::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: linear-gradient(45deg, rgba(0, 50, 100, 0.6), rgba(0, 123, 255, 0.4));
            z-index: 1; /* Encima de la imagen, debajo del contenido */
        }

        /* Contenedor principal para centrar la caja de login */
        .login-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh; /* Asegura que ocupe toda la altura de la ventana */
            padding: 20px; /* Espacio alrededor en pantallas pequeñas */
        }

        /* Estilos para la caja de login */
        .login-box {
            max-width: 430px; /* Ancho máximo de la caja */
            width: 100%; /* Responsivo en pantallas pequeñas */
            background: rgba(255, 255, 255, 0.98); /* Fondo blanco casi opaco para legibilidad */
            padding: 35px 40px; /* Espaciado interno */
            border-radius: 12px; /* Bordes redondeados más suaves */
            box-shadow: 0 12px 35px rgba(0,0,0,0.15); /* Sombra más pronunciada y profesional */
            text-align: center;
            border: 1px solid #dee2e6; /* Borde sutil */
            transition: all 0.3s ease-in-out; /* Transición para efectos hover */
        }
        .login-box:hover {
             box-shadow: 0 18px 45px rgba(0,0,0,0.2); /* Sombra más intensa al pasar el mouse */
        }

        /* Estilos para el logo */
        .logo {
            width: 110px; 
            margin-bottom: 20px; /* Espacio debajo del logo */
            border-radius: 8px; /* Redondeo si el logo es cuadrado/rectangular */
        }

        /* Estilos para el título del portal */
        .login-box h4 {
            color: #0056b3; /* Un azul más oscuro y corporativo */
            font-weight: 600; /* Texto más grueso */
            margin-bottom: 30px; /* Más espacio después del título */
        }

        /* Estilos para los campos de entrada (form-control) */
        .form-control {
            border-radius: 8px; /* Bordes redondeados para los inputs */
            padding: 12px 15px; /* Espaciado interno en los inputs */
            padding-left: 40px; /* Espacio para el icono si se usa position absolute */
            border: 1px solid #ced4da; /* Borde estándar de Bootstrap */
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out; /* Transiciones suaves */
            font-size: 0.95rem;
        }
        .form-control:focus {
            border-color: #007bff; /* Color de borde al enfocar */
            box-shadow: 0 0 0 0.25rem rgba(0, 123, 253, 0.25); /* Sombra de Bootstrap al enfocar */
        }
        
        /* Contenedor para input e icono */
        .input-wrapper {
            position: relative; /* Necesario para posicionar el icono absolutamente */
            margin-bottom: 1.25rem; /* Espacio entre campos, ajustado de mb-3 */
        }
        .input-wrapper .form-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d; /* Color del icono */
            pointer-events: none; /* Para que no interfiera con el click en el input */
        }


        /* Estilos para el botón de Ingresar */
        .btn-primary {
            background-color: #007bff; /* Color primario de Bootstrap */
            border-color: #007bff;
            padding: 12px 20px; /* Espaciado interno del botón */
            border-radius: 8px; /* Bordes redondeados */
            font-weight: 600; /* Texto más grueso */
            text-transform: uppercase; /* Texto en mayúsculas */
            letter-spacing: 0.5px; /* Espaciado entre letras */
            transition: background-color 0.2s ease-in-out, border-color 0.2s ease-in-out, transform 0.1s ease, box-shadow 0.2s ease; /* Transiciones */
            width: 100%; /* Ocupar todo el ancho */
        }
        .btn-primary:hover {
            background-color: #0056b3; /* Color más oscuro al pasar el mouse */
            border-color: #0050a0;
            transform: translateY(-2px); /* Efecto de elevación */
            box-shadow: 0 6px 12px rgba(0, 123, 255, 0.3); /* Sombra al pasar el mouse */
        }
         .btn-primary:active {
            transform: translateY(0); /* Quitar elevación al hacer clic */
            box-shadow: 0 3px 6px rgba(0, 123, 255, 0.2);
        }

        /* Estilos para el mensaje de error */
        .error-message {
            color: #dc3545; /* Color de error de Bootstrap */
            font-size: 0.9em; /* Tamaño de fuente ligeramente menor */
            margin-top: 15px; /* Espacio arriba del mensaje de error */
            font-weight: 500;
        }
        
        /* Pie de página simple */
        .footer {
            position: absolute;
            bottom: 15px;
            left: 0;
            width: 100%;
            text-align: center;
            font-size: 0.85em;
            color: rgba(255, 255, 255, 0.8); /* Color claro para contraste con fondo oscuro */
            z-index: 2; /* Asegurar que esté sobre la superposición del fondo */
        }
        .footer a {
            color: rgba(255, 255, 255, 1);
            text-decoration: none;
            font-weight: 500;
        }
        .footer a:hover {
            text-decoration: underline;
        }

    </style>
</head>
<body>
    <!-- Contenedor para la imagen de fondo y la superposición -->
    <div class="bg-container">
        <div class="bg-image"></div>
    </div>

    <!-- Contenedor principal para centrar la caja de login -->
    <div class="login-container">
        <div class="login-box">
            <!-- Asegúrate que la ruta 'Imagenes/logoPolitef.png' es correcta -->
            <img src="Imagenes/logoPolitef.png" alt="Logo Politef" class="logo img-fluid">
            <h4 class="mb-4">Portal de Alumnos</h4>
            
            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="input-wrapper">
                    <i class="fas fa-user form-icon"></i>
                    <input type="text" name="username" class="form-control" placeholder="Email o Matrícula" required aria-label="Email o Matrícula">
                </div>
                <div class="input-wrapper">
                     <i class="fas fa-lock form-icon"></i>
                    <input type="password" name="password" class="form-control" placeholder="Contraseña o CURP" required aria-label="Contraseña o CURP">
                </div>
                
                <button type="submit" class="btn btn-primary mt-3">Ingresar</button>
                
                <?php if (!empty($error)): ?>
                    <div class="error-message"><?php echo $error; ?></div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Pie de página -->
    <div class="footer">
        &copy; <?php echo date("Y"); ?> <a href="https://politefalumnos.com" target="_blank">Politef Alumnos</a>. Todos los derechos reservados.
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
