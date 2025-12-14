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

// Variables para datos del gráfico
$datosGrafico = [
    'tieneDatos' => false,
    'meses' => ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul'],
    'nominasProcesadas' => [0, 0, 0, 0, 0, 0, 0],
    'empleadosAgregados' => [0, 0, 0, 0, 0, 0, 0]
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
    
    // 3. Contar nóminas pendientes
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
        SELECT id, nombre, rif, direccion, fecha_alta 
        FROM empresas 
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
    
    // 7. Calcular total pagado en nóminas
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
    
    // 8. OBTENER DATOS PARA EL GRÁFICO - Nóminas procesadas por mes (últimos 6 meses)
    $stmt = $conn->prepare("
        SELECT 
            DATE_FORMAT(n.fecha_pago, '%b') as mes,
            MONTH(n.fecha_pago) as mes_numero,
            COUNT(*) as total_nominas
        FROM nominas n
        INNER JOIN empleados e ON n.empleado_id = e.id
        INNER JOIN empresas emp ON e.empresa_id = emp.id
        WHERE emp.usuario_id = ? 
            AND n.estatus = 'Pagada'
            AND n.fecha_pago >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY MONTH(n.fecha_pago), DATE_FORMAT(n.fecha_pago, '%b')
        ORDER BY MONTH(n.fecha_pago)
    ");
    $stmt->execute([$usuario_id]);
    $nominasPorMes = $stmt->fetchAll();
    
    // 9. OBTENER DATOS PARA EL GRÁFICO - Empleados agregados por mes (últimos 6 meses)
    $stmt = $conn->prepare("
        SELECT 
            DATE_FORMAT(e.fecha_ingreso, '%b') as mes,
            MONTH(e.fecha_ingreso) as mes_numero,
            COUNT(*) as total_empleados
        FROM empleados e
        INNER JOIN empresas emp ON e.empresa_id = emp.id
        WHERE emp.usuario_id = ? 
            AND e.fecha_ingreso >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY MONTH(e.fecha_ingreso), DATE_FORMAT(e.fecha_ingreso, '%b')
        ORDER BY MONTH(e.fecha_ingreso)
    ");
    $stmt->execute([$usuario_id]);
    $empleadosPorMes = $stmt->fetchAll();
    
    // Procesar datos para el gráfico
    if (!empty($nominasPorMes) || !empty($empleadosPorMes)) {
        $datosGrafico['tieneDatos'] = true;
        
        // Mapear nombres de meses en español
        $mesesEspanol = [
            'Jan' => 'Ene', 'Feb' => 'Feb', 'Mar' => 'Mar', 'Apr' => 'Abr',
            'May' => 'May', 'Jun' => 'Jun', 'Jul' => 'Jul', 'Aug' => 'Ago',
            'Sep' => 'Sep', 'Oct' => 'Oct', 'Nov' => 'Nov', 'Dec' => 'Dic'
        ];
        
        // Obtener los últimos 6 meses
        $ultimos6Meses = [];
        for ($i = 5; $i >= 0; $i--) {
            $fecha = date('M', strtotime("-$i months"));
            $mesNumero = date('n', strtotime("-$i months"));
            $mesEspanol = isset($mesesEspanol[$fecha]) ? $mesesEspanol[$fecha] : $fecha;
            $ultimos6Meses[$mesNumero] = $mesEspanol;
        }
        
        // Actualizar meses del gráfico
        $datosGrafico['meses'] = array_values($ultimos6Meses);
        
        // Llenar datos de nóminas procesadas
        foreach ($nominasPorMes as $dato) {
            $mesNumero = $dato['mes_numero'];
            $mesIndex = array_search($mesNumero, array_keys($ultimos6Meses));
            if ($mesIndex !== false) {
                $datosGrafico['nominasProcesadas'][$mesIndex] = $dato['total_nominas'];
            }
        }
        
        // Llenar datos de empleados agregados
        foreach ($empleadosPorMes as $dato) {
            $mesNumero = $dato['mes_numero'];
            $mesIndex = array_search($mesNumero, array_keys($ultimos6Meses));
            if ($mesIndex !== false) {
                $datosGrafico['empleadosAgregados'][$mesIndex] = $dato['total_empleados'];
            }
        }
    }
    
} catch(PDOException $e) {
    $error = "Error al cargar estadísticas: " . $e->getMessage();
}

$paginaActual = 'dashboard';
$pageTitle = "Dashboard";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Sistema de Nómina</title>
    
    <!-- Tailwind CSS -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/tailwind-output.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Estilos personalizados para Dashboard -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/dashboard.css">
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
                            <a href="<?php echo BASE_URL; ?>modules/auth/logout.php" 
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
                        class="flex items-center space-x-3 px-4 py-3 rounded-lg <?php echo $paginaActual === 'dashboard' ? 'bg-blue-50 text-blue-700' : 'hover:bg-gray-100 transition'; ?>">
                            <i class="fas fa-tachometer-alt w-6"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="modules/empresas/" 
                        class="flex items-center space-x-3 px-4 py-3 rounded-lg <?php echo $paginaActual === 'empresas' ? 'bg-blue-50 text-blue-700' : 'hover:bg-gray-100 transition'; ?>">
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
                        <a href="modules/empleados/" 
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
                        <a href="modules/nominas/" 
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
                        <a href="modules/reportes/" 
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
                            <?php echo fechaEnEspanol('l, d F Y'); ?>
                        </div>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                        <a href="modules/empresas/agregar.php" 
                           class="flex items-center justify-center p-4 bg-gradient-to-r from-blue-50 to-blue-100 border border-blue-200 rounded-lg hover:from-blue-100 hover:to-blue-200 transition">
                            <i class="fas fa-plus-circle text-blue-600 text-xl mr-3"></i>
                            <span class="font-medium text-blue-900">Nueva Empresa</span>
                        </a>
                        
                        <a href="modules/empleados/agregar.php" 
                           class="flex items-center justify-center p-4 bg-gradient-to-r from-green-50 to-green-100 border border-green-200 rounded-lg hover:from-green-100 hover:to-green-200 transition">
                            <i class="fas fa-user-plus text-green-600 text-xl mr-3"></i>
                            <span class="font-medium text-green-900">Nuevo Empleado</span>
                        </a>
                        
                        <a href="modules/nominas/calcular.php" 
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
                        <a href="modules/empresas/" 
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
                        <a href="modules/empleados/" 
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
                        <a href="modules/nominas/" 
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
                        <a href="modules/nominas/?estatus=Pagada" 
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
                            <select class="text-sm border border-gray-300 rounded-lg px-3 py-1" id="periodoSelect">
                                <option value="6">Últimos 6 meses</option>
                                <option value="3">Últimos 3 meses</option>
                                <option value="12">Este año</option>
                            </select>
                        </div>
                        <div class="h-64">
                            <?php if($datosGrafico['tieneDatos']): ?>
                                <canvas id="activityChart"></canvas>
                            <?php else: ?>
                                <!-- Mensaje cuando no hay datos -->
                                <div class="flex flex-col items-center justify-center h-full text-gray-400">
                                    <i class="fas fa-chart-line text-4xl mb-3"></i>
                                    <p class="text-lg font-medium">No hay actividad registrada</p>
                                    <p class="text-sm mt-2 text-center">Comienza procesando nóminas o agregando empleados</p>
                                    <div class="mt-4 flex space-x-2">
                                        <a href="modules/nominas/calcular.php" 
                                           class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition text-sm">
                                            <i class="fas fa-calculator mr-2"></i>Calcular Nómina
                                        </a>
                                        <a href="modules/empleados/agregar.php" 
                                           class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition text-sm">
                                            <i class="fas fa-user-plus mr-2"></i>Agregar Empleado
                                        </a>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Recent Companies -->
                    <div class="bg-white rounded-xl shadow p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-xl font-bold text-gray-900">Empresas Recientes</h2>
                            <a href="modules/empresas/" class="text-sm text-blue-600 hover:text-blue-800 font-medium">
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
                                            <p class="text-sm text-gray-500">RIF: <?php echo htmlspecialchars($empresa['rif']); ?></p>
                                        </div>
                                    </div>
                                    <a href="modules/empresas/editar.php?id=<?php echo $empresa['id']; ?>" 
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
                                    <a href="modules/empresas/agregar.php" 
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
                            <a href="modules/empleados/" class="text-sm text-green-600 hover:text-green-800 font-medium">
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
                                    <a href="modules/empleados/agregar.php" 
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
                                <a href="modules/nominas/" class="text-yellow-600 hover:text-yellow-800">
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
                                <a href="modules/empresas/agregar.php" class="text-blue-600 hover:text-blue-800">
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
                                <a href="modules/empleados/agregar.php" class="text-green-600 hover:text-green-800">
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
                                <a href="modules/reportes/" class="text-gray-600 hover:text-gray-800">
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

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Pasar datos PHP a JavaScript -->
    <script>
        window.dashboardData = {
            tieneDatos: <?php echo $datosGrafico['tieneDatos'] ? 'true' : 'false'; ?>,
            meses: <?php echo json_encode($datosGrafico['meses']); ?>,
            nominasProcesadas: <?php echo json_encode($datosGrafico['nominasProcesadas']); ?>,
            empleadosAgregados: <?php echo json_encode($datosGrafico['empleadosAgregados']); ?>
        };
    </script>

    <!-- Scripts externos -->
    <script src="<?php echo BASE_URL; ?>assets/js/dashboard/userMenu.js"></script>
    <script src="<?php echo BASE_URL; ?>assets/js/dashboard/charts.js"></script>
    <script src="<?php echo BASE_URL; ?>assets/js/dashboard/cardAnimations.js"></script>
</body>
</html>