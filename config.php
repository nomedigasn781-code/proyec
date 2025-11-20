<?php
// api/config.php - Archivo de configuración

// ⚠️ CONFIGURACIÓN DE LA BASE DE DATOS - CAMBIAR SEGÚN TU XAMPP
define('DB_HOST', 'localhost');
define('DB_NAME', 'eddi_tienda');
define('DB_USER', 'root');
define('DB_PASS', '');  // Dejar vacío en XAMPP por defecto

// Configuración de zona horaria
date_default_timezone_set('America/Asuncion');

// Headers para evitar problemas de CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

// Responder a preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Función para conectar a la base de datos
function conectarDB() {
    try {
        $conn = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $conn;
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error de conexión a la base de datos: ' . $e->getMessage()
        ]);
        exit();
    }
}

// Función para limpiar datos de entrada
function limpiarInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Función para generar token de sesión
function generarToken() {
    return bin2hex(random_bytes(32));
}
?>
