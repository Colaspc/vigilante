<?php
define('APP_ACCESS', true);
require_once __DIR__ . '/db.php'; 

$json_data = file_get_contents("php://input");
$data = json_decode($json_data, true);

if (isset($data['c8'])) {
    $db = getDB();
    
    // 1. Verificamos si existe
    $stmt = $db->prepare('SELECT * FROM computers WHERE computer_code = ?');
    $stmt->execute([$data['c8']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        // 2. Activamos
        $update = $db->prepare('UPDATE computers SET is_active = 1 WHERE computer_code = ?');
        $update->execute([$data['c8']]);

        // 3. Respuesta con lo que pide el PowerShell
        echo json_encode([
            "success" => true,
            "data" => [
                "computer_name" => $row['computer_name'], // Enviamos el nombre real
                "api_token" => $row['api_token']      // Enviamos el token real
            ]
        ]);
    } else {
        echo json_encode(["success" => false, "error" => "No existe"]);
    }
}
?>