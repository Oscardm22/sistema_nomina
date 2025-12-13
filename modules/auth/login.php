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
        // Crear sesión
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['usuario_nombre'] = $usuario['nombre'];
        $_SESSION['usuario_tipo'] = 'contador'; // Valor por defecto
        $_SESSION['usuario_email'] = $usuario['email'];
        
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
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body {
            background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
            min-height: 100vh;
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
                
                <form method="POST" action="" class="space-y-6">
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-envelope mr-2 text-gray-500"></i>Correo Electrónico
                        </label>
                        <input type="email" 
                               id="email" 
                               name="email" 
                               required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                               placeholder="admin@nominas.com"
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
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                               placeholder="admin123">
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
</body>
</html>