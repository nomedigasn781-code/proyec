<?php
// api/login.php - Procesar inicio de sesión

require_once 'config.php';

// Solo aceptar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido'
    ]);
    exit();
}

// Obtener datos JSON
$json = file_get_contents('php://input');
$datos = json_decode($json, true);

// Validar campos requeridos
if (!isset($datos['usuario']) || empty(trim($datos['usuario']))) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Usuario es requerido'
    ]);
    exit();
}

if (!isset($datos['password']) || empty($datos['password'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Contraseña es requerida'
    ]);
    exit();
}

$usuarioInput = limpiarInput($datos['usuario']);
$password = $datos['password'];

try {
    $db = conectarDB();
    
    // Buscar usuario por email o nombre
    $stmt = $db->prepare("
        SELECT id, nombre, email, telefono, direccion, password, fecha_registro
        FROM usuarios 
        WHERE email = ? OR nombre = ?
    ");
    $stmt->execute([$usuarioInput, $usuarioInput]);
    $usuario = $stmt->fetch();
    
    if (!$usuario) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Usuario o contraseña incorrectos'
        ]);
        exit();
    }
    
    // Verificar contraseña
    if (!password_verify($password, $usuario['password'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Usuario o contraseña incorrectos'
        ]);
        exit();
    }
    
    // Generar token de sesión
    $token = generarToken();
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $fechaExpiracion = date('Y-m-d H:i:s', strtotime('+30 days'));
    
    // Guardar sesión en la base de datos
    $stmt = $db->prepare("
        INSERT INTO sesiones (usuario_id, token, ip_address, user_agent, fecha_expiracion)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $usuario['id'],
        $token,
        $ipAddress,
        $userAgent,
        $fechaExpiracion
    ]);
    
    // Actualizar última conexión
    $stmt = $db->prepare("UPDATE usuarios SET ultima_conexion = NOW() WHERE id = ?");
    $stmt->execute([$usuario['id']]);
    
    // Responder con datos del usuario (sin contraseña)
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Inicio de sesión exitoso',
        'data' => [
            'token' => $token,
            'usuario' => [
                'id' => $usuario['id'],
                'nombre' => $usuario['nombre'],
                'email' => $usuario['email'],
                'telefono' => $usuario['telefono'],
                'direccion' => $usuario['direccion'],
                'fecha_registro' => $usuario['fecha_registro']
            ]
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error en el servidor: ' . $e->getMessage()
    ]);
}
?>