<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="icon" type="image/png" href="/web/assets-landing/favicon.png">
    <style>
        .carousel-item {
            transition: transform 0.8s ease-in-out;
        }

        .carousel-control-prev-icon,
        .carousel-control-next-icon {
            filter: invert(29%) sepia(98%) saturate(100%) hue-rotate(199deg) brightness(95%);
        }
    </style>
    <title>Software de Monitorización - Vigilante</title>
</head>

<body>
    <nav class="navbar navbar-expand-lg bg-white shadow-sm fixed-top">
        <div class="container">
            <a href="#" class="navbar-brand fw-bold" style="user-select: none;">VIGILANTE</a>
            <div class="d-flex gap-2">
                <a href="web/login.php" class="btn btn-outline-secondary">Iniciar sesión</a>
                <a href="web/register.php" class="btn btn-success">Registrarse</a>
            </div>
        </div>
    </nav>

    <section style="
        margin-top: 56px;
        position: relative;
        height: 500px;
        overflow: hidden;
        ">
        <div style="
            position: absolute;
            inset: 0;
            background: url(https://images.unsplash.com/photo-1629904853716-f0bc54eea481?w=1400) center/cover no-repeat;
            filter: blur(4px);
            transform: scale(1.05);"></div>
        <div style="position: absolute; inset: 0; background: rgba(0,0,0,0.4)"></div>

        <div class="container text-center position-relative h-100 d-flex flex-column justify-content-center align-items-center" style="z-index: 1; user-select: none;">
            <img src="web/assets-landing/logo-hero.svg" alt="logo" class="img-fluid mb-3" style="max-width: 280px;">
            <h1 class="display-4 fw-bold text-white">VIGILANTE</h1>
            <p class="lead mt-3 text-white">Supervisión en tiempo real desde un solo panel</p>
        </div>

    </section>

    <section class="container my-5">
        <div id="carruselLanding" class="carousel slide bg-secondary" data-bs-ride="carousel">
            <div class="carousel-indicators">
                <button type="button" data-bs-target="#carruselLanding" data-bs-slide-to="0" class="active bg-primary"></button>
                <button type="button" data-bs-target="#carruselLanding" data-bs-slide-to="1" class="bg-primary"></button>
                <button type="button" data-bs-target="#carruselLanding" data-bs-slide-to="2" class="bg-primary"></button>
                <button type="button" data-bs-target="#carruselLanding" data-bs-slide-to="3" class="bg-primary"></button>
            </div>

            <div class="carousel-inner rounded shadow">
                <div class="carousel-item active">
                    <img src="web/assets-landing/1.png" style="height:420px; object-fit: contain;" alt="1" class="d-block w-100">
                    <div class="carousel-caption d-block bg-dark bg-opacity-75 rounded mb-3">
                        <h5 class="text-white">Panel Principal</h5>
                        <p class="small text-white">Visualiza todos tus dispositivos registrados desde un único panel de control</p>
                    </div>
                </div>

                <div class="carousel-item">
                    <img src="web/assets-landing/2.jpeg" style="height:420px; object-fit: contain;" alt="2" class="d-block w-100">
                    <div class="carousel-caption d-block bg-dark bg-opacity-75 rounded mb-3">
                        <h5>Monitorización en tiempo real</h5>
                        <p class="small">Consulta el uso de CPU, RAM y disco de cada equipo al instante</p>
                    </div>
                </div>

                <div class="carousel-item">
                    <img src="web/assets-landing/3.jpeg" style="height:420px; object-fit: contain;" alt="3" class="d-block w-100">
                    <div class="carousel-caption d-block bg-dark bg-opacity-75 rounded mb-3">
                        <h5>Historial de eventos</h5>
                        <p class="small">Registra toda la actividad: webs visitadas, aplicaciones abiertas y cambios en carpetas</p>
                    </div>
                </div>

                <div class="carousel-item">
                    <img src="web/assets-landing/4.jpeg" style="height:420px; object-fit: contain;" alt="4" class="d-block w-100">
                    <div class="carousel-caption d-block bg-dark bg-opacity-75 rounded mb-3">
                        <h5>Capturas de pantalla</h5>
                        <p class="small">Obtén capturas periòdicas de la pantalla del dispositivo supervisado</p>
                    </div>
                </div>
            </div>

            <button type="button" class="carousel-control-prev" data-bs-target="#carruselLanding" data-bs-slide="prev">
                <span class="carousel-control-prev-icon"></span>
            </button>
            <button type="button" class="carousel-control-next" data-bs-target="#carruselLanding" data-bs-slide="next">
                <span class="carousel-control-next-icon"></span>
            </button>

        </div>
    </section>

    <footer class="bg-primary text-white mt-5 py-4">
        <div class="container text-center">
            <p class="mb-1 fw-bold">VIGILANTE</p>
            <p class="small text-white-50 mb-1">Aplicación web de Monitorización</p>
            <p class="small text-white-50 mb-0">© 2026 Nicolás Britos - Francisco García - Agustín Videla</p>
        </div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>