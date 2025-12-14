<?php
require_once '../../config/database.php';
requerirLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

if (!isset($_POST['empresa_id']) || !isset($_POST['nuevo_estatus'])) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit();
}

$conn = conectarDB();
$usuario_id = $_SESSION['usuario_id'];
$empresa_id = intval($_POST['empresa_id']);
$nuevo_estatus = intval($_POST['nuevo_estatus']);

// Verificar que la empresa exista y pertenezca al usuario
$stmt = $conn->prepare("SELECT id FROM empresas WHERE id = ? AND usuario_id = ?");
$stmt->execute([$empresa_id, $usuario_id]);

if ($stmt->rowCount() === 0) {
    echo json_encode(['success' => false, 'message' => 'Empresa no encontrada']);
    exit();
}

// Actualizar el estatus
try {
    $stmt = $conn->prepare("UPDATE empresas SET activa = ? WHERE id = ? AND usuario_id = ?");
    $stmt->execute([$nuevo_estatus, $empresa_id, $usuario_id]);
    
    $estado_texto = $nuevo_estatus == 1 ? 'activada' : 'desactivada';
    echo json_encode([
        'success' => true, 
        'message' => 'Empresa ' . $estado_texto . ' exitosamente'
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error al actualizar: ' . $e->getMessage()]);
}