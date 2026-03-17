<?php
//======================================================================
// SECCION INICIAL
//======================================================================
//-----------------------------------------------------
// Ver erros y añadir archivos de config 
//-----------------------------------------------------

ini_set('display_errors', 1);
error_reporting(E_ALL);

define('APP_ACCESS', true);
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/functions.php';


//-----------------------------------------------------
// Sesiones
//-----------------------------------------------------

ini_set('session.gc_maxlifetime', 7200);
session_set_cookie_params(7200);
session_start();

if (!isset($_SESSION['user_id'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}
if (time() - $_SESSION['login_time'] > 7200) {
    session_destroy();
    header('Location: login.php');
    exit;
}
$_SESSION['login_time'] = time();

$user_id = $_SESSION['user_id'];

//-----------------------------------------------------
// Manejo de errores
//-----------------------------------------------------

$error = '';
$correcto = '';


if (isset($_SESSION['error_temp'])) {
    $error = $_SESSION['error_temp'];
    unset($_SESSION['error_temp']);
}
if (isset($_SESSION['success_temp'])) {
    $correcto = $_SESSION['success_temp'];
    unset($_SESSION['success_temp']);
}

//======================================================================
// SECCION ELIMINAR UN PC
//======================================================================

if (isset($_POST['delete_pc'])) {
    $computer_id = $_POST['delete_pc'];
    try {
        $db = getDB();
        // Verificar que el PC pertenece al usuario y está inactivo
        $stmt = $db->prepare('SELECT is_active FROM computers WHERE id = ? AND user_id = ?');
        $stmt->execute(array($computer_id, $user_id));
        $pc = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($pc) {
            $stmt = $db->prepare('DELETE FROM computers WHERE id = ? AND user_id = ?');
            $stmt->execute(array($computer_id, $user_id));
            $_SESSION['success_temp'] = 'PC eliminado correctamente';
        }
    } catch (PDOException $e) {
        $_SESSION['error_temp'] = 'Error al eliminar: ' . $e->getMessage();
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}
//======================================================================
// SECCION REFRESCAR TOKEN PC
//======================================================================

if (isset($_POST['refresh_token'])) {
    $computer_id = $_POST['refresh_token'];
    try {
        $db = getDB();
        $stmt = $db->prepare('SELECT is_active FROM computers WHERE id = ? AND user_id = ?');
        $stmt->execute(array($computer_id, $user_id));
        $pc = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($pc) {
            $api_token = bin2hex(random_bytes(32));
            $stmt = $db->prepare('UPDATE computers SET is_active=0, api_token=? WHERE id = ?');
            $stmt->execute(array($api_token, $computer_id));
            $_SESSION['success_temp'] = 'PC ha sido refrescado correctamente';
        }
    } catch (PDOException $e) {
        $_SESSION['error_temp'] = 'Error al refrescar: ' . $e->getMessage();
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

//======================================================================
// SECCION GUARDAR CONFIGURACIÓN DEL AGENTE (bloqueos + carpetas)
//======================================================================

if (isset($_POST['save_config'])) {
    $computer_id = (int) $_POST['config_computer_id'];
    try {
        $db = getDB();

        // Verificar que el PC pertenece al usuario logueado
        $stmt = $db->prepare('SELECT id FROM computers WHERE id = ? AND user_id = ?');
        $stmt->execute([$computer_id, $user_id]);
        if (!$stmt->fetch()) {
            $_SESSION['error_temp'] = 'PC no encontrado';
            header("Location: ?view=" . $computer_id);
            exit;
        }

        $bloqueos = trim($_POST['bloqueos'] ?? '');
        $carpetas = trim($_POST['carpetas'] ?? '');

        // UPSERT: inserta si no existe fila para este PC, actualiza si ya existe
        $stmt = $db->prepare('
            INSERT INTO pc_config (computer_id, bloqueos, carpetas)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE
                bloqueos   = VALUES(bloqueos),
                carpetas   = VALUES(carpetas),
                updated_at = NOW()
        ');
        $stmt->execute([$computer_id, $bloqueos, $carpetas]);

        $_SESSION['success_temp'] = 'Configuración guardada. El agente la aplicará en el próximo ciclo (máx. 30s).';
    } catch (PDOException $e) {
        $_SESSION['error_temp'] = 'Error al guardar: ' . $e->getMessage();
    }
    header("Location: ?view=" . $computer_id);
    exit;
}

//======================================================================
// SECCION AÑADIR UN PC
//======================================================================

if (isset($_POST['computer_name'])) {
    // Verificar si ya existe un PC inactivo
    try {
        $db = getDB();
        $stmt = $db->prepare('SELECT COUNT(*) as count FROM computers WHERE user_id = ? AND is_active = 0');
        $stmt->execute(array($user_id));
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result['count'] > 0) {
            $_SESSION['error_temp'] = 'No puedes añadir otro PC mientras tengas uno inactivo';
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    } catch (PDOException $e) {
        $_SESSION['error_temp'] = 'Error en base de datos: ' . $e->getMessage();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    $api_token = bin2hex(random_bytes(32));
    $resultado = boton_add_pc($user_id, $_POST['computer_name'], $api_token);
    // Si hay un error lo guarda en la sesion
    if ($resultado['success'] === false) {
        $_SESSION['error_temp'] = $resultado['message'];
    } else {
        $_SESSION['success_temp'] = $resultado['message'];
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

//======================================================================
// SECCION VISUALIZAR PCs
//======================================================================
//-----------------------------------------------------
// Conjunto de boxes
//-----------------------------------------------------

function render_boxes($computer_name, $computer_info, $computer_id)
{
    $estado = $computer_info ? 'activo' : 'inactivo';
    $badge = $computer_info ? 'bg-success' : 'bg-secondary';

    echo '<div class="col-12 col-sm-6 col-md-4 col-lg-3">';
    echo '<div class="card border-primary border-2 h-100">';
    echo '<a href="?view=' . $computer_id . '" class="box ' . $estado . ' text-white text-center">';
    echo '<div class="card-header bg-primary d-flex justify-content-between align-items-center">';
    echo '<span><i class="bi bi-pc-display me-1"></i>' . htmlspecialchars($computer_name) . '</span>';
    echo '<span class="badge ' . $badge . '">' . $estado . '</span>';
    echo '</div>';
    echo '</a>';

    // Menú desplegable
    echo '<div class="card-body p-2 bg-primary bg-opacity-50">';
    echo '<div class="dropdown">';
    echo '<button class="btn btn-outline-secondary btn-sm w-100 dropdown-toggle bg-primary text-white" type="button" data-bs-toggle="dropdown">';
    echo '<i class="bi bi-three-dots me-1"></i>Opciones</button>';
    echo '<ul class="dropdown-menu w-100">';

    if (!$computer_info) {
        echo '<li><form method="POST" class="m-0">';
        echo '<input type="hidden" name="delete_pc" value="' . $computer_id . '">';
        echo '<button type="submit" class="dropdown-item text-danger" onclick="return confirm(\'¿Estás seguro de eliminar este PC?\')">';
        echo '<i class="bi bi-trash me-2"></i>Eliminar</button>';
        echo '</form></li>';
    } else {
        // Si está activo, mostrar configuración 
        echo '<li><a href="?view=' . $computer_id . '" class="dropdown-item">';
        echo '<i class="bi bi-gear me-2"></i>Configuración</a></li>';
        echo '<li><hr class="dropdown-divider"></li>';
        echo '<li><form method="POST" class="m-0">';
        echo '<input type="hidden" name="delete_pc" value="' . $computer_id . '">';
        echo '<button type="submit" class="dropdown-item text-danger" onclick="return confirm(\'¿Estás seguro de eliminar este PC?\')">';
        echo '<i class="bi bi-trash me-2"></i>Eliminar</button>';
        echo '</form></li>';
    }

    echo '</ul>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
}

function auto_boxes()
{
    echo '<div class="container my-4">';
    echo '<div class="row g-3">';
    try {
        $db = getDB();
        $stmt = $db->prepare('SELECT id,computer_name,is_active FROM  computers WHERE user_id = ?');
        $stmt->execute(array($_SESSION['user_id']));
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$results) {
            echo '<p class="text-center text-muted">No hay computadoras registradas</p>';
            return;
        }
        foreach ($results as $row) {
            render_boxes($row['computer_name'], $row['is_active'], $row['id']);
        }
    } catch (PDOException $e) {
        $_SESSION['error_temp'] = 'Error en base de datos: ' . $e->getMessage();
    }
    echo "</div>";
    echo "</div>";
}

//-----------------------------------------------------
// Visualizar solo un pc
//-----------------------------------------------------

function render_view()
{
    $computer_id = $_GET['view'];
    try {
        $db = getDB();
        $stmt = $db->prepare('SELECT * FROM computers WHERE id = ? AND user_id = ?');
        $stmt->execute(array($computer_id, $_SESSION['user_id']));
        $pc = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($pc) {
?>
            <div class="container my-4">

                <!-- Botón volver -->
                <a href="?" class="btn btn-outline-primary btn-sm mb-3">
                    <i class="bi bi-arrow-left me-1"></i>Volver
                </a>

                <!-- Información del PC -->
                <div class="card border-primary border-2 mb-4">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-pc-display me-2"></i><?php echo htmlspecialchars($pc['computer_name']); ?></h5>
                    </div>

                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item"><strong>Código:</strong> <?php echo htmlspecialchars($pc['computer_code']); ?></li>
                            <li class="list-group-item"><strong>Estado:</strong> <?php echo $pc['is_active'] ? 'Activo' : 'Inactivo'; ?></li>
                        </ul>
                        <?php if (!$pc['is_active']): ?>
                            <form method="POST" class="mt-3">
                                <input type="hidden" name="delete_pc" value="<?php echo $computer_id; ?>">
                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('¿Estas seguro de eliminar este PC?')">
                                    <i class="bi bi-trash me-1"></i> Eliminar PC
                                </button>
                            </form>
                        <?php endif; ?>

                        <?php if ($pc['is_active']):     ?>
                            <form method="POST" class="mt-3">
                                <input type="hidden" name="refresh_token" value="<?php echo $computer_id; ?>">
                                <button type="submit" class="btn btn-warning btn-sm" onclick="return confirm('¿Estas seguro que quieres refrescar la conexion?')">
                                    <i class="bi bi-arrow-clockwise me-1"></i>Refrescar conexion
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ============================================================ -->
                <!-- Configuración del agente (bloqueos + carpetas)               -->
                <!-- ============================================================ -->
                <div class="card border-primary border-2 mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-gear me-2"></i>Configuración del Agente</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small">Los cambios se aplican en el equipo monitorizado en el próximo ciclo de envío (máx. 30 segundos)</p>

                        <?php
                        // Cargar configuración guardada para este PC (si existe)
                        $stmt_cfg = $db->prepare('SELECT bloqueos, carpetas, updated_at FROM pc_config WHERE computer_id = ?');
                        $stmt_cfg->execute([$computer_id]);
                        $cfg = $stmt_cfg->fetch(PDO::FETCH_ASSOC);

                        $bloqueos_val = htmlspecialchars($cfg['bloqueos'] ?? "facebook\ntiktok\nyoutube");
                        $carpetas_val = htmlspecialchars($cfg['carpetas'] ?? "C:\\Users\\Usuario\\Desktop\nC:\\Users\\Usuario\\Documents");
                        $updated      = $cfg['updated_at'] ?? null;
                        ?>

                        <?php if ($updated): ?>
                            <p style="font-size:12px; color:#999; margin-bottom: 10px;">
                                Última actualización guardada: <strong><?php echo htmlspecialchars($updated); ?></strong>
                            </p>
                        <?php endif; ?>

                        <form method="POST">
                            <input type="hidden" name="save_config" value="1">
                            <input type="hidden" name="config_computer_id" value="<?php echo (int)$computer_id; ?>">

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">
                                        <i class="bi bi-slash-circle me-1"></i> Webs a bloquear
                                        <span class="text-muted fw-normal small">(un dominio por línea)</span>
                                    </label>
                                    <textarea
                                        name="bloqueos"
                                        rows="10"
                                        class="form-control font-monospace"
                                        placeholder="facebook&#10;tiktok&#10;youtube"><?php echo $bloqueos_val; ?></textarea>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-bold">
                                        <i class="bi bi-folder me-1"></i> Carpetas a vigilar
                                        <span class="text-muted fw-normal small">(una ruta por línea)</span>
                                    </label>
                                    <textarea
                                        name="carpetas"
                                        rows="10"
                                        class="form-control font-monospace"
                                        placeholder="C:\Users\Alumno\Desktop&#10;C:\Users\Alumno\Documents"><?php echo $carpetas_val; ?></textarea>
                                </div>
                            </div>

                            <button
                                type="submit"
                                class="btn btn-primary mt-3"
                                onclick="return confirm('¿Guardar configuración? El agente la aplicará en el próximo ciclo.')">
                                <i class="bi bi-floppy me-1"></i> Guardar configuración
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Datos del sistema (computer_data) -->
                <div class="card border-primary border-2 mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-cpu me-2"></i>Últimos Datos del Sistema</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $stmt_data = $db->prepare('SELECT * FROM computer_data WHERE computer_id = ? ORDER BY created_at DESC LIMIT 5');
                        $stmt_data->execute(array($computer_id));
                        $datos = $stmt_data->fetchAll(PDO::FETCH_ASSOC);

                        if ($datos) {
                            foreach ($datos as $dato) {
                                $parametros = json_decode($dato['parametro'], true);
                                echo '<ul class="list-group list-group-flush mb-3">';
                                echo '<li class="list-group-item"><strong>Fecha:</strong> ' . $dato['created_at'] . '</li>';
                                if ($parametros) {
                                    foreach ($parametros as $key => $value) {
                                        echo '<li class="list-group-item"><strong>' . htmlspecialchars($key) . ':</strong> ' . htmlspecialchars($value) . '</li>';
                                    }
                                }
                                echo '</ul>';
                            }
                        } else {
                            echo '<p class="text-muted">No hay datos registrados</p>';
                        }
                        ?>
                    </div>
                </div>

                <!-- Capturas de Pantalla -->
                <div class="card border-primary border-2 mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-camera me-2"></i>Últimas Capturas de Pantalla</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $stmt_screens = $db->prepare('
                                            SELECT id, created_at 
                                            FROM computer_screenshots 
                                            WHERE computer_id = ? 
                                            ORDER BY created_at DESC 
                                            LIMIT 3
                                        ');
                        $stmt_screens->execute(array($computer_id));
                        $capturas = $stmt_screens->fetchAll(PDO::FETCH_ASSOC);

                        if ($capturas) {
                            echo '<div id="carruselCap" class="carousel slide bg-secondary rounded" data-bs-ride="false">';
                            echo '<div class="carousel-inner">';

                            foreach ($capturas as $i =>  $captura) {
                                $active = $i === 0 ? 'active' : '';
                                echo '<div class="carousel-item ' . $active . '">';
                                echo '<img src="config/get_screenshoot.php?id=' . (int)$captura['id'] . '"
                               class="d-block w-100"
                               style="max-height:400px; object-fit:contain;"
                               alt="Captura ' . htmlspecialchars($captura['created_at']) . '"
                               loading="lazy">';
                                echo '<div class="carousel-caption d-none d-md-block bg-dark bg-opacity-50 rounded">';
                                echo '<p class="mb-0 small">' . htmlspecialchars(date('d/m/Y H:i:s', strtotime($captura['created_at']))) . '</p>';
                                echo '</div>';
                                echo '</div>';
                            }
                            echo '</div>';
                            echo '<button class="carousel-control-prev" type="button" data-bs-target="#carruselCap" data-bs-slide="prev">';
                            echo '<span class="carousel-control-prev-icon"></span></button>';
                            echo '<button class="carousel-control-next" type="button" data-bs-target="#carruselCap" data-bs-slide="next">';
                            echo '<span class="carousel-control-next-icon"></span></button>';
                            echo '</div>';
                        } else {
                            echo '<p class="text-muted">No hay capturas registradas aún</p>';
                        }
                        ?>
                    </div>
                </div>

                <!-- Historial (computer_history) -->
                <div class="card border-primary border-2 mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Historial de Eventos</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="p-3 pb-0 d-flex gap-2">
                            <a href="?view=<?php echo $computer_id; ?>&filtro=todos"
                            class="btn btn-sm <?php echo (!isset($_GET['filtro']) || $_GET['filtro']==='todos') ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                Todos
                            </a>
                            <a href="?view=<?php echo $computer_id; ?>&filtro=archivos"
                            class="btn btn-sm <?php echo (isset($_GET['filtro']) && $_GET['filtro']==='archivos') ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                Solo archivos
                            </a>
                        </div>
                        <?php
                        if (isset($_GET['filtro']) && $_GET['filtro'] === 'archivos') {
                            $stmt_history = $db->prepare("SELECT * FROM computer_history WHERE computer_id = ? AND evento IN ('FILE_Created','FILE_Changed','FILE_Deleted') ORDER BY created_at DESC LIMIT 30");
                        } else {
                            $stmt_history = $db->prepare('SELECT * FROM computer_history WHERE computer_id = ? ORDER BY created_at DESC LIMIT 30');
                        }
                        $stmt_history->execute(array($computer_id));
                        $historial = $stmt_history->fetchAll(PDO::FETCH_ASSOC);

                        if ($historial) {
                            echo '<div class="table-responsive">';
                            echo '<table class="table table-striped table-hover table-bordered mb-0">';
                            echo '<thead class="table-primary">';
                            echo '<tr>';
                            echo '<th>Evento</th>';
                            echo '<th>Sitio</th>';
                            echo '<th>Equipo</th>';
                            echo '<th>Usuario</th>';
                            echo '<th>Fecha</th>';
                            echo '</tr>';
                            echo '</thead><tbody>';

                            foreach ($historial as $evento) {
                                echo '<tr>';
                                echo '<td>' . htmlspecialchars($evento['evento']) . '</td>';
                                echo '<td>' . htmlspecialchars($evento['sitio'] ?? 'N/A') . '</td>';
                                echo '<td>' . htmlspecialchars($evento['equipo'] ?? 'N/A') . '</td>';
                                echo '<td>' . htmlspecialchars($evento['usuario'] ?? 'N/A') . '</td>';
                                echo '<td>' . $evento['created_at'] . '</td>';
                                echo '</tr>';
                            }
                            echo '</tbody></table>';
                            echo '</div>';
                        } else {
                            echo '<p class="text-muted p-3 mb-0">No hay historial registrado</p>';
                        }
                        ?>
                    </div>
                </div>

            </div>
<?php
        } else {
            echo '<div class="container my-4">';
            echo '<p class="text-danger">PC no encontrado</p>';
            echo '<a href="?" class="btn btn-outline-primary btn-sm"><i class="bi bi-arrow-left me-1"></i>Volver</a>';
            echo '</div>';
        }
    } catch (PDOException $e) {
        $_SESSION['error_temp'] = 'Error en base de datos: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logueado</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="icon" type="image/png" href="/web/assets-landing/favicon.png">
</head>

<body class="bg-light">
    <!-- ---------------------------------------------- -->
    <!-- Manejo de errores                                -->
    <!-- ---------------------------------------------- -->
    <?php
    if (!empty($error)) {
        echo '<p style="color: red ;">';
        echo htmlspecialchars($error);
        echo '</p>';
    }
    if (!empty($correcto)) {
        echo '<p style="color: green ;">';
        echo htmlspecialchars($correcto);
        echo '</p>';
    }
    ?>
    <!-- =============================================================== -->
    <!-- SECCIÓN TE HAS LOGUEADO                                         -->
    <!-- =============================================================== -->

    <!-- <h1 style="color: green;">Te has logueado correctamente</h1>-->

    <nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
        <div class="container-fluid">
            <!-- Logo Monitorización -->
            <a href="logueado.php" class="navbar-brand">
                Monitorización
            </a>

            <!-- Botón hamburgesa -->
            <button class="navbar-toggler" type="button"
                data-bs-toggle="collapse"
                data-bs-target="#mainNavbar"
                aria-controls="mainNavbar"
                aria-expanded="false"
                aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <!-- Bloque Colapsable -->
            <div class="collapse navbar-collapse" id="mainNavbar">
                <div class="w-100 d-flex flex-column flex-lg-row align-items-center">
                    <!-- Usuario y email -->
                    <div class="flex-grow-1 text-center text-white mb-2 mb-lg-O">
                        <span class="d-block">
                            <?php echo htmlspecialchars($_SESSION['user_nombre'] . ' ' . $_SESSION['user_apellidos']); ?>
                        </span>
                        <span class="d-block small">
                            <?php echo htmlspecialchars($_SESSION['user_email']); ?>
                        </span>
                    </div>
                    <!-- Botón logout -->
                    <form action="logout.php" class="d-flex" method="POST">
                        <button class="btn btn-outline-light" type="submit" name="logout">
                            Cerrar Sesión
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    <!-- =============================================================== -->
    <!-- SECCIÓN AÑADIR PC                                               -->
    <!-- =============================================================== -->
    <div class="container my-4">
        <div class="row justify-content-center">
            <div class="col-12 col-md-6 col-lg-4">
                <div class="add-pc text-center">
                    <h3 class="mb-3">Añadir ordenador</h3>
                    <form method="POST">
                        <div class="mb-3">
                            <label>Escribe el nombre para tu nuevo ordenador</label><br>
                            <input type="text" name="computer_name" required><br><br>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Entrar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <hr>
    <!-- =============================================================== -->
    <!-- SECCION VISUALIZAR INFORMACION PC  boxes-views                  -->
    <!-- =============================================================== -->
    <?php
    if (isset($_GET['view'])) {
        render_view();
    } else {
        auto_boxes();
    }
    ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const carrusel = document.querySelector('#carruselCap');
            if (carrusel && !e1._carousel) {
                new bootstrap.Carousel(carrusel, {
                    interval: false
                })
            }
        });
    </script>
</body>

</html>