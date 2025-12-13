<?php
require_once '../../config/database.php';

// Si ya está logueado, redirigir al dashboard
if (estaLogueado()) {
    header("Location: " . BASE_URL . "dashboard.php");
    exit();
}

$error = '';
$success = '';
$nombre = $email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = limpiar($_POST['nombre']);
    $email = limpiar($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validaciones básicas
    if (empty($nombre) || empty($email) || empty($password)) {
        $error = "Todos los campos son obligatorios";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Email inválido";
    } elseif (strlen($password) < 6) {
        $error = "La contraseña debe tener al menos 6 caracteres";
    } elseif ($password !== $confirm_password) {
        $error = "Las contraseñas no coinciden";
    } else {
        $conn = conectarDB();
        
        try {
            // Verificar si el email ya existe
            $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->rowCount() > 0) {
                $error = "Este email ya está registrado";
            } else {
                // Hash de la contraseña
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                
                // Insertar nuevo usuario (tipo "contador" por defecto)
                $stmt = $conn->prepare("
                    INSERT INTO usuarios (nombre, email, password) 
                    VALUES (?, ?, ?)
                ");
                
                $stmt->execute([$nombre, $email, $password_hash]);
                
                // Obtener el ID del usuario recién creado
                $usuario_id = $conn->lastInsertId();
                
                // Iniciar sesión automáticamente después del registro
                $_SESSION['usuario_id'] = $usuario_id;
                $_SESSION['usuario_nombre'] = $nombre;
                $_SESSION['usuario_email'] = $email;
                $_SESSION['usuario_tipo'] = 'contador'; // Tipo por defecto
                
                $success = "¡Registro exitoso! Redirigiendo al dashboard...";
                
                // Redireccionar después de 2 segundos
                header("refresh:2;url=" . BASE_URL . "dashboard.php");
            }
            
        } catch(PDOException $e) {
            $error = "Error en el sistema: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - Sistema de Nómina</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body {
            background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
            min-height: 100vh;
        }
        
        .register-card {
            animation: slideUp 0.5s ease-out;
        }
        
        @keyframes slideUp {
            from { 
                opacity: 0; 
                transform: translateY(30px); 
            }
            to { 
                opacity: 1; 
                transform: translateY(0); 
            }
        }
        
        .btn-register {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            transition: all 0.3s ease;
        }
        
        .btn-register:hover {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(16, 185, 129, 0.3);
        }
    </style>
</head>
<body class="flex items-center justify-center p-4 py-8">
    <div class="w-full max-w-md register-card">
        <!-- Register Card -->
        <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
            <!-- Header -->
            <div class="bg-gradient-to-r from-green-600 to-emerald-800 text-white p-8 text-center">
                <div class="flex justify-center mb-4">
                    <div class="bg-white/20 p-4 rounded-full">
                        <i class="fas fa-user-plus text-3xl"></i>
                    </div>
                </div>
                <h1 class="text-2xl font-bold text-white">Registro de Contador</h1>
                <p class="text-emerald-200 mt-2">Crea tu cuenta para comenzar</p>
            </div>
            
            <!-- Form -->
            <div class="p-8">
                <?php if ($error): ?>
                <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded">
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
                <div class="mb-6 bg-green-50 border-l-4 border-green-500 p-4 rounded">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-check-circle text-green-500"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-green-700"><?php echo $success; ?></p>
                            <p class="text-xs text-green-600 mt-1">
                                <i class="fas fa-spinner fa-spin mr-1"></i>Redirigiendo...
                            </p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="" class="space-y-6" id="registerForm">
                    <!-- Nombre -->
                    <div>
                        <label for="nombre" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-user mr-2 text-gray-500"></i>Nombre Completo *
                        </label>
                        <input type="text" 
                               id="nombre" 
                               name="nombre" 
                               required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition"
                               placeholder="Tu nombre completo"
                               value="<?php echo htmlspecialchars($nombre); ?>">
                    </div>
                    
                    <!-- Email -->
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-envelope mr-2 text-gray-500"></i>Correo Electrónico *
                        </label>
                        <input type="email" 
                               id="email" 
                               name="email" 
                               required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition"
                               placeholder="tu@email.com"
                               value="<?php echo htmlspecialchars($email); ?>">
                    </div>
                    
                    <!-- Contraseña -->
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-lock mr-2 text-gray-500"></i>Contraseña *
                        </label>
                        <input type="password" 
                               id="password" 
                               name="password" 
                               required
                               minlength="6"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition"
                               placeholder="Mínimo 6 caracteres">
                        <p class="text-xs text-gray-500 mt-2">La contraseña debe tener al menos 6 caracteres</p>
                    </div>
                    
                    <!-- Confirmar Contraseña -->
                    <div>
                        <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-lock mr-2 text-gray-500"></i>Confirmar Contraseña *
                        </label>
                        <input type="password" 
                               id="confirm_password" 
                               name="confirm_password" 
                               required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition"
                               placeholder="Repite tu contraseña">
                        <div id="passwordMatch" class="mt-1 text-xs"></div>
                    </div>
                    
                    <!-- Botón de registro -->
                    <button type="submit" 
                            class="btn-register w-full text-white py-3 px-4 rounded-lg font-medium focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 shadow-md">
                        <i class="fas fa-user-plus mr-2"></i>Crear Cuenta
                    </button>
                    
                    <!-- Enlace a login -->
                    <div class="text-center pt-4 border-t border-gray-200">
                        <p class="text-sm text-gray-600">
                            ¿Ya tienes cuenta?
                            <a href="login.php" class="font-medium text-green-600 hover:text-green-800 ml-1">
                                Inicia sesión aquí
                            </a>
                        </p>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="mt-6 text-center">
            <p class="text-sm text-white/90">
                Sistema de Nómina para Contadores
            </p>
            <p class="text-xs text-white/70 mt-1">
                © <?php echo date('Y'); ?> - Versión 1.0
            </p>
        </div>
    </div>
    
    <script>
        // Validación de coincidencia de contraseñas en tiempo real
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirm_password');
        const matchText = document.getElementById('passwordMatch');
        
        function checkPasswordMatch() {
            if (password.value && confirmPassword.value) {
                if (password.value === confirmPassword.value) {
                    matchText.innerHTML = '<i class="fas fa-check-circle text-green-500 mr-1"></i>Las contraseñas coinciden';
                    matchText.className = 'mt-1 text-xs text-green-600';
                } else {
                    matchText.innerHTML = '<i class="fas fa-times-circle text-red-500 mr-1"></i>Las contraseñas no coinciden';
                    matchText.className = 'mt-1 text-xs text-red-600';
                }
            } else {
                matchText.textContent = '';
            }
        }
        
        password.addEventListener('input', checkPasswordMatch);
        confirmPassword.addEventListener('input', checkPasswordMatch);
        
        // Validación del formulario
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const terminos = document.getElementById('terminos').checked;
            
            if (!terminos) {
                e.preventDefault();
                alert('Debes aceptar los términos y condiciones');
                return false;
            }
            
            if (password.length < 6) {
                e.preventDefault();
                alert('La contraseña debe tener al menos 6 caracteres');
                return false;
            }
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Las contraseñas no coinciden');
                return false;
            }
            
            return true;
        });
    </script>
</body>
</html>