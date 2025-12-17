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

$empleado_id = intval($_GET['id']);

// Verificar que el empleado exista y pertenezca al usuario
$stmt = $conn->prepare("
    SELECT e.*, emp.nombre as empresa_nombre 
    FROM empleados e
    INNER JOIN empresas emp ON e.empresa_id = emp.id
    WHERE e.id = ? AND emp.usuario_id = ?
");
$stmt->execute([$empleado_id, $usuario_id]);
$empleado = $stmt->fetch();

if (!$empleado) {
    header('Location: index.php');
    exit();
}

// Obtener empresas del usuario
$stmt_empresas = $conn->prepare("SELECT id, nombre FROM empresas WHERE usuario_id = ? AND activa = 1 ORDER BY nombre");
$stmt_empresas->execute([$usuario_id]);
$empresas = $stmt_empresas->fetchAll();

// Lista de bancos venezolanos
$bancos = [
    'Bancamiga',
    'Banco Activo',
    'Banco Bicentenario',
    'Banco de Venezuela',
    'Banco del Tesoro',
    'Banco Exterior',
    'Banco Mercantil',
    'Banco Nacional de Crédito',
    'Banco Plaza',
    'Banco Sofitasa',
    'Banco Venezolano de Crédito',
    'Banplus',
    'BBVA Provincial',
    'BFC Banco Fondo Común',
    'BOD',
    'DELSUR',
    '100% Banco'
];

$errores = [];
$mensaje = '';
$datos = [
    'empresa_id' => $empleado['empresa_id'],
    'nombre' => $empleado['nombre'],
    'apellidos' => $empleado['apellidos'],
    'fecha_nacimiento' => $empleado['fecha_nacimiento'] ?? '',
    'fecha_ingreso' => $empleado['fecha_ingreso'],
    'puesto' => $empleado['puesto'],
    'departamento' => $empleado['departamento'],
    'salario_diario' => $empleado['salario_diario'],
    'tipo_contrato' => $empleado['tipo_contrato'],
    'regimen_contratacion' => $empleado['regimen_contratacion'],
    'tipo_jornada' => $empleado['tipo_jornada'],
    'periodicidad_pago' => $empleado['periodicidad_pago'],
    'banco' => $empleado['banco'] ?? '',
    'cuenta_bancaria' => $empleado['cuenta_bancaria'] ?? '',
    'activo' => $empleado['activo']
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recoger y limpiar datos
    $datos['empresa_id'] = intval($_POST['empresa_id'] ?? $empleado['empresa_id']);
    $datos['nombre'] = limpiar($_POST['nombre'] ?? $empleado['nombre']);
    $datos['apellidos'] = limpiar($_POST['apellidos'] ?? $empleado['apellidos']);
    $datos['fecha_nacimiento'] = limpiar($_POST['fecha_nacimiento'] ?? ($empleado['fecha_nacimiento'] ?? ''));
    $datos['fecha_ingreso'] = limpiar($_POST['fecha_ingreso'] ?? $empleado['fecha_ingreso']);
    $datos['puesto'] = limpiar($_POST['puesto'] ?? $empleado['puesto']);
    $datos['departamento'] = limpiar($_POST['departamento'] ?? $empleado['departamento']);
    $datos['salario_diario'] = floatval($_POST['salario_diario'] ?? $empleado['salario_diario']);
    $datos['tipo_contrato'] = limpiar($_POST['tipo_contrato'] ?? $empleado['tipo_contrato']);
    $datos['regimen_contratacion'] = limpiar($_POST['regimen_contratacion'] ?? $empleado['regimen_contratacion']);
    $datos['tipo_jornada'] = limpiar($_POST['tipo_jornada'] ?? $empleado['tipo_jornada']);
    $datos['periodicidad_pago'] = limpiar($_POST['periodicidad_pago'] ?? $empleado['periodicidad_pago']);
    $datos['banco'] = limpiar($_POST['banco'] ?? ($empleado['banco'] ?? ''));
    $datos['cuenta_bancaria'] = limpiar($_POST['cuenta_bancaria'] ?? ($empleado['cuenta_bancaria'] ?? ''));
    $datos['activo'] = isset($_POST['activo']) ? 1 : 0;
    
    // Validaciones
    if ($datos['empresa_id'] <= 0) {
        $errores['empresa_id'] = 'Seleccione una empresa válida';
    } else {
        // Verificar que la empresa pertenezca al usuario
        $stmt = $conn->prepare("SELECT id FROM empresas WHERE id = ? AND usuario_id = ?");
        $stmt->execute([$datos['empresa_id'], $usuario_id]);
        if ($stmt->rowCount() === 0) {
            $errores['empresa_id'] = 'Empresa no válida';
        }
    }
    
    if (empty($datos['nombre'])) {
        $errores['nombre'] = 'El nombre es obligatorio';
    }
    
    if (empty($datos['apellidos'])) {
        $errores['apellidos'] = 'Los apellidos son obligatorios';
    }
    
    if (empty($datos['fecha_ingreso'])) {
        $errores['fecha_ingreso'] = 'La fecha de ingreso es obligatoria';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $datos['fecha_ingreso'])) {
        $errores['fecha_ingreso'] = 'Formato de fecha inválido (AAAA-MM-DD)';
    }
    
    if (!empty($datos['fecha_nacimiento']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $datos['fecha_nacimiento'])) {
        $errores['fecha_nacimiento'] = 'Formato de fecha inválido (AAAA-MM-DD)';
    }
    
    if (empty($datos['puesto'])) {
        $errores['puesto'] = 'El puesto es obligatorio';
    }
    
    if (empty($datos['departamento'])) {
        $errores['departamento'] = 'El departamento es obligatorio';
    }
    
    if ($datos['salario_diario'] <= 0) {
        $errores['salario_diario'] = 'El salario diario debe ser mayor a 0';
    }
    
    if (!empty($datos['cuenta_bancaria']) && !preg_match('/^\d{20}$/', $datos['cuenta_bancaria'])) {
        $errores['cuenta_bancaria'] = 'La cuenta bancaria debe tener 20 dígitos';
    }
    
    // Validar que la fecha de ingreso no sea futura
    if (empty($errores['fecha_ingreso'])) {
        $fecha_ingreso = new DateTime($datos['fecha_ingreso']);
        $hoy = new DateTime();
        if ($fecha_ingreso > $hoy) {
            $errores['fecha_ingreso'] = 'La fecha de ingreso no puede ser futura';
        }
    }
    
    // Si no hay errores, actualizar en la base de datos
    if (empty($errores)) {
        try {
            $stmt = $conn->prepare("
                UPDATE empleados 
                SET empresa_id = ?, nombre = ?, apellidos = ?, fecha_nacimiento = ?, fecha_ingreso = ?,
                    puesto = ?, departamento = ?, salario_diario = ?, tipo_contrato = ?, regimen_contratacion = ?,
                    tipo_jornada = ?, periodicidad_pago = ?, banco = ?, cuenta_bancaria = ?, activo = ?
                WHERE id = ?
            ");
            
            $resultado = $stmt->execute([
                $datos['empresa_id'],
                $datos['nombre'],
                $datos['apellidos'],
                $datos['fecha_nacimiento'] ?: null,
                $datos['fecha_ingreso'],
                $datos['puesto'],
                $datos['departamento'],
                $datos['salario_diario'],
                $datos['tipo_contrato'],
                $datos['regimen_contratacion'],
                $datos['tipo_jornada'],
                $datos['periodicidad_pago'],
                $datos['banco'] ?: null,
                $datos['cuenta_bancaria'] ?: null,
                $datos['activo'],
                $empleado_id
            ]);
            
            if ($resultado) {
                $mensaje = 'success|Empleado actualizado exitosamente.';
                // Actualizar datos del empleado
                $empleado = array_merge($empleado, $datos);
            } else {
                $errores[] = 'Error al actualizar el empleado';
            }
            
        } catch (PDOException $e) {
            $errores[] = 'Error: ' . $e->getMessage();
        }
    }
}

// Calcular antigüedad
$fecha_ingreso = new DateTime($empleado['fecha_ingreso']);
$hoy = new DateTime();
$antiguedad = $hoy->diff($fecha_ingreso);
$antiguedad_texto = $antiguedad->y . ' años, ' . $antiguedad->m . ' meses, ' . $antiguedad->d . ' días';

// Calcular edad si tiene fecha de nacimiento
$edad_texto = '';
if ($empleado['fecha_nacimiento']) {
    $fecha_nac = new DateTime($empleado['fecha_nacimiento']);
    $edad = $hoy->diff($fecha_nac)->y;
    $edad_texto = $edad . ' años';
}

$pageTitle = "Editar Empleado";
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
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>modules/empleados/css/formulario.css">
    
    <style>
        .info-badge {
            background-color: #e0f2fe;
            color: #0369a1;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            display: inline-flex;
            align-items: center;
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
        }
        
        .info-badge i {
            margin-right: 0.5rem;
        }
        
        .employee-summary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
        }
        
        .antiguedad-box {
            background-color: #f0f9ff;
            border: 2px solid #bae6fd;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-top: 1rem;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navbar -->
    <?php include '../../partials/navbar.php'; ?>

    <!-- Main Layout -->
    <div class="flex">
        <!-- Sidebar -->
        <?php include '../../partials/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1">
            <div class="container mx-auto px-4 py-8">
                <!-- Header -->
                <div class="mb-8">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900">Editar Empleado</h1>
                            <p class="text-gray-600 mt-2">Actualiza la información de <?php echo htmlspecialchars($empleado['nombre'] . ' ' . $empleado['apellidos']); ?></p>
                            
                            <div class="mt-4 flex flex-wrap">
                                <span class="info-badge">
                                    <i class="fas fa-building"></i>
                                    <?php echo htmlspecialchars($empleado['empresa_nombre']); ?>
                                </span>
                                <span class="info-badge">
                                    <i class="fas fa-id-card"></i>
                                    ID: <?php echo $empleado['id']; ?>
                                </span>
                                <span class="info-badge">
                                    <i class="fas fa-calendar-alt"></i>
                                    Ingreso: <?php echo date('d/m/Y', strtotime($empleado['fecha_ingreso'])); ?>
                                </span>
                                <?php if($empleado['fecha_nacimiento']): ?>
                                <span class="info-badge">
                                    <i class="fas fa-birthday-cake"></i>
                                    Edad: <?php echo $edad_texto; ?>
                                </span>
                                <?php endif; ?>
                                <span class="info-badge <?php echo $empleado['activo'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                    <i class="fas fa-circle text-xs mr-1"></i>
                                    <?php echo $empleado['activo'] ? 'Activo' : 'Inactivo'; ?>
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
                    
                    <!-- Antigüedad -->
                    <div class="antiguedad-box">
                        <div class="flex items-center">
                            <i class="fas fa-history text-blue-600 text-xl mr-3"></i>
                            <div>
                                <h4 class="font-medium text-gray-900">Antigüedad en la empresa</h4>
                                <p class="text-lg font-bold text-blue-700"><?php echo $antiguedad_texto; ?></p>
                                <p class="text-sm text-gray-600 mt-1">
                                    Desde el <?php echo date('d/m/Y', strtotime($empleado['fecha_ingreso'])); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Formulario -->
                <form method="POST" action="" class="space-y-8">
                    <!-- Sección 1: Empresa y Datos Personales -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="form-section">
                            <h2 class="text-xl font-semibold">
                                <i class="fas fa-building mr-2"></i>
                                1. Empresa y Datos Personales
                            </h2>
                        </div>
                        
                        <div class="mb-6">
                            <label for="empresa_id" class="block text-sm font-medium text-gray-700 mb-1 form-required">
                                Empresa
                            </label>
                            <select name="empresa_id" 
                                    id="empresa_id" 
                                    class="w-full px-4 py-2 border <?php echo isset($errores['empresa_id']) ? 'border-red-500' : 'border-gray-300'; ?> rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    required>
                                <option value="">Seleccione una empresa</option>
                                <?php foreach($empresas as $empresa): ?>
                                <option value="<?php echo $empresa['id']; ?>" 
                                        <?php echo $datos['empresa_id'] == $empresa['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($empresa['nombre']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if(isset($errores['empresa_id'])): ?>
                            <div class="error-message"><?php echo $errores['empresa_id']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Nombre -->
                            <div>
                                <label for="nombre" class="block text-sm font-medium text-gray-700 mb-1 form-required">
                                    Nombre(s)
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
                            
                            <!-- Apellidos -->
                            <div>
                                <label for="apellidos" class="block text-sm font-medium text-gray-700 mb-1 form-required">
                                    Apellidos
                                </label>
                                <input type="text" 
                                       id="apellidos" 
                                       name="apellidos" 
                                       value="<?php echo htmlspecialchars($datos['apellidos']); ?>"
                                       class="w-full px-4 py-2 border <?php echo isset($errores['apellidos']) ? 'border-red-500' : 'border-gray-300'; ?> rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                       required>
                                <?php if(isset($errores['apellidos'])): ?>
                                <div class="error-message"><?php echo $errores['apellidos']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Fecha de Nacimiento -->
                            <div>
                                <label for="fecha_nacimiento" class="block text-sm font-medium text-gray-700 mb-1">
                                    Fecha de Nacimiento
                                </label>
                                <input type="date" 
                                       id="fecha_nacimiento" 
                                       name="fecha_nacimiento" 
                                       value="<?php echo htmlspecialchars($datos['fecha_nacimiento']); ?>"
                                       class="w-full px-4 py-2 border <?php echo isset($errores['fecha_nacimiento']) ? 'border-red-500' : 'border-gray-300'; ?> rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                       max="<?php echo date('Y-m-d'); ?>">
                                <?php if(isset($errores['fecha_nacimiento'])): ?>
                                <div class="error-message"><?php echo $errores['fecha_nacimiento']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Fecha de Ingreso -->
                            <div>
                                <label for="fecha_ingreso" class="block text-sm font-medium text-gray-700 mb-1 form-required">
                                    Fecha de Ingreso
                                </label>
                                <input type="date" 
                                       id="fecha_ingreso" 
                                       name="fecha_ingreso" 
                                       value="<?php echo htmlspecialchars($datos['fecha_ingreso']); ?>"
                                       class="w-full px-4 py-2 border <?php echo isset($errores['fecha_ingreso']) ? 'border-red-500' : 'border-gray-300'; ?> rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                       max="<?php echo date('Y-m-d'); ?>"
                                       required>
                                <?php if(isset($errores['fecha_ingreso'])): ?>
                                <div class="error-message"><?php echo $errores['fecha_ingreso']; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Sección 2: Información Laboral -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="form-section">
                            <h2 class="text-xl font-semibold">
                                <i class="fas fa-briefcase mr-2"></i>
                                2. Información Laboral
                            </h2>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Puesto -->
                            <div>
                                <label for="puesto" class="block text-sm font-medium text-gray-700 mb-1 form-required">
                                    Puesto/Cargo
                                </label>
                                <input type="text" 
                                       id="puesto" 
                                       name="puesto" 
                                       value="<?php echo htmlspecialchars($datos['puesto']); ?>"
                                       class="w-full px-4 py-2 border <?php echo isset($errores['puesto']) ? 'border-red-500' : 'border-gray-300'; ?> rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                       required>
                                <?php if(isset($errores['puesto'])): ?>
                                <div class="error-message"><?php echo $errores['puesto']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Departamento -->
                            <div>
                                <label for="departamento" class="block text-sm font-medium text-gray-700 mb-1 form-required">
                                    Departamento/Área
                                </label>
                                <input type="text" 
                                       id="departamento" 
                                       name="departamento" 
                                       value="<?php echo htmlspecialchars($datos['departamento']); ?>"
                                       class="w-full px-4 py-2 border <?php echo isset($errores['departamento']) ? 'border-red-500' : 'border-gray-300'; ?> rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                       required>
                                <?php if(isset($errores['departamento'])): ?>
                                <div class="error-message"><?php echo $errores['departamento']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Salario Diario -->
                            <div>
                                <label for="salario_diario" class="block text-sm font-medium text-gray-700 mb-1 form-required">
                                    Salario Diario (Bs.)
                                </label>
                                <div class="relative">
                                    <span class="absolute left-3 top-2.5 text-gray-500">$</span>
                                    <input type="number" 
                                           id="salario_diario" 
                                           name="salario_diario" 
                                           value="<?php echo htmlspecialchars($datos['salario_diario']); ?>"
                                           step="0.01"
                                           min="0.01"
                                           class="w-full pl-8 pr-4 py-2 salario-input border <?php echo isset($errores['salario_diario']) ? 'border-red-500' : 'border-gray-300'; ?> rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                           required>
                                </div>
                                <?php if(isset($errores['salario_diario'])): ?>
                                <div class="error-message"><?php echo $errores['salario_diario']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Periodicidad de Pago -->
                            <div>
                                <label for="periodicidad_pago" class="block text-sm font-medium text-gray-700 mb-1 form-required">
                                    Periodicidad de Pago
                                </label>
                                <select name="periodicidad_pago" 
                                        id="periodicidad_pago" 
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="Semanal" <?php echo $datos['periodicidad_pago'] === 'Semanal' ? 'selected' : ''; ?>>Semanal</option>
                                    <option value="Catorcenal" <?php echo $datos['periodicidad_pago'] === 'Catorcenal' ? 'selected' : ''; ?>>Catorcenal</option>
                                    <option value="Quincenal" <?php echo $datos['periodicidad_pago'] === 'Quincenal' ? 'selected' : ''; ?>>Quincenal</option>
                                    <option value="Mensual" <?php echo $datos['periodicidad_pago'] === 'Mensual' ? 'selected' : ''; ?>>Mensual</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-6">
                            <!-- Tipo de Contrato -->
                            <div>
                                <label for="tipo_contrato" class="block text-sm font-medium text-gray-700 mb-1 form-required">
                                    Tipo de Contrato
                                </label>
                                <select name="tipo_contrato" 
                                        id="tipo_contrato" 
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="Tiempo Indeterminado" <?php echo $datos['tipo_contrato'] === 'Tiempo Indeterminado' ? 'selected' : ''; ?>>Tiempo Indeterminado</option>
                                    <option value="Tiempo Determinado" <?php echo $datos['tipo_contrato'] === 'Tiempo Determinado' ? 'selected' : ''; ?>>Tiempo Determinado</option>
                                    <option value="Por Obra" <?php echo $datos['tipo_contrato'] === 'Por Obra' ? 'selected' : ''; ?>>Por Obra</option>
                                    <option value="Honorarios" <?php echo $datos['tipo_contrato'] === 'Honorarios' ? 'selected' : ''; ?>>Honorarios</option>
                                </select>
                            </div>
                            
                            <!-- Régimen de Contratación -->
                            <div>
                                <label for="regimen_contratacion" class="block text-sm font-medium text-gray-700 mb-1 form-required">
                                    Régimen de Contratación
                                </label>
                                <select name="regimen_contratacion" 
                                        id="regimen_contratacion" 
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="Sueldos" <?php echo $datos['regimen_contratacion'] === 'Sueldos' ? 'selected' : ''; ?>>Sueldos</option>
                                    <option value="Jubilados" <?php echo $datos['regimen_contratacion'] === 'Jubilados' ? 'selected' : ''; ?>>Jubilados</option>
                                    <option value="Pensionados" <?php echo $datos['regimen_contratacion'] === 'Pensionados' ? 'selected' : ''; ?>>Pensionados</option>
                                    <option value="Asimilados" <?php echo $datos['regimen_contratacion'] === 'Asimilados' ? 'selected' : ''; ?>>Asimilados</option>
                                </select>
                            </div>
                            
                            <!-- Tipo de Jornada -->
                            <div>
                                <label for="tipo_jornada" class="block text-sm font-medium text-gray-700 mb-1 form-required">
                                    Tipo de Jornada
                                </label>
                                <select name="tipo_jornada" 
                                        id="tipo_jornada" 
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="Diurna" <?php echo $datos['tipo_jornada'] === 'Diurna' ? 'selected' : ''; ?>>Diurna</option>
                                    <option value="Nocturna" <?php echo $datos['tipo_jornada'] === 'Nocturna' ? 'selected' : ''; ?>>Nocturna</option>
                                    <option value="Mixta" <?php echo $datos['tipo_jornada'] === 'Mixta' ? 'selected' : ''; ?>>Mixta</option>
                                    <option value="Reducida" <?php echo $datos['tipo_jornada'] === 'Reducida' ? 'selected' : ''; ?>>Reducida</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Sección 3: Información Bancaria -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="form-section">
                            <h2 class="text-xl font-semibold">
                                <i class="fas fa-university mr-2"></i>
                                3. Información Bancaria (Opcional)
                            </h2>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Banco -->
                            <div>
                                <label for="banco" class="block text-sm font-medium text-gray-700 mb-1">
                                    Banco
                                </label>
                                <select name="banco" 
                                        id="banco" 
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">Seleccione un banco</option>
                                    <?php foreach($bancos as $banco): ?>
                                    <option value="<?php echo htmlspecialchars($banco); ?>" 
                                            <?php echo $datos['banco'] === $banco ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($banco); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Cuenta Bancaria -->
                            <div>
                                <label for="cuenta_bancaria" class="block text-sm font-medium text-gray-700 mb-1">
                                    Número de Cuenta (20 dígitos)
                                </label>
                                <input type="text" 
                                       id="cuenta_bancaria" 
                                       name="cuenta_bancaria" 
                                       value="<?php echo htmlspecialchars($datos['cuenta_bancaria']); ?>"
                                       class="w-full px-4 py-2 border <?php echo isset($errores['cuenta_bancaria']) ? 'border-red-500' : 'border-gray-300'; ?> rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                       placeholder="00000000000000000000"
                                       maxlength="20">
                                <?php if(isset($errores['cuenta_bancaria'])): ?>
                                <div class="error-message"><?php echo $errores['cuenta_bancaria']; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Sección 4: Estatus -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="form-section">
                            <h2 class="text-xl font-semibold">
                                <i class="fas fa-cog mr-2"></i>
                                4. Configuración
                            </h2>
                        </div>
                        
                        <div class="flex items-center">
                            <input type="checkbox" 
                                   id="activo" 
                                   name="activo" 
                                   value="1"
                                   class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                                   <?php echo $datos['activo'] ? 'checked' : ''; ?>>
                            <label for="activo" class="ml-2 block text-sm text-gray-900">
                                Empleado activo (aparecerá en los listados y nóminas)
                            </label>
                        </div>
                        <p class="mt-2 text-sm text-gray-600">
                            Si desactivas este empleado, no podrás procesar nóminas para él.
                            Los datos se mantendrán para historial.
                        </p>
                    </div>

                    <!-- Botones -->
                    <div class="flex justify-end space-x-4 pt-6">
                        <a href="index.php" 
                           class="px-6 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition font-medium">
                            Cancelar
                        </a>
                        <button type="submit" 
                                class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium">
                            <i class="fas fa-save mr-2"></i>
                            Guardar Cambios
                        </button>
                    </div>
                </form>
                
                <!-- Resumen de cálculos -->
                <div class="mt-8 bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">
                        <i class="fas fa-calculator mr-2"></i>Resumen de Cálculos Basados en el Salario Diario
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div class="text-center p-4 bg-blue-50 rounded-lg">
                            <p class="text-sm text-gray-600">Salario Semanal</p>
                            <p class="text-xl font-bold text-blue-700">
                                $<?php echo number_format($empleado['salario_diario'] * 7, 2); ?>
                            </p>
                        </div>
                        
                        <div class="text-center p-4 bg-green-50 rounded-lg">
                            <p class="text-sm text-gray-600">Salario Quincenal</p>
                            <p class="text-xl font-bold text-green-700">
                                $<?php echo number_format($empleado['salario_diario'] * 15, 2); ?>
                            </p>
                        </div>
                        
                        <div class="text-center p-4 bg-purple-50 rounded-lg">
                            <p class="text-sm text-gray-600">Salario Mensual</p>
                            <p class="text-xl font-bold text-purple-700">
                                $<?php echo number_format($empleado['salario_diario'] * 30, 2); ?>
                            </p>
                        </div>
                        
                        <div class="text-center p-4 bg-orange-50 rounded-lg">
                            <p class="text-sm text-gray-600">Bono Vacacional*</p>
                            <p class="text-xl font-bold text-orange-700">
                                $<?php echo number_format($empleado['salario_diario'] * 15, 2); ?>
                            </p>
                        </div>
                    </div>
                    
                    <p class="text-xs text-gray-500 mt-4">
                        *Bono vacacional estimado (15 días de salario). Los cálculos reales pueden variar según la antigüedad y políticas de la empresa.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        // Formatear salario automáticamente
        document.getElementById('salario_diario').addEventListener('input', function() {
            let value = this.value.replace(/[^\d.]/g, '');
            let parts = value.split('.');
            
            if (parts.length > 2) {
                value = parts[0] + '.' + parts.slice(1).join('');
            }
            
            if (parts.length === 2 && parts[1].length > 2) {
                value = parts[0] + '.' + parts[1].substring(0, 2);
            }
            
            this.value = value;
        });
        
        // Validar cuenta bancaria en tiempo real
        document.getElementById('cuenta_bancaria').addEventListener('input', function() {
            let value = this.value.replace(/\D/g, '');
            
            if (value.length > 20) {
                value = value.substring(0, 20);
            }
            
            this.value = value;
            
            if (value.length > 0 && value.length !== 20) {
                this.classList.add('border-yellow-500');
                this.classList.remove('border-gray-300', 'border-red-500');
            } else if (value.length === 20) {
                this.classList.remove('border-yellow-500', 'border-red-500');
                this.classList.add('border-green-500');
            } else {
                this.classList.remove('border-yellow-500', 'border-green-500', 'border-red-500');
                this.classList.add('border-gray-300');
            }
        });
        
        // Validar fecha de nacimiento no sea futura
        document.getElementById('fecha_nacimiento').addEventListener('change', function() {
            const fechaNac = new Date(this.value);
            const hoy = new Date();
            
            if (fechaNac > hoy) {
                alert('La fecha de nacimiento no puede ser futura');
                this.value = '';
            }
        });
        
        // Validar fecha de ingreso no sea futura
        document.getElementById('fecha_ingreso').addEventListener('change', function() {
            const fechaIng = new Date(this.value);
            const hoy = new Date();
            
            if (fechaIng > hoy) {
                alert('La fecha de ingreso no puede ser futura');
                this.value = '<?php echo date('Y-m-d'); ?>';
            }
            
            // Mostrar nueva antigüedad
            if (this.value) {
                calcularAntiguedad();
            }
        });
        
        // Calcular antigüedad
        function calcularAntiguedad() {
            const fechaIngreso = document.getElementById('fecha_ingreso').value;
            if (!fechaIngreso) return;
            
            const fechaIng = new Date(fechaIngreso);
            const hoy = new DateTime();
            const antiguedad = hoy.diff(fechaIng);
            
            // Podrías actualizar un elemento en el DOM con la nueva antigüedad
            console.log('Nueva antigüedad:', antiguedad.y + ' años, ' + antiguedad.m + ' meses, ' + antiguedad.d + ' días');
        }
        
        // Validación al enviar el formulario
        document.querySelector('form').addEventListener('submit', function(e) {
            const salario = parseFloat(document.getElementById('salario_diario').value);
            const cuentaBancaria = document.getElementById('cuenta_bancaria').value;
            
            // Validar salario
            if (isNaN(salario) || salario <= 0) {
                e.preventDefault();
                alert('Por favor ingrese un salario diario válido (mayor a 0)');
                document.getElementById('salario_diario').focus();
                return false;
            }
            
            // Validar cuenta bancaria si se proporciona
            if (cuentaBancaria && cuentaBancaria.length !== 20) {
                e.preventDefault();
                alert('La cuenta bancaria debe tener exactamente 20 dígitos');
                document.getElementById('cuenta_bancaria').focus();
                return false;
            }
            
            // Validar fecha de ingreso
            const fechaIngreso = document.getElementById('fecha_ingreso').value;
            if (!fechaIngreso) {
                e.preventDefault();
                alert('La fecha de ingreso es obligatoria');
                document.getElementById('fecha_ingreso').focus();
                return false;
            }
            
            // Confirmar si se cambia la empresa
            const empresaOriginal = <?php echo $empleado['empresa_id']; ?>;
            const empresaNueva = parseInt(document.getElementById('empresa_id').value);
            
            if (empresaOriginal !== empresaNueva) {
                if (!confirm('¿Estás seguro de cambiar la empresa del empleado? Esto podría afectar sus nóminas históricas.')) {
                    e.preventDefault();
                    return false;
                }
            }
            
            return true;
        });
        
        // Calcular y mostrar cálculos en tiempo real
        document.getElementById('salario_diario').addEventListener('input', function() {
            const salarioDiario = parseFloat(this.value) || 0;
            
            // Actualizar cálculos (podrías mostrar esto en un elemento del DOM)
            const salarioSemanal = salarioDiario * 7;
            const salarioQuincenal = salarioDiario * 15;
            const salarioMensual = salarioDiario * 30;
            const bonoVacacional = salarioDiario * 15;
            
            console.log('Cálculos actualizados:', {
                semanal: salarioSemanal,
                quincenal: salarioQuincenal,
                mensual: salarioMensual,
                bonoVacacional: bonoVacacional
            });
        
        });
    </script>
</body>
</html>