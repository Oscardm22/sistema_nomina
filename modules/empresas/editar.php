<?php
require_once '../../config/database.php';
requerirLogin();

$conn = conectarDB();
$usuario_id = $_SESSION['usuario_id'];

// Verificar que se haya proporcionado un ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit();
}

$empresa_id = intval($_GET['id']);

// Verificar que la empresa exista y pertenezca al usuario
$stmt = $conn->prepare("SELECT * FROM empresas WHERE id = ? AND usuario_id = ?");
$stmt->execute([$empresa_id, $usuario_id]);
$empresa = $stmt->fetch();

if (!$empresa) {
    header('Location: index.php');
    exit();
}

$errores = [];
$mensaje = '';
$datos = [
    'nombre' => $empresa['nombre'],
    'rif' => $empresa['rif'],
    'direccion' => $empresa['direccion'] ?? '',
    'telefono' => $empresa['telefono'] ?? '',
    'email' => $empresa['email'] ?? '',
    'activa' => $empresa['activa']
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recoger y limpiar datos
    $datos['nombre'] = limpiar($_POST['nombre'] ?? '');
    $datos['rif'] = strtoupper(limpiar($_POST['rif'] ?? ''));
    $datos['direccion'] = limpiar($_POST['direccion'] ?? '');
    $datos['telefono'] = limpiar($_POST['telefono'] ?? '');
    $datos['email'] = limpiar($_POST['email'] ?? '');
    $datos['activa'] = isset($_POST['activa']) ? 1 : 0;
    
    // Validaciones
    if (empty($datos['nombre'])) {
        $errores['nombre'] = 'El nombre de la empresa es obligatorio';
    }
    
    if (empty($datos['rif'])) {
        $errores['rif'] = 'El RIF es obligatorio';
    } elseif (!preg_match('/^J-\d{9}$/', $datos['rif'])) {
        $errores['rif'] = 'Formato inválido. Use: J-123456789 (9 números después del guión)';
    }
    
    if (!empty($datos['email']) && !filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
        $errores['email'] = 'El email no es válido';
    }
    
    if (!empty($datos['telefono']) && !preg_match('/^[0-9\s\-\+\(\)]{10,15}$/', $datos['telefono'])) {
        $errores['telefono'] = 'El teléfono no es válido';
    }
    
    // Verificar que el RIF no esté duplicado (excepto para esta empresa)
    if (empty($errores['rif'])) {
        $stmt = $conn->prepare("SELECT id FROM empresas WHERE rif = ? AND usuario_id = ? AND id != ?");
        $stmt->execute([$datos['rif'], $usuario_id, $empresa_id]);
        if ($stmt->rowCount() > 0) {
            $errores['rif'] = 'Este RIF ya está registrado en otra empresa';
        }
    }
    
    // Si no hay errores, actualizar en la base de datos
    if (empty($errores)) {
        try {
            $stmt = $conn->prepare("
                UPDATE empresas 
                SET nombre = ?, rif = ?, direccion = ?, telefono = ?, email = ?, activa = ?
                WHERE id = ? AND usuario_id = ?
            ");
            
            if ($stmt->execute([
                $datos['nombre'],
                $datos['rif'],
                $datos['direccion'],
                $datos['telefono'],
                $datos['email'],
                $datos['activa'],
                $empresa_id,
                $usuario_id
            ])) {
                $mensaje = 'success|Empresa actualizada exitosamente.';
                // Actualizar datos de la empresa
                $empresa = array_merge($empresa, $datos);
            } else {
                $errores[] = 'Error al actualizar la empresa';
            }
            
        } catch (PDOException $e) {
            $errores[] = 'Error: ' . $e->getMessage();
        }
    }
}

$pageTitle = "Editar Empresa";
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Sistema de Nómina</title>
    
    <!-- Tailwind CSS via CDN -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Estilos personalizados -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>modules/empresas/styles/formulario.css">
</head>
<body class="bg-gray-50">
    <!-- Navbar temporal -->
    <nav class="bg-gradient-to-r from-blue-600 to-indigo-700 text-white shadow-lg">
        <div class="container mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-3">
                    <a href="../../dashboard.php" class="flex items-center space-x-3">
                        <i class="fas fa-calculator text-2xl"></i>
                        <span class="text-xl font-bold">NominaContadores</span>
                    </a>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="../../dashboard.php" class="hover:text-blue-200">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                    <a href="<?php echo BASE_URL; ?>modules/auth/logout.php" class="hover:text-blue-200">
                        <i class="fas fa-sign-out-alt"></i> Salir
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Layout -->
    <div class="flex">
        <!-- Sidebar temporal -->
        <div class="w-64 bg-white shadow-lg min-h-screen">
            <div class="p-6">
                <div class="mb-8">
                    <h3 class="font-bold text-gray-700 mb-2">Menú Principal</h3>
                    <div class="text-sm text-gray-500"><?php echo $_SESSION['usuario_email'] ?? 'Usuario'; ?></div>
                </div>
                <ul class="space-y-2">
                    <li><a href="../../dashboard.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-gray-100 transition"><i class="fas fa-tachometer-alt w-6"></i><span>Dashboard</span></a></li>
                    <li><a href="index.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg bg-blue-50 text-blue-700"><i class="fas fa-building w-6"></i><span>Empresas</span></a></li>
                    <li><a href="../empleados/" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-gray-100 transition"><i class="fas fa-users w-6"></i><span>Empleados</span></a></li>
                    <li><a href="../nominas/" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-gray-100 transition"><i class="fas fa-file-invoice-dollar w-6"></i><span>Nóminas</span></a></li>
                </ul>
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex-1">
            <div class="container mx-auto px-4 py-8">
                <!-- Header -->
                <div class="mb-8">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900">Editar Empresa</h1>
                            <p class="text-gray-600 mt-2">Modifica la información de <?php echo htmlspecialchars($empresa['nombre']); ?></p>
                            <div class="mt-2">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium <?php echo $empresa['activa'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                    <i class="fas fa-circle text-xs mr-1"></i>
                                    <?php echo $empresa['activa'] ? 'Activa' : 'Inactiva'; ?>
                                </span>
                                <span class="ml-2 text-sm text-gray-500">
                                    <i class="fas fa-calendar-alt mr-1"></i>
                                    Registrada: <?php echo date('d/m/Y', strtotime($empresa['fecha_alta'])); ?>
                                </span>
                            </div>
                        </div>
                        <div>
                            <a href="index.php" 
                               class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                                <i class="fas fa-arrow-left mr-2"></i>
                                Volver al listado
                            </a>
                        </div>
                    </div>
                    
                    <!-- Mostrar mensajes -->
                    <?php if(!empty($mensaje)): 
                        $parts = explode('|', $mensaje);
                        $tipo = $parts[0];
                        $texto = $parts[1] ?? $mensaje;
                    ?>
                    <div class="mb-6 p-4 rounded-lg <?php echo $tipo === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                        <div class="flex items-center">
                            <i class="fas <?php echo $tipo === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> mr-2"></i>
                            <span><?php echo $texto; ?></span>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if(!empty($errores) && is_array($errores)): ?>
                    <div class="mb-6 p-4 rounded-lg bg-red-100 text-red-800">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-circle mr-2"></i>
                            <span>Por favor corrige los siguientes errores:</span>
                        </div>
                        <ul class="mt-2 ml-6 list-disc">
                            <?php foreach($errores as $campo => $error): 
                                if(is_string($campo)) continue;
                            ?>
                            <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Formulario -->
                <div class="bg-white rounded-lg shadow p-6">
                    <form method="POST" action="" class="space-y-6">
                        <!-- Información Básica -->
                        <div>
                            <h2 class="text-lg font-medium text-gray-900 mb-4 border-b pb-2">
                                <i class="fas fa-info-circle mr-2 text-blue-600"></i>
                                Información Básica
                            </h2>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Nombre -->
                                <div>
                                    <label for="nombre" class="block text-sm font-medium text-gray-700 mb-1 form-required">
                                        Nombre de la Empresa
                                    </label>
                                    <input type="text" 
                                           id="nombre" 
                                           name="nombre" 
                                           value="<?php echo htmlspecialchars($datos['nombre']); ?>"
                                           class="w-full px-4 py-2 border <?php echo isset($errores['nombre']) ? 'border-red-500' : 'border-gray-300'; ?> rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                           required
                                           autofocus>
                                    <?php if(isset($errores['nombre'])): ?>
                                    <div class="error-message"><?php echo $errores['nombre']; ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- RIF -->
                                <div>
                                    <label for="rif" class="block text-sm font-medium text-gray-700 mb-1 form-required">
                                        RIF (Registro de Información Fiscal)
                                    </label>
                                    <input type="text" 
                                           id="rif" 
                                           name="rif" 
                                           value="<?php echo htmlspecialchars($datos['rif']); ?>"
                                           class="w-full px-4 py-2 border <?php echo isset($errores['rif']) ? 'border-red-500' : 'border-gray-300'; ?> rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                           placeholder="J-123456789"
                                           required
                                           pattern="J-\d{9}"
                                           title="Formato: J-123456789 (9 números después del guión)"
                                           oninput="formatRIF(this)">
                                    <?php if(isset($errores['rif'])): ?>
                                    <div class="error-message"><?php echo $errores['rif']; ?></div>
                                    <?php endif; ?>
                                    <div class="rif-example">
                                        <strong>Formato:</strong> J-123456789
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Información de Contacto -->
                        <div>
                            <h2 class="text-lg font-medium text-gray-900 mb-4 border-b pb-2">
                                <i class="fas fa-address-card mr-2 text-blue-600"></i>
                                Información de Contacto
                            </h2>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Dirección -->
                                <div class="md:col-span-2">
                                    <label for="direccion" class="block text-sm font-medium text-gray-700 mb-1">
                                        Dirección
                                    </label>
                                    <textarea 
                                        id="direccion" 
                                        name="direccion" 
                                        rows="3"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                        placeholder="Dirección completa de la empresa"><?php echo htmlspecialchars($datos['direccion']); ?></textarea>
                                </div>
                                
                                <!-- Teléfono -->
                                <div>
                                    <label for="telefono" class="block text-sm font-medium text-gray-700 mb-1">
                                        Teléfono
                                    </label>
                                    <input type="text" 
                                           id="telefono" 
                                           name="telefono" 
                                           value="<?php echo htmlspecialchars($datos['telefono']); ?>"
                                           class="w-full px-4 py-2 border <?php echo isset($errores['telefono']) ? 'border-red-500' : 'border-gray-300'; ?> rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                           placeholder="Ej: 0412-1234567">
                                    <?php if(isset($errores['telefono'])): ?>
                                    <div class="error-message"><?php echo $errores['telefono']; ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Email -->
                                <div>
                                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                                        Correo Electrónico
                                    </label>
                                    <input type="email" 
                                           id="email" 
                                           name="email" 
                                           value="<?php echo htmlspecialchars($datos['email']); ?>"
                                           class="w-full px-4 py-2 border <?php echo isset($errores['email']) ? 'border-red-500' : 'border-gray-300'; ?> rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                           placeholder="contacto@empresa.com">
                                    <?php if(isset($errores['email'])): ?>
                                    <div class="error-message"><?php echo $errores['email']; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Estatus -->
                        <div>
                            <h2 class="text-lg font-medium text-gray-900 mb-4 border-b pb-2">
                                <i class="fas fa-cog mr-2 text-blue-600"></i>
                                Configuración
                            </h2>
                            
                            <div class="flex items-center">
                                <input type="checkbox" 
                                       id="activa" 
                                       name="activa" 
                                       value="1"
                                       class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                                       <?php echo $datos['activa'] ? 'checked' : ''; ?>>
                                <label for="activa" class="ml-2 block text-sm text-gray-900">
                                    Empresa activa (puede procesar nóminas)
                                </label>
                            </div>
                            <p class="mt-2 text-sm text-gray-600">
                                Si desactivas esta empresa, no podrás agregar nuevos empleados ni procesar nóminas para ella.
                                Los datos existentes se mantendrán.
                            </p>
                        </div>
                        
                        <!-- Botones -->
                        <div class="flex justify-end space-x-4 pt-6 border-t">
                            <a href="index.php" 
                               class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                                Cancelar
                            </a>
                            <button type="submit" 
                                    class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                                <i class="fas fa-save mr-2"></i>
                                Guardar Cambios
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="<?php echo BASE_URL; ?>modules/empresas/js/empresa-form.js"></script>
</body>
</html>