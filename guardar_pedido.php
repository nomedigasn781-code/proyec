<?php
// api/guardar_pedido.php - API para guardar pedidos

require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Validar token de sesión
if (!isset($data['token']) || empty($data['token'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Token de sesión requerido'
    ]);
    exit();
}

// Validar datos del pedido
if (!isset($data['productos']) || !is_array($data['productos']) || empty($data['productos'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Debe incluir al menos un producto'
    ]);
    exit();
}

if (!isset($data['total']) || $data['total'] <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Total del pedido inválido'
    ]);
    exit();
}

$token = sanitize_input($data['token']);
$productos = $data['productos'];
$total = floatval($data['total']);

try {
    $db = Database::getInstance()->getConnection();
    
    // Verificar token y obtener usuario
    $stmt = $db->prepare("
        SELECT usuario_id 
        FROM sesiones 
        WHERE token = ? AND fecha_expiracion > NOW()
    ");
    $stmt->execute([$token]);
    $sesion = $stmt->fetch();
    
    if (!$sesion) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Sesión inválida o expirada'
        ]);
        exit();
    }
    
    $usuario_id = $sesion['usuario_id'];
    
    // Iniciar transacción
    $db->beginTransaction();
    
    // Insertar pedido
    $stmt = $db->prepare("
        INSERT INTO pedidos (usuario_id, total, estado)
        VALUES (?, ?, 'Enviado')
    ");
    $stmt->execute([$usuario_id, $total]);
    $pedido_id = $db->lastInsertId();
    
    // Insertar productos del pedido
    $stmt = $db->prepare("
        INSERT INTO pedido_productos (pedido_id, producto_id, nombre_producto, precio, cantidad)
        VALUES (?, ?, ?, ?, ?)
    ");
    
    foreach ($productos as $producto) {
        // Limpiar el precio (remover $ y puntos)
        $precio_limpio = str_replace(['$', '.', ','], ['', '', '.'], $producto['price']);
        $precio = floatval($precio_limpio);
        
        $stmt->execute([
            $pedido_id,
            $producto['id'],
            sanitize_input($producto['name']),
            $precio,
            isset($producto['cantidad']) ? intval($producto['cantidad']) : 1
        ]);
    }
    
    // Confirmar transacción
    $db->commit();
    
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Pedido guardado exitosamente',
        'data' => [
            'pedido_id' => $pedido_id,
            'total' => $total,
            'fecha' => date('Y-m-d H:i:s')
        ]
    ]);
    
} catch (Exception $e) {
    // Revertir transacción en caso de error
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al guardar el pedido: ' . $e->getMessage()
    ]);
}
?>