<?php
require_once '../../config/database.php';
requerirLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

if (!isset($_POST['empleado_id']) || !isset($_POST['nuevo_estatus'])) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit();
}

$empleado_id = intval($_POST['empleado_id']);
$nuevo_estatus = intval($_POST['nuevo_estatus']);
$usuario_id = $_SESSION['usuario_id'];

try {
    $conn = conectarDB();
    
    // Verificar que el empleado pertenezca a una empresa del usuario
    $stmt = $conn->prepare("
        SELECT e.id 
        FROM empleados e
        INNER JOIN empresas emp ON e.empresa_id = emp.id
        WHERE e.id = ? AND emp.usuario_id = ?
    ");
    $stmt->execute([$empleado_id, $usuario_id]);
    $empleado = $stmt->fetch();
    
    if (!$empleado) {
        echo json_encode(['success' => false, 'message' => 'Empleado no encontrado']);
        exit();
    }
    
    // Actualizar el estatus
    $stmt = $conn->prepare("UPDATE empleados SET activo = ? WHERE id = ?");
    $result = $stmt->execute([$nuevo_estatus, $empleado_id]);
    
    if ($result) {
        $mensaje = $nuevo_estatus == 1 
            ? 'Empleado activado correctamente' 
            : 'Empleado desactivado correctamente';
        
        echo json_encode(['success' => true, 'message' => $mensaje]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al actualizar']);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}