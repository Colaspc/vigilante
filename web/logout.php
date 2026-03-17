<?php
session_start();
session_destroy();
setcookie(session_name(), '', time() - 3600, '/'); // Esto elimina el nombre de la cookie hace que el tiempo se hace una hora y indica que eliminemos de todo el sitio web 
header('Location: login.php');
exit;
?>