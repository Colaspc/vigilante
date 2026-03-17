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
    $email = $_POST['email'] ?? ''; // Si no hay nada antes del ?? hace ''
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $_SESSION['mostrar_error'] = 'Email y contraseña obligatorios';
    } else {
        try {
            $db = getDB();
            $stmt = $db->prepare('SELECT id, nombre, apellidos, email, password_hash FROM users WHERE email = ?');
            $stmt->execute(array($email));
            $user = $stmt->fetch(PDO::FETCH_ASSOC); //esto crea un array asociativo donde password_hash es lo que buscamos

            if ($user && password_verify($password, $user['password_hash'])) { //si hay algo en user entonces ejecuta el p_v donde compara p escrita y la p de la bd 
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_nombre'] = $user['nombre'];
                $_SESSION['user_apellidos'] = $user['apellidos'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['login_time'] = time();

                $_SESSION['mostrar_ok'] = 'Usuario logueado correctamente. Redirigiendo';
                header("Location: logueado.php");
                exit;
            } else {
                $_SESSION['mostrar_error'] = 'Email o contraseña incorrecto';
            }
        } catch (PDOException $e) {
            $_SESSION['mostrar_error'] = 'Error de base de datos' . $e->getMessage();
        }
    }
}

$email_value = isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; // Para no escribir el email tantas veces
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="icon" type="image/png" href="/web/assets-landing/favicon.png">
    <title>Login</title>
</head>

<body class="bg-light">
    <div id="app" class="container d-flex justify-content-center align-items-center vh-100">
        <div class="card py-5 px-4 shadow" style="min-width: 320px; max-width: 400px; width: 100%; ">
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

            <h1 class="h4 mb-4 text-center">Iniciar sesión</h1>

            <div v-if="frontError" class="alert alert-warning">
                {{frontError}}
            </div>

            <form method="POST" @submit="onSubmit">

                <div class="input-group mb-4">
                    <span class="input-group-text">
                        <i class="bi bi-envelope"></i>
                    </span>
                    <input type="email" name="email" class="form-control" v-model="email" value="<?php echo $email_value; ?>">
                </div>

                <div class="input-group mb-4">
                    <span class="input-group-text">
                        <i class="bi bi-lock-fill"></i>
                    </span>
                    <input type="password" id="password" name="password" class="form-control" v-model="password">
                    <button type="button" class="btn btn-outline-secondary" id="togglePassword">
                        <i class="bi bi-eye" id="toggleIcon"></i>
                    </button>
                </div>

                <button type="submit" class="btn btn-primary w-100" :disabled="loading">
                    {{ loading ? 'Entrando...' : 'Entrar' }}
                </button>
            </form>

            <p class="mt-3 text-center">
                <a href="register.php" class="link-registro">No tengo cuenta - Registrarme</a>
            </p>
        </div>
    </div>

    <script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
    <script>
        const {
            createApp
        } = Vue

        createApp({
            data() {
                return {
                    email: "<?php echo $email_value; ?>",
                    password: "",
                    loading: false,
                    frontError: "",
                }
            },
            methods: {
                onSubmit(event) {
                    this.frontError = ""

                    if (!this.email || !this.password) {
                        event.preventDefault()
                        this.frontError = "Completa los datos"
                        return
                    }

                    this.loading = true
                }
            }
        }).mount('#app')
    </script>
    <script>
        const togglePassword = document.querySelector('#togglePassword');
        const passwordInput = document.querySelector('#password');

        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);

            const icon = document.querySelector('#toggleIcon');
            icon.classList.toggle('bi-eye');
            icon.classList.toggle('bi-eye-slash');
        });
    </script>
</body>

</html>