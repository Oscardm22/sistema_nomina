<?php
require_once '../../config/database.php';

// Si ya está logueado, redirigir al dashboard
if (estaLogueado()) {
    header("Location: " . BASE_URL . "dashboard.php");
    exit();
}

$mensaje = '';
$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = limpiar($_POST['email']);
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Por favor ingresa un email válido";
    } else {
        $conn = conectarDB();
        
        try {
            // Verificar si el usuario existe
            $stmt = $conn->prepare("SELECT id, nombre FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($usuario) {
                // Generar token único
                $token = bin2hex(random_bytes(32));
                $expiracion = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Guardar token en la base de datos
                $stmt = $conn->prepare("
                    UPDATE usuarios 
                    SET reset_token = ?, reset_token_expira = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$token, $expiracion, $usuario['id']]);
                
                // Crear enlace de recuperación
                $enlaceRecuperacion = BASE_URL . "modules/auth/reset_password.php?token=" . $token;
                
                // Preparar el correo
                $asunto = "Recuperación de Contraseña - Sistema de Nómina";
                
                // Plantilla HTML del correo
                $mensajeEmail = "
                <!DOCTYPE html>
                <html lang='es'>
                <head>
                    <meta charset='UTF-8'>
                    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                    <title>Recuperación de Contraseña</title>
                    <style>
                        body { 
                            font-family: 'Arial', sans-serif; 
                            line-height: 1.6; 
                            color: #333; 
                            margin: 0; 
                            padding: 0; 
                            background-color: #f4f4f4;
                        }
                        .container { 
                            max-width: 600px; 
                            margin: 0 auto; 
                            padding: 20px; 
                            background-color: #ffffff;
                            border-radius: 10px;
                            box-shadow: 0 0 10px rgba(0,0,0,0.1);
                        }
                        .header { 
                            background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%); 
                            color: white; 
                            padding: 20px; 
                            text-align: center; 
                            border-radius: 10px 10px 0 0;
                        }
                        .content { 
                            padding: 30px; 
                        }
                        .btn { 
                            display: inline-block; 
                            background: linear-gradient(135deg, #60a5fa 0%, #3b82f6 100%); /* Azul más claro */
                            color: #ffffff ;
                            padding: 12px 24px; 
                            text-decoration: none; 
                            border-radius: 5px; 
                            font-weight: bold;
                            margin: 20px 0;
                            border: 2px solid #3b82f6;
                        }
                        .footer { 
                            text-align: center; 
                            padding: 20px; 
                            color: #666; 
                            font-size: 12px; 
                            border-top: 1px solid #eee;
                        }
                        .logo { 
                            font-size: 24px; 
                            font-weight: bold; 
                            margin-bottom: 10px;
                        }
                        .token-box {
                            background-color: #f8f9fa;
                            border: 1px solid #dee2e6;
                            padding: 15px;
                            border-radius: 5px;
                            margin: 20px 0;
                            word-break: break-all;
                            font-family: monospace;
                            font-size: 12px;
                        }
                        .warning {
                            background-color: #fff3cd;
                            border: 1px solid #ffeaa7;
                            color: #856404;
                            padding: 10px;
                            border-radius: 5px;
                            margin: 15px 0;
                        }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <div class='logo'>📊 Sistema de Nómina</div>
                            <h2>Recuperación de Contraseña</h2>
                        </div>
                        
                        <div class='content'>
                            <p>Hola <strong>{$usuario['nombre']}</strong>,</p>
                            
                            <p>Has solicitado recuperar tu contraseña en el <strong>Sistema de Nómina para Contadores</strong>.</p>
                            
                            <p>Para establecer una nueva contraseña, haz clic en el siguiente botón:</p>
                            
                            <div style='text-align: center;'>
                                <a href='{$enlaceRecuperacion}' class='btn'>
                                    🔐 Restablecer Contraseña
                                </a>
                            </div>
                            
                            <p>O copia y pega este enlace en tu navegador:</p>
                            
                            <div class='token-box'>
                                {$enlaceRecuperacion}
                            </div>
                            
                            <div class='warning'>
                                <p><strong>⚠️ Importante:</strong></p>
                                <ul>
                                    <li>Este enlace es válido por <strong>1 hora</strong></li>
                                    <li>Si no solicitaste este cambio, ignora este mensaje</li>
                                    <li>Tu contraseña actual seguirá funcionando hasta que la cambies</li>
                                </ul>
                            </div>
                            
                            <p>Si tienes problemas con el botón, también puedes:</p>
                            <ol>
                                <li>Abrir tu navegador</li>
                                <li>Copiar el enlace de arriba</li>
                                <li>Pegarlo en la barra de direcciones</li>
                                <li>Presionar Enter</li>
                            </ol>
                        </div>
                        
                        <div class='footer'>
                            <p>Este es un mensaje automático, por favor no responder.</p>
                            <p>© " . date('Y') . " Sistema de Nómina para Contadores. Todos los derechos reservados.</p>
                            <p><small>Si recibiste este correo por error, por favor ignóralo.</small></p>
                        </div>
                    </div>
                </body>
                </html>
                ";
                
                // Enviar el correo usando PHPMailer
                $resultado = enviarCorreo($email, $asunto, $mensajeEmail);
                
                if ($resultado['success']) {
                    // Por seguridad, mostramos el mismo mensaje aunque el email no exista
                    $mensaje = "
                    <div class='mb-4'>
                        <p>Si el email <strong>{$email}</strong> existe en nuestro sistema, hemos enviado un enlace de recuperación.</p>
                        <p class='text-sm text-gray-600 mt-2'>Revisa tu bandeja de entrada y también la carpeta de spam.</p>
                    </div>
                    <div class='bg-blue-50 border border-blue-200 rounded-lg p-4 mt-4'>
                        <h4 class='font-medium text-blue-800 mb-2'>📨 ¿No recibiste el correo?</h4>
                        <ul class='text-sm text-blue-700 space-y-1'>
                            <li>✓ Revisa la carpeta de <strong>spam</strong> o <strong>correo no deseado</strong></li>
                            <li>✓ Verifica que escribiste correctamente tu email</li>
                            <li>✓ Espera unos minutos, puede haber demoras</li>
                            <li>✓ Si el problema persiste, contacta al administrador</li>
                        </ul>
                    </div>
                    ";
                } else {
                    // En desarrollo, mostrar el error
                    $mensaje = "
                    <div class='mb-4'>
                        <p>Error al enviar el correo: {$resultado['message']}</p>
                        <p class='text-sm text-gray-600 mt-2'>En desarrollo: Usa este enlace:</p>
                        <div class='mt-2 p-3 bg-gray-100 rounded border'>
                            <a href='{$enlaceRecuperacion}' class='text-blue-600 underline break-all'>
                                {$enlaceRecuperacion}
                            </a>
                        </div>
                    </div>
                    ";
                }
                
            } else {
                // Por seguridad, no revelamos si el email existe o no
                $mensaje = "
                <div class='mb-4'>
                    <p>Si el email <strong>{$email}</strong> existe en nuestro sistema, recibirás un enlace de recuperación.</p>
                    <p class='text-sm text-gray-600 mt-2'>Revisa tu bandeja de entrada y también la carpeta de spam.</p>
                </div>
                ";
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
    <title>Recuperar Contraseña - Sistema de Nómina</title>
    
    <link rel="icon" href="<?php echo BASE_URL; ?>assets/images/favicon/favicon.ico" type="image/x-icon">
    
    <!-- Tailwind CSS -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/tailwind-output.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Estilos específicos -->
    <link rel="stylesheet" href="styles/recuperar.css">
    
    <style>
        .email-preview {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
        }
        .email-preview.show {
            max-height: 500px;
        }
    </style>
</head>
<body class="flex items-center justify-center p-4 min-h-screen" style="background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);">
    <div class="w-full max-w-md">
        <!-- Card -->
        <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
            <!-- Header -->
            <div class="bg-gradient-to-r from-orange-500 to-amber-600 text-white p-8 text-center">
                <div class="flex justify-center mb-4">
                    <div class="bg-white/20 p-4 rounded-full">
                        <i class="fas fa-key text-3xl"></i>
                    </div>
                </div>
                <h1 class="text-2xl font-bold">Recuperar Contraseña</h1>
                <p class="text-amber-100 mt-2">Ingresa tu email para restablecer tu contraseña</p>
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
                
                <?php if ($mensaje): ?>
                <div class="mb-6 bg-green-50 border-l-4 border-green-500 p-4 rounded animate-fade-in">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-check-circle text-green-500"></i>
                        </div>
                        <div class="ml-3">
                            <div class="text-sm text-green-700"><?php echo $mensaje; ?></div>
                        </div>
                    </div>
                    
                    <!-- Botón para ver vista previa (solo en desarrollo) -->
                    <?php if (isset($enlaceRecuperacion) && SMTP_DEBUG > 0): ?>
                    <div class="mt-4">
                        <button type="button" onclick="toggleEmailPreview()" 
                                class="text-xs bg-blue-100 text-blue-700 px-3 py-1 rounded hover:bg-blue-200 transition">
                            <i class="fas fa-eye mr-1"></i>Ver enlace de recuperación
                        </button>
                        
                        <div id="emailPreview" class="email-preview mt-3">
                            <div class="bg-gray-50 p-3 rounded border text-xs">
                                <p class="font-medium mb-2">Enlace generado:</p>
                                <p class="break-all bg-white p-2 rounded border"><?php echo $enlaceRecuperacion; ?></p>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <?php if (empty($mensaje)): ?>
                <form method="POST" action="" class="space-y-6" id="recuperarForm">
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-envelope mr-2 text-gray-500"></i>Correo Electrónico
                        </label>
                        <input type="email" 
                               id="email" 
                               name="email" 
                               required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition"
                               placeholder="tu@email.com"
                               value="<?php echo htmlspecialchars($email); ?>"
                               autocomplete="email">
                        <p class="text-xs text-gray-500 mt-2">
                            Te enviaremos un enlace para restablecer tu contraseña.
                        </p>
                    </div>
                    
                    <button type="submit" 
                            class="w-full bg-gradient-to-r from-orange-500 to-amber-600 hover:from-orange-600 hover:to-amber-700 text-white py-3 px-4 rounded-lg font-medium transition duration-300 shadow-md hover:shadow-lg transform hover:-translate-y-0.5">
                        <i class="fas fa-paper-plane mr-2"></i>Enviar Enlace de Recuperación
                    </button>
                </form>
                <?php endif; ?>
                
                <!-- Enlaces -->
                <div class="mt-6 pt-6 border-t border-gray-200">
                    <div class="space-y-3">
                        <a href="login.php" 
                           class="flex items-center text-sm text-blue-600 hover:text-blue-800 transition">
                            <i class="fas fa-arrow-left mr-2"></i>
                            Volver al inicio de sesión
                        </a>
                        <a href="registrar.php" 
                           class="flex items-center text-sm text-gray-600 hover:text-gray-800 transition">
                            <i class="fas fa-user-plus mr-2"></i>
                            ¿No tienes cuenta? Regístrate
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="mt-6 text-center">
            <p class="text-sm text-white/80">
                Sistema de Nómina para Contadores
            </p>
            <p class="text-xs text-white/80">
                © <?php echo date('Y'); ?> - Versión 1.0
            </p>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script src="js/recuperar.js"></script>
    <script>
        function toggleEmailPreview() {
            const preview = document.getElementById('emailPreview');
            preview.classList.toggle('show');
        }
    </script>
</body>
</html>