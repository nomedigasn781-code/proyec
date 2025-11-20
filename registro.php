<?php
// api/registro.php - Procesar registro de usuarios

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

// Validar que lleguen todos los campos requeridos
if (!isset($datos['nombre']) || empty(trim($datos['nombre']))) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'El nombre es obligatorio'
    ]);
    exit();
}

if (!isset($datos['email']) || empty(trim($datos['email']))) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'El email es obligatorio'
    ]);
    exit();
}

if (!isset($datos['telefono']) || empty(trim($datos['telefono']))) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'El teléfono es obligatorio'
    ]);
    exit();
}

if (!isset($datos['direccion']) || empty(trim($datos['direccion']))) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'La dirección es obligatoria'
    ]);
    exit();
}

if (!isset($datos['password']) || strlen($datos['password']) < 6) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'La contraseña debe tener al menos 6 caracteres'
    ]);
    exit();
}

// Limpiar datos
$nombre = limpiarInput($datos['nombre']);
$email = filter_var(trim($datos['email']), FILTER_VALIDATE_EMAIL);
$telefono = limpiarInput($datos['telefono']);
$direccion = limpiarInput($datos['direccion']);
$password = $datos['password'];

// Validar email
if (!$email) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Email inválido'
    ]);
    exit();
}

try {
    $db = conectarDB();
    
    // Verificar si el email ya existe
    $stmt = $db->prepare("SELECT id FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'Este email ya está registrado'
        ]);
        exit();
    }
    
    // Hashear contraseña
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    
    // Insertar usuario
    $stmt = $db->prepare("
        INSERT INTO usuarios (nombre, email, telefono, direccion, password, email_verificado)
        VALUES (?, ?, ?, ?, ?, TRUE)
    ");
    
    $resultado = $stmt->execute([
        $nombre,
        $email,
        $telefono,
        $direccion,
        $passwordHash
    ]);
    
    if ($resultado) {
        $usuarioId = $db->lastInsertId();
        
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'Usuario registrado exitosamente. ¡Ahora puedes iniciar sesión!',
            'data' => [
                'usuario_id' => $usuarioId,
                'email' => $email
            ]
        ]);
    } else {
        throw new Exception('Error al registrar usuario');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error en el servidor: ' . $e->getMessage()
    ]);
}
?>

