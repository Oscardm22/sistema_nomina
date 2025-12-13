<?php
require_once '../../config/database.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = limpiar($_POST['email']);
    $password = $_POST['password'];
    
    $conn = conectarDB();
    
    // Buscar usuario
    $stmt = $conn->prepare("SELECT * FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($usuario && password_verify($password, $usuario['password'])) {
        if ($usuario['activo']) {
            // Crear sesión
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['usuario_nombre'] = $usuario['nombre'];
            $_SESSION['usuario_tipo'] = $usuario['tipo'];
            $_SESSION['usuario_email'] = $usuario['email'];
            
            // Redireccionar al dashboard
            header("Location: " . BASE_URL . "dashboard.php");
            exit();
        } else {
            $error = "Tu cuenta está desactivada. Contacta al administrador.";
        }
    } else {
        $error = "Email o contraseña incorrectos.";
    }
}

$pageTitle = "Iniciar Sesión - Sistema de Nómina";
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    
    <!-- Tailwind CSS con configuración de colores -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            200: '#bfdbfe',
                            300: '#93c5fd',
                            400: '#60a5fa',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a',
                        }
                    }
                }
            }
        }
    </script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body {
            background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
            min-height: 100vh;
        }
        
        .login-card {
            animation: fadeIn 0.5s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="flex items-center justify-center p-4">
    <div class="w-full max-w-md login-card">
        <!-- Login Card -->
        <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
            <!-- Header - Fondo azul con buen contraste -->
            <div class="bg-gradient-to-r from-blue-600 to-indigo-800 text-white p-8 text-center">
                <div class="flex justify-center mb-4">
                    <div class="bg-white/20 p-4 rounded-full backdrop-blur-sm">
                        <i class="fas fa-calculator text-3xl"></i>
                    </div>
                </div>
                <h1 class="text-2xl font-bold text-white">NominaContadores</h1>
                <p class="text-blue-200 mt-2 opacity-90">Sistema de nómina para contadores</p>
            </div>
            
            <!-- Form -->
            <div class="p-8">
                <?php if ($error): ?>
                <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded-lg">
                    <div class="flex items-start">
                        <div class="flex-shrink-0 pt-0.5">
                            <i class="fas fa-exclamation-circle text-red-500"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-red-700"><?php echo $error; ?></p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                <div class="mb-6 bg-green-50 border-l-4 border-green-500 p-4 rounded-lg">
                    <div class="flex items-start">
                        <div class="flex-shrink-0 pt-0.5">
                            <i class="fas fa-check-circle text-green-500"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-green-700"><?php echo $success; ?></p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="" class="space-y-6">
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-envelope mr-2 text-gray-500"></i>Correo Electrónico
                        </label>
                        <input type="email" 
                               id="email" 
                               name="email" 
                               required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200"
                               placeholder="usuario@ejemplo.com"
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>
                    
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-lock mr-2 text-gray-500"></i>Contraseña
                        </label>
                        <input type="password" 
                               id="password" 
                               name="password" 
                               required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200"
                               placeholder="••••••••">
                    </div>
                    
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <input id="remember" name="remember" type="checkbox" 
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="remember" class="ml-2 block text-sm text-gray-700">
                                Recordarme
                            </label>
                        </div>
                        
                        <a href="#" class="text-sm text-blue-600 hover:text-blue-800 transition duration-200">
                            ¿Olvidaste tu contraseña?
                        </a>
                    </div>
                    
                    <button type="submit" 
                            class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white py-3 px-4 rounded-lg font-medium focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-300 shadow-md hover:shadow-lg transform hover:-translate-y-0.5">
                        <i class="fas fa-sign-in-alt mr-2"></i>Iniciar Sesión
                    </button>
                </form>
                
                <!-- Divider -->
                <div class="mt-8 pt-6 border-t border-gray-200">
                    <p class="text-center text-sm text-gray-600">
                        ¿Primera vez usando el sistema?
                        <a href="registrar.php" class="font-medium text-blue-600 hover:text-blue-800 ml-1 transition duration-200">
                            Solicitar acceso
                        </a>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Footer info -->
        <div class="mt-6 text-center">
            <p class="text-sm text-white/90">
                © <?php echo date('Y'); ?> Sistema de Nómina v1.0
            </p>
            <p class="text-xs text-white/70 mt-1">
                Para contadores y empresas
            </p>
        </div>
    </div>
    
    <script>
        // Efecto de focus en inputs
        document.querySelectorAll('input').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.classList.add('opacity-100');
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.classList.remove('opacity-100');
            });
        });
        
        // Mostrar/ocultar contraseña (opcional)
        const passwordInput = document.getElementById('password');
        const togglePassword = document.createElement('button');
        togglePassword.type = 'button';
        togglePassword.className = 'absolute right-3 top-10 text-gray-500 hover:text-gray-700';
        togglePassword.innerHTML = '<i class="fas fa-eye"></i>';
        
        // Si quieres agregar el botón de mostrar contraseña:
        // passwordInput.parentElement.classList.add('relative');
        // passwordInput.parentElement.appendChild(togglePassword);
    </script>
</body>
</html>