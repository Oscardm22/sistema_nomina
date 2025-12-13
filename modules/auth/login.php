<?php
require_once '../../config/database.php';

// ===== PREVENIR CACHÉ Y ACCESO =====
prevenirCaché();

// Si ya está logueado, redirigir inmediatamente al dashboard
redireccionarSiAutenticado();

// ===== MANEJO DEL LOGIN =====
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = limpiar($_POST['email']);
    $password = $_POST['password'];
    
    $conn = conectarDB();
    
    // Buscar usuario
    $stmt = $conn->prepare("SELECT * FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($usuario && password_verify($password, $usuario['password'])) {
        // Crear sesión
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['usuario_nombre'] = $usuario['nombre'];
        $_SESSION['usuario_tipo'] = 'contador';
        $_SESSION['usuario_email'] = $usuario['email'];
        $_SESSION['login_time'] = time();
        $_SESSION['ultima_actividad'] = time();
        
        // Regenerar ID de sesión para mayor seguridad
        session_regenerate_id(true);
        
        // Prevenir caché antes de redirigir
        prevenirCaché();
        
        // Redireccionar al dashboard
        header("Location: " . BASE_URL . "dashboard.php");
        exit();
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
    
    <!-- Meta tags para prevenir caché en el navegador -->
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    
    <!-- Tailwind CSS -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/tailwind-output.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body {
            background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
            min-height: 100vh;
        }
        
        /* Mejorar la apariencia del autocompletado */
        input:-webkit-autofill {
            -webkit-box-shadow: 0 0 0px 1000px white inset !important;
            -webkit-text-fill-color: #374151 !important;
            transition: background-color 5000s ease-in-out 0s;
        }
        
        /* Estilo para campos con autocompletado */
        .autocomplete-item {
            padding: 8px 12px;
            cursor: pointer;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .autocomplete-item:hover {
            background-color: #f3f4f6;
        }
        
        .autocomplete-container {
            position: absolute;
            background: white;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            max-height: 200px;
            overflow-y: auto;
            width: 100%;
            display: none;
        }
    </style>
</head>
<body class="flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
            <div class="bg-gradient-to-r from-blue-600 to-indigo-700 text-white p-8 text-center">
                <div class="mb-4">
                    <i class="fas fa-calculator text-4xl"></i>
                </div>
                <h1 class="text-2xl font-bold">NominaContadores</h1>
                <p class="text-blue-200 mt-2">Sistema de nómina para contadores</p>
            </div>
            
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
                
                <form method="POST" action="" class="space-y-6" id="loginForm">
                    <div class="relative">
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-envelope mr-2 text-gray-500"></i>Correo Electrónico
                        </label>
                        <input type="email" 
                               id="email" 
                               name="email" 
                               required
                               autocomplete="email"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                               placeholder="admin@nominas.com"
                               data-autocomplete="email">
                        <div id="email-autocomplete" class="autocomplete-container mt-1"></div>
                    </div>
                    
                    <div class="relative">
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-lock mr-2 text-gray-500"></i>Contraseña
                        </label>
                        <input type="password" 
                               id="password" 
                               name="password" 
                               required
                               autocomplete="current-password"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                               placeholder="••••••••">
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center mt-8">
                            <button type="button" 
                                    id="togglePassword" 
                                    class="text-gray-500 hover:text-gray-700 focus:outline-none">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <button type="submit" 
                            class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white py-3 px-4 rounded-lg font-medium transition duration-300 shadow-md hover:shadow-lg">
                        <i class="fas fa-sign-in-alt mr-2"></i>Iniciar Sesión
                    </button>
                </form>
            
                <!-- Divider -->
                <div class="mt-6 pt-6 border-t border-gray-200">
                    <p class="text-center text-sm text-gray-600">
                        ¿Primera vez usando el sistema?
                        <a href="registrar.php" class="font-medium text-blue-600 hover:text-blue-800 ml-1">
                            Regístrate aquí
                        </a>
                    </p>
                </div>
            </div>
        </div>
        
        <div class="mt-6 text-center text-white/80 text-sm">
            <p>Sistema de Nómina v1.0</p>
        </div>
    </div>
    
    <script>
        
        document.addEventListener('DOMContentLoaded', function() {
            // 1. Mostrar/ocultar contraseña
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');
            const eyeIcon = togglePassword.querySelector('i');
            
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                eyeIcon.className = type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
            });
            
            // 2. Prevenir múltiples envíos
            const loginForm = document.getElementById('loginForm');
            loginForm.addEventListener('submit', function(e) {
                const submitButton = this.querySelector('button[type="submit"]');
                submitButton.disabled = true;
                submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Iniciando sesión...';
            });
            
            // 3. Cargar usuarios guardados del localStorage (opcional)
            loadSavedCredentials();
            
            // 4. Guardar credenciales si el usuario marca "Recordar"
            document.getElementById('remember').addEventListener('change', function() {
                if (this.checked) {
                    saveCredentials();
                } else {
                    clearSavedCredentials();
                }
            });
            
            // 5. Auto-completar si hay credenciales guardadas
            function loadSavedCredentials() {
                const savedEmail = localStorage.getItem('nomina_email');
                const savedRemember = localStorage.getItem('nomina_remember');
                
                if (savedEmail && savedRemember === 'true') {
                    document.getElementById('email').value = savedEmail;
                    document.getElementById('remember').checked = true;
                    // No cargamos la contraseña por seguridad
                    // El navegador la autocompletará si está guardada
                }
            }
            
            function saveCredentials() {
                const email = document.getElementById('email').value;
                if (email) {
                    localStorage.setItem('nomina_email', email);
                    localStorage.setItem('nomina_remember', 'true');
                }
            }
            
            function clearSavedCredentials() {
                localStorage.removeItem('nomina_email');
                localStorage.removeItem('nomina_remember');
            }
            
            // 6. Guardar email cuando cambia
            document.getElementById('email').addEventListener('blur', function() {
                if (document.getElementById('remember').checked) {
                    saveCredentials();
                }
            });
            
            // 7. Prevenir caché pero permitir autocompletado
            if (window.history.replaceState) {
                window.history.replaceState(null, null, window.location.href);
            }
            
            // 8. Detectar autocompletado del navegador
            setTimeout(function() {
                const emailField = document.getElementById('email');
                const passwordField = document.getElementById('password');
                
                // Verificar si los campos fueron autocompletados
                if (emailField.value && !emailField.matches(':autofill')) {
                    // El navegador ya autocompletó
                    console.log('Campos autocompletados por el navegador');
                }
            }, 100);
        });
        
        // 9. Manejar navegación atrás (mantener seguridad)
        window.onpopstate = function(event) {
            window.history.pushState(null, null, window.location.href);
        };
        
        window.history.pushState(null, null, window.location.href);
        
        // 10. Función para sugerencias de email (opcional)
        function showEmailSuggestions(email) {
            const commonDomains = ['@gmail.com', '@hotmail.com', '@outlook.com', '@yahoo.com', '@empresa.com'];
            const input = email.toLowerCase();
            const suggestions = commonDomains.filter(domain => domain.startsWith(input));
            
            const container = document.getElementById('email-autocomplete');
            container.innerHTML = '';
            
            if (suggestions.length > 0 && input.includes('@')) {
                const atIndex = input.indexOf('@');
                const userPart = input.substring(0, atIndex + 1);
                
                suggestions.forEach(domain => {
                    const fullEmail = userPart + domain.substring(1);
                    const div = document.createElement('div');
                    div.className = 'autocomplete-item';
                    div.textContent = fullEmail;
                    div.onclick = function() {
                        document.getElementById('email').value = fullEmail;
                        container.style.display = 'none';
                    };
                    container.appendChild(div);
                });
                
                container.style.display = 'block';
            } else {
                container.style.display = 'none';
            }
        }
        
        // Escuchar cambios en el campo email
        document.getElementById('email').addEventListener('input', function(e) {
            showEmailSuggestions(e.target.value);
        });
        
        // Ocultar sugerencias al hacer clic fuera
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.relative')) {
                document.getElementById('email-autocomplete').style.display = 'none';
            }
        });
    </script>
</body>
</html>