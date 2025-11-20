<?php
// api/verificar_email.php - API para verificar código de email

require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!isset($data['email']) || !isset($data['codigo'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Email y código son requeridos'
    ]);
    exit();
}

$email = filter_var(sanitize_input($data['email']), FILTER_VALIDATE_EMAIL);
$codigo = sanitize_input($data['codigo']);

if (!$email) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Email inválido'
    ]);
    exit();
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Buscar usuario con el código
    $stmt = $db->prepare("
        SELECT id, nombre, email, telefono, direccion, fecha_registro
        FROM usuarios 
        WHERE email = ? AND codigo_verificacion = ? AND email_verificado = FALSE
    ");
    $stmt->execute([$email, $codigo]);
    $usuario = $stmt->fetch();
    
    if (!$usuario) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Código incorrecto o email ya verificado'
        ]);
        exit();
    }
    
    // Marcar email como verificado
    $stmt = $db->prepare("
        UPDATE usuarios 
        SET email_verificado = TRUE, codigo_verificacion = NULL 
        WHERE id = ?
    ");
    $stmt->execute([$usuario['id']]);
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Email verificado correctamente',
        'data' => [
            'usuario_id' => $usuario['id'],
            'nombre' => $usuario['nombre'],
            'email' => $usuario['email']
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