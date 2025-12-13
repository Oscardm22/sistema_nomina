<?php
// Configuración de seguridad
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');

// Incluir configuración de base de datos
require_once '../../config/database.php';

// Verificar si hay una sesión activa
$usuario_nombre = isset($_SESSION['usuario_nombre']) ? $_SESSION['usuario_nombre'] : 'Usuario';

// Registrar el logout (opcional - para auditoría)
if (isset($_SESSION['usuario_id'])) {
    try {
        $conn = conectarDB();
        $stmt = $conn->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?");
        $stmt->execute([$_SESSION['usuario_id']]);
    } catch (Exception $e) {
        // Silenciar errores de auditoría, no impedir el logout
    }
}

// Limpiar todas las variables de sesión
$_SESSION = array();

// Borrar la cookie de sesión si existe
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Destruir la sesión
if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

// Redireccionar a login con mensaje
$mensaje = urlencode("Sesión cerrada exitosamente. ¡Hasta pronto, $usuario_nombre!");
header("Location: login.php?mensaje=$mensaje&tipo=success");
exit();
?>