<?php
require_once 'config/database.php';
requerirLogin();

$conn = conectarDB();
$usuario_id = $_SESSION['usuario_id'];

// Obtener estadísticas
$stats = [
    'empresas' => 0,
    'empleados' => 0,
    'nominas_pendientes' => 0,
    'nominas_pagadas' => 0
];

try {
    // 1. Contar empresas del usuario
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM empresas WHERE usuario_id = ?");
    $stmt->execute([$usuario_id]);
    $result = $stmt->fetch();
    $stats['empresas'] = $result['total'];
    
    // 2. Contar empleados totales (de todas las empresas del usuario)
    $stmt = $conn->prepare("
        SELECT COUNT(e.id) as total 
        FROM empleados e
        INNER JOIN empresas emp ON e.empresa_id = emp.id
        WHERE emp.usuario_id = ? AND e.activo = 1
    ");
    $stmt->execute([$usuario_id]);
    $result = $stmt->fetch();
    $stats['empleados'] = $result['total'];
    
    // 3. Contar nóminas pendientes (ejemplo, puedes ajustar según tu estructura)
    $stmt = $conn->prepare("
        SELECT COUNT(n.id) as total 
        FROM nominas n
        INNER JOIN empleados e ON n.empleado_id = e.id
        INNER JOIN empresas emp ON e.empresa_id = emp.id
        WHERE emp.usuario_id = ? AND n.estatus = 'Borrador'
    ");
    $stmt->execute([$usuario_id]);
    $result = $stmt->fetch();
    $stats['nominas_pendientes'] = $result['total'];
    
    // 4. Contar nóminas pagadas
    $stmt = $conn->prepare("
        SELECT COUNT(n.id) as total 
        FROM nominas n
        INNER JOIN empleados e ON n.empleado_id = e.id
        INNER JOIN empresas emp ON e.empresa_id = emp.id
        WHERE emp.usuario_id = ? AND n.estatus = 'Pagada'
    ");
    $stmt->execute([$usuario_id]);
    $result = $stmt->fetch();
    $stats['nominas_pagadas'] = $result['total'];
    
    // 5. Obtener últimas empresas
    $stmt = $conn->prepare("
        SELECT * FROM empresas 
        WHERE usuario_id = ? 
        ORDER BY fecha_alta DESC 
        LIMIT 5
    ");
    $stmt->execute([$usuario_id]);
    $ultimasEmpresas = $stmt->fetchAll();
    
    // 6. Obtener empleados recientes
    $stmt = $conn->prepare("
        SELECT e.*, emp.nombre as empresa_nombre
        FROM empleados e
        INNER JOIN empresas emp ON e.empresa_id = emp.id
        WHERE emp.usuario_id = ?
        ORDER BY e.fecha_ingreso DESC
        LIMIT 5
    ");
    $stmt->execute([$usuario_id]);
    $empleadosRecientes = $stmt->fetchAll();
    
    // 7. Calcular total pagado en nóminas (ejemplo)
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(n.neto_pagar), 0) as total_pagado
        FROM nominas n
        INNER JOIN empleados e ON n.empleado_id = e.id
        INNER JOIN empresas emp ON e.empresa_id = emp.id
        WHERE emp.usuario_id = ? AND n.estatus = 'Pagada'
    ");
    $stmt->execute([$usuario_id]);
    $result = $stmt->fetch();
    $totalPagado = $result['total_pagado'];
    
} catch(PDOException $e) {
    $error = "Error al cargar estadísticas: " . $e->getMessage();
}

$pageTitle = "Dashboard";
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Sistema de Nómina</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* Estilos para el menú desplegable */
        .user-dropdown {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            margin-top: 0.5rem;
            min-width: 180px;
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            animation: fadeIn 0.2s ease-out;
        }
        
        .user-dropdown.show {
            display: block;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Estilos existentes */
        .dashboard-card {
            transition: all 0.3s ease;
        }
        
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .progress-bar {
            height: 6px;
            border-radius: 3px;
            overflow: hidden;
            background-color: #e5e7eb;
        }
        
        .progress-fill {
            height: 100%;
            transition: width 0.3s ease;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navbar -->
    <nav class="bg-gradient-to-r from-blue-600 to-indigo-700 text-white shadow-lg">
        <div class="container mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-3">
                    <a href="dashboard.php" class="flex items-center space-x-3">
                        <i class="fas fa-calculator text-2xl"></i>
                        <span class="text-xl font-bold">NominaContadores</span>
                    </a>
                </div>
                
                <div class="flex items-center space-x-4">
                    <div class="relative">
                        <!-- Botón para abrir/cerrar menú -->
                        <button id="userMenuBtn" 
                                class="flex items-center space-x-2 bg-white/10 hover:bg-white/20 px-4 py-2 rounded-lg transition">
                            <i class="fas fa-user-circle"></i>
                            <span><?php echo $_SESSION['usuario_nombre']; ?></span>
                            <i class="fas fa-chevron-down text-sm transition-transform duration-200" id="chevronIcon"></i>
                        </button>
                        
                        <!-- Menú desplegable (solo Cerrar Sesión) -->
                        <div class="user-dropdown" id="userDropdown">
                            <a href="<?php echo BASE_URL; ?>modulos/auth/logout.php" 
                               class="flex items-center px-4 py-3 hover:bg-gray-100 text-gray-800">
                                <i class="fas fa-sign-out-alt mr-3 text-red-500"></i>
                                <span>Cerrar Sesión</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Layout -->
    <div class="flex">
        <!-- Sidebar -->
        <div class="w-64 bg-white shadow-lg min-h-screen">
            <div class="p-6">
                <div class="mb-8">
                    <h3 class="font-bold text-gray-700 mb-2">Menú Principal</h3>
                    <div class="text-sm text-gray-500"><?php echo $_SESSION['usuario_email']; ?></div>
                </div>
                
                <ul class="space-y-2">
                    <li>
                        <a href="dashboard.php" 
                           class="flex items-center space-x-3 px-4 py-3 rounded-lg bg-blue-50 text-blue-700">
                            <i class="fas fa-tachometer-alt w-6"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="modulos/empresas/" 
                           class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-gray-100 transition">
                            <i class="fas fa-building w-6"></i>
                            <span>Empresas</span>
                            <?php if($stats['empresas'] > 0): ?>
                            <span class="ml-auto bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full">
                                <?php echo $stats['empresas']; ?>
                            </span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li>
                        <a href="modulos/empleados/" 
                           class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-gray-100 transition">
                            <i class="fas fa-users w-6"></i>
                            <span>Empleados</span>
                            <?php if($stats['empleados'] > 0): ?>
                            <span class="ml-auto bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full">
                                <?php echo $stats['empleados']; ?>
                            </span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li>
                        <a href="modulos/nominas/" 
                           class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-gray-100 transition">
                            <i class="fas fa-file-invoice-dollar w-6"></i>
                            <span>Nóminas</span>
                            <?php if($stats['nominas_pendientes'] > 0): ?>
                            <span class="ml-auto bg-yellow-100 text-yellow-800 text-xs px-2 py-1 rounded-full">
                                <?php echo $stats['nominas_pendientes']; ?>
                            </span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li>
                        <a href="modulos/reportes/" 
                           class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-gray-100 transition">
                            <i class="fas fa-chart-bar w-6"></i>
                            <span>Reportes</span>
                        </a>
                    </li>
                </ul>
                
                <!-- Quick Stats Sidebar -->
                <div class="mt-8 pt-6 border-t">
                    <h4 class="text-xs font-semibold text-gray-500 uppercase mb-3">Resumen Rápido</h4>
                    <div class="space-y-3">
                        <div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Empresas</span>
                                <span class="font-medium"><?php echo $stats['empresas']; ?></span>
                            </div>
                            <div class="progress-bar mt-1">
                                <div class="progress-fill bg-blue-500" 
                                     style="width: <?php echo min(100, $stats['empresas'] * 20); ?>%"></div>
                            </div>
                        </div>
                        
                        <div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Empleados</span>
                                <span class="font-medium"><?php echo $stats['empleados']; ?></span>
                            </div>
                            <div class="progress-bar mt-1">
                                <div class="progress-fill bg-green-500" 
                                     style="width: <?php echo min(100, $stats['empleados'] * 2); ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex-1">
            <div class="container mx-auto px-4 py-8">
                <!-- Header y Welcome -->
                <div class="mb-8">
                    <div class="flex justify-between items-center mb-4">
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900">Dashboard</h1>
                            <p class="text-gray-600 mt-2">Bienvenido, <?php echo $_SESSION['usuario_nombre']; ?></p>
                        </div>
                        <div class="text-sm text-gray-500">
                            <?php echo date('l, d F Y'); ?>
                        </div>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                        <a href="modulos/empresas/agregar.php" 
                           class="flex items-center justify-center p-4 bg-gradient-to-r from-blue-50 to-blue-100 border border-blue-200 rounded-lg hover:from-blue-100 hover:to-blue-200 transition">
                            <i class="fas fa-plus-circle text-blue-600 text-xl mr-3"></i>
                            <span class="font-medium text-blue-900">Nueva Empresa</span>
                        </a>
                        
                        <a href="modulos/empleados/agregar.php" 
                           class="flex items-center justify-center p-4 bg-gradient-to-r from-green-50 to-green-100 border border-green-200 rounded-lg hover:from-green-100 hover:to-green-200 transition">
                            <i class="fas fa-user-plus text-green-600 text-xl mr-3"></i>
                            <span class="font-medium text-green-900">Nuevo Empleado</span>
                        </a>
                        
                        <a href="modulos/nominas/calcular.php" 
                           class="flex items-center justify-center p-4 bg-gradient-to-r from-purple-50 to-purple-100 border border-purple-200 rounded-lg hover:from-purple-100 hover:to-purple-200 transition">
                            <i class="fas fa-calculator text-purple-600 text-xl mr-3"></i>
                            <span class="font-medium text-purple-900">Calcular Nómina</span>
                        </a>
                    </div>
                </div>

                <!-- Stats Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <!-- Empresas Card -->
                    <div class="dashboard-card bg-white rounded-xl shadow p-6 border-l-4 border-blue-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Empresas</p>
                                <p class="text-3xl font-bold text-gray-900 mt-2"><?php echo $stats['empresas']; ?></p>
                            </div>
                            <div class="bg-blue-100 p-3 rounded-lg">
                                <i class="fas fa-building text-2xl text-blue-600"></i>
                            </div>
                        </div>
                        <a href="modulos/empresas/" 
                           class="inline-flex items-center text-blue-600 hover:text-blue-800 mt-4 text-sm font-medium">
                            Gestionar empresas
                            <i class="fas fa-arrow-right ml-2"></i>
                        </a>
                    </div>

                    <!-- Empleados Card -->
                    <div class="dashboard-card bg-white rounded-xl shadow p-6 border-l-4 border-green-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Empleados Activos</p>
                                <p class="text-3xl font-bold text-gray-900 mt-2"><?php echo $stats['empleados']; ?></p>
                            </div>
                            <div class="bg-green-100 p-3 rounded-lg">
                                <i class="fas fa-users text-2xl text-green-600"></i>
                            </div>
                        </div>
                        <a href="modulos/empleados/" 
                           class="inline-flex items-center text-green-600 hover:text-green-800 mt-4 text-sm font-medium">
                            Ver empleados
                            <i class="fas fa-arrow-right ml-2"></i>
                        </a>
                    </div>

                    <!-- Nóminas Pendientes -->
                    <div class="dashboard-card bg-white rounded-xl shadow p-6 border-l-4 border-yellow-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Nóminas Pendientes</p>
                                <p class="text-3xl font-bold text-gray-900 mt-2"><?php echo $stats['nominas_pendientes']; ?></p>
                            </div>
                            <div class="bg-yellow-100 p-3 rounded-lg">
                                <i class="fas fa-clock text-2xl text-yellow-600"></i>
                            </div>
                        </div>
                        <a href="modulos/nominas/" 
                           class="inline-flex items-center text-yellow-600 hover:text-yellow-800 mt-4 text-sm font-medium">
                            Procesar nóminas
                            <i class="fas fa-arrow-right ml-2"></i>
                        </a>
                    </div>

                    <!-- Nóminas Pagadas -->
                    <div class="dashboard-card bg-white rounded-xl shadow p-6 border-l-4 border-purple-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Nóminas Pagadas</p>
                                <p class="text-3xl font-bold text-gray-900 mt-2"><?php echo $stats['nominas_pagadas']; ?></p>
                            </div>
                            <div class="bg-purple-100 p-3 rounded-lg">
                                <i class="fas fa-check-circle text-2xl text-purple-600"></i>
                            </div>
                        </div>
                        <a href="modulos/nominas/?estatus=Pagada" 
                           class="inline-flex items-center text-purple-600 hover:text-purple-800 mt-4 text-sm font-medium">
                            Ver historial
                            <i class="fas fa-arrow-right ml-2"></i>
                        </a>
                    </div>
                </div>

                <!-- Charts and Recent Activity -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                    <!-- Chart Section -->
                    <div class="bg-white rounded-xl shadow p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-xl font-bold text-gray-900">Actividad Mensual</h2>
                            <select class="text-sm border border-gray-300 rounded-lg px-3 py-1">
                                <option>Últimos 30 días</option>
                                <option>Este mes</option>
                                <option>Este año</option>
                            </select>
                        </div>
                        <div class="h-64">
                            <canvas id="activityChart"></canvas>
                        </div>
                    </div>

                    <!-- Recent Companies -->
                    <div class="bg-white rounded-xl shadow p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-xl font-bold text-gray-900">Empresas Recientes</h2>
                            <a href="modulos/empresas/" class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                                Ver todas
                            </a>
                        </div>
                        
                        <div class="space-y-4">
                            <?php if(count($ultimasEmpresas) > 0): ?>
                                <?php foreach($ultimasEmpresas as $empresa): ?>
                                <div class="flex items-center justify-between p-3 hover:bg-gray-50 rounded-lg transition">
                                    <div class="flex items-center">
                                        <div class="bg-blue-100 p-2 rounded-lg mr-3">
                                            <i class="fas fa-building text-blue-600"></i>
                                        </div>
                                        <div>
                                            <p class="font-medium text-gray-900"><?php echo htmlspecialchars($empresa['nombre']); ?></p>
                                            <p class="text-sm text-gray-500">RFC: <?php echo htmlspecialchars($empresa['rfc']); ?></p>
                                        </div>
                                    </div>
                                    <a href="modulos/empresas/editar.php?id=<?php echo $empresa['id']; ?>" 
                                       class="text-blue-600 hover:text-blue-800">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center py-8">
                                    <div class="text-gray-400 mb-4">
                                        <i class="fas fa-building text-4xl"></i>
                                    </div>
                                    <p class="text-gray-500">No hay empresas registradas</p>
                                    <a href="modulos/empresas/agregar.php" 
                                       class="inline-block mt-2 text-blue-600 hover:text-blue-800 font-medium">
                                        Agregar primera empresa
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Recent Employees and To-Do -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Recent Employees -->
                    <div class="bg-white rounded-xl shadow p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-xl font-bold text-gray-900">Empleados Recientes</h2>
                            <a href="modulos/empleados/" class="text-sm text-green-600 hover:text-green-800 font-medium">
                                Ver todos
                            </a>
                        </div>
                        
                        <div class="space-y-4">
                            <?php if(count($empleadosRecientes) > 0): ?>
                                <?php foreach($empleadosRecientes as $empleado): ?>
                                <div class="flex items-center justify-between p-3 hover:bg-gray-50 rounded-lg transition">
                                    <div class="flex items-center">
                                        <div class="bg-green-100 p-2 rounded-lg mr-3">
                                            <i class="fas fa-user text-green-600"></i>
                                        </div>
                                        <div>
                                            <p class="font-medium text-gray-900">
                                                <?php echo htmlspecialchars($empleado['nombre'] . ' ' . $empleado['apellidos']); ?>
                                            </p>
                                            <p class="text-sm text-gray-500">
                                                <?php echo htmlspecialchars($empleado['empresa_nombre']); ?> • 
                                                <?php echo date('d/m/Y', strtotime($empleado['fecha_ingreso'])); ?>
                                            </p>
                                        </div>
                                    </div>
                                    <span class="text-sm px-2 py-1 rounded-full bg-gray-100 text-gray-800">
                                        $<?php echo number_format($empleado['salario_diario'], 2); ?>
                                    </span>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center py-8">
                                    <div class="text-gray-400 mb-4">
                                        <i class="fas fa-users text-4xl"></i>
                                    </div>
                                    <p class="text-gray-500">No hay empleados registrados</p>
                                    <a href="modulos/empleados/agregar.php" 
                                       class="inline-block mt-2 text-green-600 hover:text-green-800 font-medium">
                                        Agregar primer empleado
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- To-Do / Pending Tasks -->
                    <div class="bg-white rounded-xl shadow p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-xl font-bold text-gray-900">Tareas Pendientes</h2>
                            <span class="text-sm text-gray-500">Hoy</span>
                        </div>
                        
                        <div class="space-y-4">
                            <?php if($stats['nominas_pendientes'] > 0): ?>
                            <div class="flex items-center justify-between p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                                <div class="flex items-center">
                                    <div class="bg-yellow-100 p-2 rounded-lg mr-3">
                                        <i class="fas fa-file-invoice-dollar text-yellow-600"></i>
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-900">Nóminas pendientes</p>
                                        <p class="text-sm text-gray-600">
                                            <?php echo $stats['nominas_pendientes']; ?> nómina(s) por procesar
                                        </p>
                                    </div>
                                </div>
                                <a href="modulos/nominas/" class="text-yellow-600 hover:text-yellow-800">
                                    <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                            <?php endif; ?>
                            
                            <?php if($stats['empresas'] == 0): ?>
                            <div class="flex items-center justify-between p-3 bg-blue-50 border border-blue-200 rounded-lg">
                                <div class="flex items-center">
                                    <div class="bg-blue-100 p-2 rounded-lg mr-3">
                                        <i class="fas fa-building text-blue-600"></i>
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-900">Primera empresa</p>
                                        <p class="text-sm text-gray-600">Comienza agregando tu primera empresa</p>
                                    </div>
                                </div>
                                <a href="modulos/empresas/agregar.php" class="text-blue-600 hover:text-blue-800">
                                    <i class="fas fa-plus"></i>
                                </a>
                            </div>
                            <?php endif; ?>
                            
                            <?php if($stats['empleados'] == 0 && $stats['empresas'] > 0): ?>
                            <div class="flex items-center justify-between p-3 bg-green-50 border border-green-200 rounded-lg">
                                <div class="flex items-center">
                                    <div class="bg-green-100 p-2 rounded-lg mr-3">
                                        <i class="fas fa-user-plus text-green-600"></i>
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-900">Primer empleado</p>
                                        <p class="text-sm text-gray-600">Agrega empleados a tu empresa</p>
                                    </div>
                                </div>
                                <a href="modulos/empleados/agregar.php" class="text-green-600 hover:text-green-800">
                                    <i class="fas fa-plus"></i>
                                </a>
                            </div>
                            <?php endif; ?>
                            
                            <div class="flex items-center justify-between p-3 bg-gray-50 border border-gray-200 rounded-lg">
                                <div class="flex items-center">
                                    <div class="bg-gray-100 p-2 rounded-lg mr-3">
                                        <i class="fas fa-chart-line text-gray-600"></i>
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-900">Reporte mensual</p>
                                        <p class="text-sm text-gray-600">Generar reporte del mes actual</p>
                                    </div>
                                </div>
                                <a href="modulos/reportes/" class="text-gray-600 hover:text-gray-800">
                                    <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                        
                        <!-- Quick Stats -->
                        <div class="mt-8 pt-6 border-t">
                            <div class="grid grid-cols-2 gap-4">
                                <div class="text-center p-3 bg-gray-50 rounded-lg">
                                    <p class="text-2xl font-bold text-gray-900"><?php echo $stats['empresas']; ?></p>
                                    <p class="text-sm text-gray-600">Empresas</p>
                                </div>
                                <div class="text-center p-3 bg-gray-50 rounded-lg">
                                    <p class="text-2xl font-bold text-gray-900"><?php echo $stats['empleados']; ?></p>
                                    <p class="text-sm text-gray-600">Empleados</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer Stats -->
                <div class="mt-8 bg-white rounded-xl shadow p-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-6">Resumen del Sistema</h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="text-center p-4 border rounded-lg">
                            <div class="text-3xl font-bold text-blue-600 mb-2"><?php echo $stats['empresas']; ?></div>
                            <p class="text-gray-700">Empresas gestionadas</p>
                            <p class="text-sm text-gray-500 mt-2">Clientes activos en el sistema</p>
                        </div>
                        
                        <div class="text-center p-4 border rounded-lg">
                            <div class="text-3xl font-bold text-green-600 mb-2"><?php echo $stats['empleados']; ?></div>
                            <p class="text-gray-700">Empleados registrados</p>
                            <p class="text-sm text-gray-500 mt-2">Total de nóminas posibles</p>
                        </div>
                        
                        <div class="text-center p-4 border rounded-lg">
                            <div class="text-3xl font-bold text-purple-600 mb-2">
                                $<?php echo number_format($totalPagado, 2); ?>
                            </div>
                            <p class="text-gray-700">Total pagado</p>
                            <p class="text-sm text-gray-500 mt-2">En nóminas procesadas</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript para el menú desplegable -->
    <script>
        // Control del menú desplegable
        const userMenuBtn = document.getElementById('userMenuBtn');
        const userDropdown = document.getElementById('userDropdown');
        const chevronIcon = document.getElementById('chevronIcon');
        
        let isDropdownOpen = false;
        
        // Abrir/cerrar menú al hacer clic
        userMenuBtn.addEventListener('click', function(e) {
            e.stopPropagation(); // Evitar que el clic se propague
            isDropdownOpen = !isDropdownOpen;
            
            if (isDropdownOpen) {
                userDropdown.classList.add('show');
                chevronIcon.style.transform = 'rotate(180deg)';
            } else {
                userDropdown.classList.remove('show');
                chevronIcon.style.transform = 'rotate(0deg)';
            }
        });
        
        // Cerrar menú al hacer clic fuera
        document.addEventListener('click', function(e) {
            if (!userMenuBtn.contains(e.target) && !userDropdown.contains(e.target)) {
                userDropdown.classList.remove('show');
                chevronIcon.style.transform = 'rotate(0deg)';
                isDropdownOpen = false;
            }
        });
        
        // Cerrar menú al hacer clic en una opción
        userDropdown.addEventListener('click', function() {
            userDropdown.classList.remove('show');
            chevronIcon.style.transform = 'rotate(0deg)';
            isDropdownOpen = false;
        });
        
        // Evitar que el menú se cierre al hacer clic dentro de él
        userDropdown.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    </script>
    
    <!-- JavaScript para gráficos (opcional) -->
    <script>
        // Gráfico de actividad
        const ctx = document.getElementById('activityChart');
        if (ctx) {
            const activityChart = new Chart(ctx.getContext('2d'), {
                type: 'line',
                data: {
                    labels: ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul'],
                    datasets: [{
                        label: 'Nóminas procesadas',
                        data: [12, 19, 8, 15, 22, 18, 25],
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }, {
                        label: 'Empleados agregados',
                        data: [5, 10, 6, 12, 8, 15, 10],
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        x: {
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        }
                    }
                }
            });
        }

        // Actualizar animaciones de las cards
        document.querySelectorAll('.dashboard-card').forEach(card => {
            card.addEventListener('mouseenter', () => {
                card.style.transform = 'translateY(-5px)';
            });
            
            card.addEventListener('mouseleave', () => {
                card.style.transform = 'translateY(0)';
            });
        });
    </script>
</body>
</html>