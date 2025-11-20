<?php
// api/obtener_historial.php - API para obtener historial de pedidos

require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

// Validar token
if (!isset($_GET['token']) || empty($_GET['token'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Token de sesión requerido'
    ]);
    exit();
}

$token = sanitize_input($_GET['token']);

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
    
    // Obtener pedidos del usuario
    $stmt = $db->prepare("
        SELECT id, total, estado, fecha
        FROM pedidos
        WHERE usuario_id = ?
        ORDER BY fecha DESC
    ");
    $stmt->execute([$usuario_id]);
    $pedidos = $stmt->fetchAll();
    
    // Para cada pedido, obtener sus productos
    $stmt_productos = $db->prepare("
        SELECT producto_id, nombre_producto, precio, cantidad
        FROM pedido_productos
        WHERE pedido_id = ?
    ");
    
    $historial = [];
    foreach ($pedidos as $pedido) {
        $stmt_productos->execute([$pedido['id']]);
        $productos = $stmt_productos->fetchAll();
        
        $historial[] = [
            'id' => $pedido['id'],
            'fecha' => $pedido['fecha'],
            'total' => floatval($pedido['total']),
            'estado' => $pedido['estado'],
            'productos' => array_map(function($p) {
                return [
                    'id' => $p['producto_id'],
                    'name' => $p['nombre_producto'],
                    'price' => '$' . number_format($p['precio'], 0, ',', '.'),
                    'cantidad' => $p['cantidad']
                ];
            }, $productos)
        ];
    }
    
    // Calcular estadísticas
    $total_pedidos = count($historial);
    $total_gastado = array_sum(array_column($historial, 'total'));
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => [
            'pedidos' => $historial,
            'estadisticas' => [
                'total_pedidos' => $total_pedidos,
                'total_gastado' => $total_gastado
            ]
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener historial: ' . $e->getMessage()
    ]);
}
?>