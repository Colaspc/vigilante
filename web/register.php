<?php
ini_set('display_errors', 1); // Ver errores
error_reporting(E_ALL);

define('APP_ACCESS', true); // Para permitir archivos config
require_once __DIR__ . '/config/db.php'; // Ejecutar una vez el archivo 

ini_set('session.gc_maxlifetime', 7200); // Guarda la sesion durante 24 horas en el server
session_set_cookie_params(7200); // Guarda la cookie 24 horas en el navegador

session_start();

$mostrar_error = $_SESSION['mostrar_error'] ?? '';
$mostrar_ok = $_SESSION['mostrar_ok'] ?? '';

unset($_SESSION['mostrar_error'], $_SESSION['mostrar_ok']);

if (isset($_SESSION['user_id'])) {
    header('Location: logueado.php');
    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') { // Si el request method es estrictamente igual
    $nombre = $_POST['nombre'] ?? ''; // Si no hay nada antes del ?? hace ''
    $apellidos = $_POST['apellidos'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';


    if (empty($nombre) || empty($apellidos) || empty($email) || empty($password) || empty($password_confirm)) {
        $_SESSION['mostrar_error'] = 'Todos los campos son obligatiorios';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['mostrar_error'] = 'Email no valido';
    } elseif (strlen($password) < 8) {
        $_SESSION['mostrar_error'] = 'La contraseña debe tener al menos 8 caracteres';
    } elseif ($password !== $password_confirm) {
        $_SESSION['mostrar_error'] = 'Las contraseñas no coinciden';
    } else {
        try {
            $db = getDB();

            $stmt = $db->prepare('SELECT email FROM  users WHERE email = ?');
            $stmt->execute(array($email));

            if ($stmt->fetch()) {
                $_SESSION['mostrar_error'] = 'El email ya esta registrado';
            } else {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare('INSERT INTO users (nombre, apellidos, email, password_hash) VALUES (?, ?, ?, ?)');
                $stmt->execute(array($nombre, $apellidos, $email, $password_hash));

                $correcto = 'Usuario registrado correctamente. Redirigiendo ';

                header('refresh:2; url=login.php'); // Esto es para que espere un momento despues de ejecutarse 
            }
        } catch (PDOException $e) {
            $_SESSION['mostrar_error'] = 'Error en base de datos' . $e->getMessage();
        }
    }
}
// Para que no tenga que escribir varias veces
$nombre_value = $_POST['nombre'] ?? '';
$apellidos_value = $_POST['apellidos'] ?? '';
$email_value = $_POST['email'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="icon" type="image/png" href="/web/assets-landing/favicon.png">
    <title>Registrase</title>
</head>

<body class="bg-light">
    <div id="app" class="container d-flex justify-content-center align-items-center vh-100">
        <div class="card py-5 px-4 shadow" style="min-width: 320px; max-width: 400px; width: 100%; ">

            <h1 class="h4 mb-4 text-center">Registro de usuario</h1>

            <?php
            if ($mostrar_error) {
                echo '<div class="alert alert-danger">';
                echo htmlspecialchars($mostrar_error);
                echo '</div>';
            }
            if ($mostrar_ok) {
                echo '<div class="alert alert-success">';
                echo htmlspecialchars($mostrar_ok);
                echo '</div>';
            }
            ?>

            <form method="POST">
                <div class="input-group mb-4">
                    <!--<label>Nombre:</label>-->
                    <input type="text" name="nombre" class="form-control" placeholder="Nombre" required value="<?php echo $nombre_value; ?>">
                </div>

                <div class="input-group mb-4">
                    <!--<label>Apellidos:</label>-->
                    <input type="text" name="apellidos" class="form-control" placeholder="Apellidos" required value="<?php echo $apellidos_value; ?>">
                </div>

                <div class="input-group mb-4">
                    <!--<label>Email:</label>-->
                    <input type="email" name="email" class="form-control" placeholder="Correo" value="<?php echo $email_value; ?>">
                </div>

                <div class="input-group mb-4">
                    <!--<label>Contraseña (mínimo 8 caracteres):</label>-->
                    <input type="password" name="password" class="form-control" placeholder="Contraseña" required>
                </div>

                <div class="input-group mb-4">
                    <!--<label>Confirmar Contraseña:</label>-->
                    <input type="password" name="password_confirm" class="form-control" placeholder="Confirmar contraseña" required>
                </div>

                <button type="submit" class="btn btn-primary w-100">Registrarse</button>
            </form>

            <p class="mt-3 text-center">
                <a href="login.php">Ya tengo cuenta - Ir al Login</a>
            </p>
        </div>
    </div>
</body>

</html>