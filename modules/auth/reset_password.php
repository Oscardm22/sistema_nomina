<?php
require_once '../../config/database.php';

// Si ya está logueado, redirigir al dashboard
if (estaLogueado()) {
    header("Location: " . BASE_URL . "dashboard.php");
    exit();
}

$error = '';
$success = '';
$token_valido = false;
$token = isset($_GET['token']) ? limpiar($_GET['token']) : '';

// Verificar token
if (!empty($token)) {
    $conn = conectarDB();
    
    try {
        $stmt = $conn->prepare("
            SELECT id, email, reset_token_expira 
            FROM usuarios 
            WHERE reset_token = ? 
            AND reset_token_expira > NOW()
        ");
        $stmt->execute([$token]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($usuario) {
            $token_valido = true;
            $usuario_id = $usuario['id'];
            $usuario_email = $usuario['email'];
        } else {
            $error = "El enlace de recuperación es inválido o ha expirado.";
        }
        
    } catch(PDOException $e) {
        $error = "Error en el sistema: " . $e->getMessage();
    }
} else {
    $error = "Enlace de recuperación no proporcionado.";
}

// Procesar el formulario de nueva contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token_valido) {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($password) || empty($confirm_password)) {
        $error = "Todos los campos son obligatorios";
    } elseif (strlen($password) < 6) {
        $error = "La contraseña debe tener al menos 6 caracteres";
    } elseif ($password !== $confirm_password) {
        $error = "Las contraseñas no coinciden";
    } else {
        try {
            // Hash de la nueva contraseña
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            // Actualizar contraseña y limpiar token
            $stmt = $conn->prepare("
                UPDATE usuarios 
                SET password = ?, 
                    reset_token = NULL, 
                    reset_token_expira = NULL,
                    ultimo_cambio_password = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$password_hash, $usuario_id]);
            
            $success = "¡Contraseña actualizada exitosamente!";
            
            // Redireccionar después de 3 segundos
            header("refresh:3;url=" . BASE_URL . "modules/auth/login.php");
            
        } catch(PDOException $e) {
            $error = "Error al actualizar la contraseña: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Contraseña - Sistema de Nómina</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Estilos específicos -->
    <link rel="stylesheet" href="recuperar.css">
</head>
<body class="flex items-center justify-center p-4 min-h-screen" style="background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);">
    <div class="w-full max-w-md">
        <!-- Card -->
        <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
            <!-- Header -->
            <div class="bg-gradient-to-r from-green-500 to-emerald-600 text-white p-8 text-center">
                <div class="flex justify-center mb-4">
                    <div class="bg-white/20 p-4 rounded-full">
                        <i class="fas fa-lock text-3xl"></i>
                    </div>
                </div>
                <h1 class="text-2xl font-bold">Nueva Contraseña</h1>
                <p class="text-emerald-100 mt-2">Establece tu nueva contraseña</p>
            </div>
            
            <!-- Form -->
            <div class="p-8">
                <?php if ($error): ?>
                <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded animate-fade-in">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-circle text-red-500"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-red-700"><?php echo $error; ?></p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                <div class="mb-6 bg-green-50 border-l-4 border-green-500 p-4 rounded animate-fade-in">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-check-circle text-green-500"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-green-700"><?php echo $success; ?></p>
                            <p class="text-xs text-green-600 mt-1">
                                <i class="fas fa-spinner fa-spin mr-1"></i>Redirigiendo al login...
                            </p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($token_valido && empty($success)): ?>
                <?php if (!empty($usuario_email)): ?>
                <div class="mb-6 bg-blue-50 border-l-4 border-blue-500 p-4 rounded">
                    <p class="text-sm text-blue-700">
                        <i class="fas fa-info-circle mr-2"></i>
                        Estableciendo nueva contraseña para: <strong><?php echo htmlspecialchars($usuario_email); ?></strong>
                    </p>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="" class="space-y-6" id="resetForm">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-lock mr-2 text-gray-500"></i>Nueva Contraseña
                        </label>
                        <div class="relative">
                            <input type="password" 
                                   id="password" 
                                   name="password" 
                                   required
                                   minlength="6"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition"
                                   placeholder="Mínimo 6 caracteres">
                            <button type="button" 
                                    class="absolute inset-y-0 right-0 pr-3 flex items-center password-toggle"
                                    data-target="password">
                                <i class="fas fa-eye text-gray-500 hover:text-gray-700"></i>
                            </button>
                        </div>
                        <div class="password-strength mt-2">
                            <div class="password-strength-bar" id="passwordStrength"></div>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">La contraseña debe tener al menos 6 caracteres</p>
                    </div>
                    
                    <div>
                        <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-lock mr-2 text-gray-500"></i>Confirmar Contraseña
                        </label>
                        <div class="relative">
                            <input type="password" 
                                   id="confirm_password" 
                                   name="confirm_password" 
                                   required
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition"
                                   placeholder="Repite tu contraseña">
                            <button type="button" 
                                    class="absolute inset-y-0 right-0 pr-3 flex items-center password-toggle"
                                    data-target="confirm_password">
                                <i class="fas fa-eye text-gray-500 hover:text-gray-700"></i>
                            </button>
                        </div>
                        <div id="passwordMatch" class="mt-1 text-xs"></div>
                    </div>
                    
                    <button type="submit" 
                            class="w-full bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white py-3 px-4 rounded-lg font-medium transition duration-300 shadow-md hover:shadow-lg transform hover:-translate-y-0.5">
                        <i class="fas fa-save mr-2"></i>Guardar Nueva Contraseña
                    </button>
                </form>
                <?php elseif (empty($success)): ?>
                <div class="text-center py-8">
                    <i class="fas fa-exclamation-triangle text-4xl text-amber-500 mb-4"></i>
                    <p class="text-gray-700 mb-4">No se puede restablecer la contraseña con este enlace.</p>
                    <a href="recuperar_password.php" 
                       class="inline-flex items-center text-blue-600 hover:text-blue-800 font-medium">
                        <i class="fas fa-redo mr-2"></i>
                        Solicitar nuevo enlace
                    </a>
                </div>
                <?php endif; ?>
                
                <!-- Enlaces -->
                <div class="mt-6 pt-6 border-t border-gray-200">
                    <a href="login.php" 
                       class="flex items-center text-sm text-blue-600 hover:text-blue-800 transition">
                        <i class="fas fa-sign-in-alt mr-2"></i>
                        Volver al inicio de sesión
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="mt-6 text-center">
            <p class="text-sm text-white/80">
                Sistema de Nómina para Contadores
            </p>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script src="js/reset_password.js"></script>
</body>
</html>