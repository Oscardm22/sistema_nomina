<?php
require_once '../../config/database.php';
requerirLogin();

$conn = conectarDB();
$usuario_id = $_SESSION['usuario_id'];

// Obtener todas las empresas del usuario para el filtro
$stmt_empresas = $conn->prepare("SELECT id, nombre FROM empresas WHERE usuario_id = ? AND activa = 1 ORDER BY nombre");
$stmt_empresas->execute([$usuario_id]);
$empresas_usuario = $stmt_empresas->fetchAll();

// Manejar filtros
$empresa_id = isset($_GET['empresa_id']) ? intval($_GET['empresa_id']) : 0;
$busqueda = isset($_GET['busqueda']) ? limpiar($_GET['busqueda']) : '';
$estatus = isset($_GET['estatus']) ? limpiar($_GET['estatus']) : 'activos';
$pagina = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$por_pagina = 15;
$offset = ($pagina - 1) * $por_pagina;

// Construir consulta base
$sql = "SELECT e.*, emp.nombre as empresa_nombre 
        FROM empleados e 
        INNER JOIN empresas emp ON e.empresa_id = emp.id 
        WHERE emp.usuario_id = ?";
$params = [$usuario_id];

// Aplicar filtros
if ($empresa_id > 0) {
    $sql .= " AND e.empresa_id = ?";
    $params[] = $empresa_id;
}

if (!empty($busqueda)) {
    $sql .= " AND (e.nombre LIKE ? OR e.apellidos LIKE ? OR e.puesto LIKE ?)";
    $busqueda_param = "%$busqueda%";
    $params[] = $busqueda_param;
    $params[] = $busqueda_param;
    $params[] = $busqueda_param;
}

if ($estatus === 'activos') {
    $sql .= " AND e.activo = 1";
} elseif ($estatus === 'inactivos') {
    $sql .= " AND e.activo = 0";
}

// Contar total de registros
$sql_count = str_replace('SELECT e.*, emp.nombre as empresa_nombre', 'SELECT COUNT(*) as total', $sql);
$stmt_count = $conn->prepare($sql_count);
$stmt_count->execute($params);
$total_empleados = $stmt_count->fetch()['total'];
$total_paginas = ceil($total_empleados / $por_pagina);

// Obtener empleados con paginación
$sql .= " ORDER BY e.fecha_ingreso DESC LIMIT ? OFFSET ?";
$params[] = $por_pagina;
$params[] = $offset;

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$empleados = $stmt->fetchAll();

// Obtener estadísticas
$stmt_stats = $conn->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN activo = 1 THEN 1 ELSE 0 END) as activos,
        SUM(CASE WHEN activo = 1 THEN salario_diario ELSE 0 END) as total_nomina_diaria,
        AVG(CASE WHEN activo = 1 THEN salario_diario ELSE NULL END) as promedio_salario
    FROM empleados e
    INNER JOIN empresas emp ON e.empresa_id = emp.id
    WHERE emp.usuario_id = ?" . ($empresa_id > 0 ? " AND e.empresa_id = ?" : "")
);
$params_stats = [$usuario_id];
if ($empresa_id > 0) {
    $params_stats[] = $empresa_id;
}
$stmt_stats->execute($params_stats);
$stats = $stmt_stats->fetch();

// Obtener distribución por tipo de contrato
$stmt_contratos = $conn->prepare("
    SELECT tipo_contrato, COUNT(*) as cantidad
    FROM empleados e
    INNER JOIN empresas emp ON e.empresa_id = emp.id
    WHERE emp.usuario_id = ? AND e.activo = 1
    GROUP BY tipo_contrato
");
$stmt_contratos->execute([$usuario_id]);
$distribucion_contratos = $stmt_contratos->fetchAll();

$paginaActual = 'empleados';
$pageTitle = "Gestión de Empleados";
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
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>modules/empleados/css/listado.css">
    
    <style>
        /* Estilos adicionales específicos */
        .salario-diario {
            font-weight: 600;
            color: #059669;
        }
        .contrato-badge {
            font-size: 0.7rem;
            padding: 0.15rem 0.4rem;
            border-radius: 0.25rem;
            display: inline-block;
        }
        .contrato-indeterminado { background-color: #d1fae5; color: #065f46; }
        .contrato-determinado { background-color: #fef3c7; color: #92400e; }
        .contrato-obra { background-color: #e0e7ff; color: #3730a3; }
        .contrato-honorarios { background-color: #fce7f3; color: #9d174d; }
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
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900">Empleados</h1>
                            <p class="text-gray-600 mt-2">Gestiona los empleados de tus empresas</p>
                        </div>
                        <div class="mt-4 md:mt-0">
                            <a href="agregar.php" 
                               class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                                <i class="fas fa-user-plus mr-2"></i>
                                Nuevo Empleado
                            </a>
                        </div>
                    </div>

                    <!-- Filtros y Búsqueda -->
                    <div class="bg-white rounded-lg shadow p-4 mb-6">
                        <form method="GET" action="" class="flex flex-col md:flex-row gap-4">
                            <!-- Select de Empresa -->
                            <div class="w-full md:w-1/3">
                                <select name="empresa_id" 
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="0">Todas las empresas</option>
                                    <?php foreach($empresas_usuario as $empresa): ?>
                                    <option value="<?php echo $empresa['id']; ?>" 
                                            <?php echo $empresa_id == $empresa['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($empresa['nombre']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Búsqueda -->
                            <div class="flex-1">
                                <div class="relative">
                                    <input type="text" 
                                           name="busqueda" 
                                           value="<?php echo htmlspecialchars($busqueda); ?>"
                                           placeholder="Buscar por nombre, apellido o puesto..."
                                           class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                                </div>
                            </div>
                            
                            <!-- Filtro de estatus -->
                            <div>
                                <select name="estatus" 
                                        class="w-full md:w-auto px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="activos" <?php echo $estatus === 'activos' ? 'selected' : ''; ?>>Activos</option>
                                    <option value="inactivos" <?php echo $estatus === 'inactivos' ? 'selected' : ''; ?>>Inactivos</option>
                                    <option value="todos" <?php echo $estatus === 'todos' ? 'selected' : ''; ?>>Todos</option>
                                </select>
                            </div>
                            
                            <!-- Botones -->
                            <div class="flex gap-2">
                                <button type="submit" 
                                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                                    <i class="fas fa-filter mr-2"></i>Filtrar
                                </button>
                                <?php if($empresa_id > 0 || $busqueda || $estatus !== 'activos'): ?>
                                <a href="index.php" 
                                   class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                                    <i class="fas fa-times mr-2"></i>Limpiar
                                </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Estadísticas -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="bg-blue-100 p-3 rounded-lg mr-4">
                                <i class="fas fa-users text-blue-600 text-xl"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Total Empleados</p>
                                <p class="text-2xl font-bold text-gray-900"><?php echo $stats['total'] ?? 0; ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="bg-green-100 p-3 rounded-lg mr-4">
                                <i class="fas fa-user-check text-green-600 text-xl"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Empleados Activos</p>
                                <p class="text-2xl font-bold text-gray-900"><?php echo $stats['activos'] ?? 0; ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="bg-purple-100 p-3 rounded-lg mr-4">
                                <i class="fas fa-money-bill-wave text-purple-600 text-xl"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Salario Diario</p>
                                <p class="text-2xl font-bold text-gray-900">
                                    $<?php echo number_format($stats['total_nomina_diaria'] ?? 0, 2); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="bg-orange-100 p-3 rounded-lg mr-4">
                                <i class="fas fa-chart-line text-orange-600 text-xl"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Promedio Salario</p>
                                <p class="text-2xl font-bold text-gray-900">
                                    $<?php echo number_format($stats['promedio_salario'] ?? 0, 2); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Distribución por tipo de contrato -->
                <?php if(count($distribucion_contratos) > 0): ?>
                <div class="mb-8">
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Distribución por Tipo de Contrato</h3>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <?php foreach($distribucion_contratos as $contrato): 
                                $clase_contrato = strtolower(str_replace(' ', '-', $contrato['tipo_contrato']));
                                $clase_contrato = str_replace(['tiempo-', 'por-'], '', $clase_contrato);
                            ?>
                            <div class="text-center p-3 bg-gray-50 rounded-lg">
                                <span class="contrato-badge contrato-<?php echo $clase_contrato; ?> mb-2">
                                    <?php echo $contrato['tipo_contrato']; ?>
                                </span>
                                <p class="text-2xl font-bold text-gray-900"><?php echo $contrato['cantidad']; ?></p>
                                <p class="text-sm text-gray-600">empleados</p>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Listado de Empleados -->
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <?php if(count($empleados) > 0): ?>
                        <!-- Tabla para pantallas grandes -->
                        <div class="hidden md:block overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Empleado
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Empresa
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Información Laboral
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Salario
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Estatus
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Acciones
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach($empleados as $empleado): 
                                        $clase_contrato = strtolower(str_replace(' ', '-', $empleado['tipo_contrato']));
                                        $clase_contrato = str_replace(['tiempo-', 'por-'], '', $clase_contrato);
                                    ?>
                                    <tr class="table-row hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-10 w-10 bg-blue-100 rounded-full flex items-center justify-center">
                                                    <i class="fas fa-user text-blue-600"></i>
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-gray-900">
                                                        <?php echo htmlspecialchars($empleado['nombre'] . ' ' . $empleado['apellidos']); ?>
                                                    </div>
                                                    <div class="text-sm text-gray-500">
                                                        <?php if($empleado['fecha_nacimiento']): ?>
                                                        <?php 
                                                            $fecha_nac = new DateTime($empleado['fecha_nacimiento']);
                                                            $hoy = new DateTime();
                                                            $edad = $hoy->diff($fecha_nac)->y;
                                                            echo $edad . ' años';
                                                        ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($empleado['empresa_nombre']); ?></div>
                                            <div class="text-sm text-gray-500">
                                                <?php echo htmlspecialchars($empleado['departamento'] ?? 'Sin departamento'); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="space-y-1">
                                                <div class="text-sm">
                                                    <span class="font-medium">Puesto:</span>
                                                    <?php echo htmlspecialchars($empleado['puesto'] ?? 'No especificado'); ?>
                                                </div>
                                                <div class="text-sm">
                                                    <span class="contrato-badge contrato-<?php echo $clase_contrato; ?>">
                                                        <?php echo $empleado['tipo_contrato']; ?>
                                                    </span>
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    <i class="fas fa-calendar-alt mr-1"></i>
                                                    Ingreso: <?php echo date('d/m/Y', strtotime($empleado['fecha_ingreso'])); ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900 salario-diario">
                                                $<?php echo number_format($empleado['salario_diario'], 2); ?>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                <?php echo $empleado['periodicidad_pago']; ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="status-badge <?php echo $empleado['activo'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                <i class="fas fa-circle text-xs mr-1"></i>
                                                <?php echo $empleado['activo'] ? 'Activo' : 'Inactivo'; ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="flex space-x-3">
                                                <!-- AGREGA ESTE BOTÓN DE VER -->
                                                <a href="ver.php?id=<?php echo $empleado['id']; ?>" 
                                                class="text-blue-600 hover:text-blue-900" 
                                                title="Ver detalles">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                
                                                <a href="editar.php?id=<?php echo $empleado['id']; ?>" 
                                                class="text-green-600 hover:text-green-900" 
                                                title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <?php if($empleado['activo']): ?>
                                                <a href="#" 
                                                onclick="cambiarEstatus(<?php echo $empleado['id']; ?>, 0)"
                                                class="text-yellow-600 hover:text-yellow-900"
                                                title="Desactivar">
                                                    <i class="fas fa-ban"></i>
                                                </a>
                                                <?php else: ?>
                                                <a href="#" 
                                                onclick="cambiarEstatus(<?php echo $empleado['id']; ?>, 1)"
                                                class="text-green-600 hover:text-green-900"
                                                title="Activar">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Cards para pantallas pequeñas -->
                        <div class="md:hidden">
                            <div class="p-4 space-y-4">
                                <?php foreach($empleados as $empleado): 
                                    $clase_contrato = strtolower(str_replace(' ', '-', $empleado['tipo_contrato']));
                                    $clase_contrato = str_replace(['tiempo-', 'por-'], '', $clase_contrato);
                                ?>
                                <div class="empleado-card bg-white border border-gray-200 rounded-lg p-4">
                                    <div class="flex justify-between items-start mb-3">
                                        <div class="flex items-center">
                                            <div class="bg-blue-100 p-2 rounded-full mr-3">
                                                <i class="fas fa-user text-blue-600"></i>
                                            </div>
                                            <div>
                                                <h3 class="font-medium text-gray-900">
                                                    <?php echo htmlspecialchars($empleado['nombre'] . ' ' . $empleado['apellidos']); ?>
                                                </h3>
                                                <span class="status-badge <?php echo $empleado['activo'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?> text-xs">
                                                    <?php echo $empleado['activo'] ? 'Activo' : 'Inactivo'; ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="flex space-x-2">
                                            <a href="editar.php?id=<?php echo $empleado['id']; ?>" 
                                               class="text-green-600 hover:text-green-900">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        </div>
                                    </div>
                                    
                                    <div class="grid grid-cols-2 gap-4 text-sm mb-3">
                                        <div>
                                            <p class="text-gray-600">Empresa</p>
                                            <p class="font-medium"><?php echo htmlspecialchars($empleado['empresa_nombre']); ?></p>
                                        </div>
                                        <div>
                                            <p class="text-gray-600">Puesto</p>
                                            <p class="font-medium"><?php echo htmlspecialchars($empleado['puesto'] ?? 'No especificado'); ?></p>
                                        </div>
                                        <div>
                                            <p class="text-gray-600">Departamento</p>
                                            <p class="font-medium"><?php echo htmlspecialchars($empleado['departamento'] ?? 'No especificado'); ?></p>
                                        </div>
                                        <div>
                                            <p class="text-gray-600">Salario Diario</p>
                                            <p class="font-medium salario-diario">$<?php echo number_format($empleado['salario_diario'], 2); ?></p>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <span class="contrato-badge contrato-<?php echo $clase_contrato; ?>">
                                            <?php echo $empleado['tipo_contrato']; ?>
                                        </span>
                                        <span class="ml-2 text-sm text-gray-600">
                                            <?php echo $empleado['periodicidad_pago']; ?>
                                        </span>
                                    </div>
                                    
                                    <div class="pt-3 border-t">
                                        <div class="flex justify-between items-center">
                                            <div class="text-sm text-gray-600">
                                                <i class="fas fa-calendar-alt mr-1"></i>
                                                Ingreso: <?php echo date('d/m/Y', strtotime($empleado['fecha_ingreso'])); ?>
                                            </div>
                                            <div class="flex space-x-2">
                                                <?php if($empleado['activo']): ?>
                                                <a href="#" 
                                                   onclick="cambiarEstatus(<?php echo $empleado['id']; ?>, 0)"
                                                   class="text-sm text-yellow-600 hover:text-yellow-800">
                                                    <i class="fas fa-ban mr-1"></i> Desactivar
                                                </a>
                                                <?php else: ?>
                                                <a href="#" 
                                                   onclick="cambiarEstatus(<?php echo $empleado['id']; ?>, 1)"
                                                   class="text-sm text-green-600 hover:text-green-800">
                                                    <i class="fas fa-check mr-1"></i> Activar
                                                </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Paginación -->
                        <?php if($total_paginas > 1): ?>
                        <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                            <div class="flex flex-col md:flex-row justify-between items-center">
                                <div class="mb-4 md:mb-0">
                                    <p class="text-sm text-gray-700">
                                        Mostrando 
                                        <span class="font-medium"><?php echo ($offset + 1); ?></span> 
                                        a 
                                        <span class="font-medium"><?php echo min($offset + $por_pagina, $total_empleados); ?></span> 
                                        de 
                                        <span class="font-medium"><?php echo $total_empleados; ?></span> 
                                        empleados
                                    </p>
                                </div>
                                <div class="flex space-x-1">
                                    <?php if($pagina > 1): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina - 1])); ?>" 
                                       class="pagination-link">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php 
                                    $inicio = max(1, $pagina - 2);
                                    $fin = min($total_paginas, $pagina + 2);
                                    
                                    if($inicio > 1) {
                                        echo '<a href="?' . http_build_query(array_merge($_GET, ['pagina' => 1])) . '" class="pagination-link">1</a>';
                                        if($inicio > 2) echo '<span class="px-2 py-1">...</span>';
                                    }
                                    
                                    for($i = $inicio; $i <= $fin; $i++): 
                                    ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $i])); ?>" 
                                       class="pagination-link <?php echo $i == $pagina ? 'active' : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                    <?php endfor; ?>
                                    
                                    <?php 
                                    if($fin < $total_paginas) {
                                        if($fin < $total_paginas - 1) echo '<span class="px-2 py-1">...</span>';
                                        echo '<a href="?' . http_build_query(array_merge($_GET, ['pagina' => $total_paginas])) . '" class="pagination-link">' . $total_paginas . '</a>';
                                    }
                                    ?>
                                    
                                    <?php if($pagina < $total_paginas): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina + 1])); ?>" 
                                       class="pagination-link">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                    <?php else: ?>
                        <!-- Mensaje cuando no hay empleados -->
                        <div class="text-center py-12">
                            <div class="text-gray-400 mb-4">
                                <i class="fas fa-users text-5xl"></i>
                            </div>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">No hay empleados registrados</h3>
                            <p class="text-gray-600 mb-6">Comienza agregando el primer empleado a tu empresa</p>
                            <a href="agregar.php" 
                               class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                                <i class="fas fa-user-plus mr-2"></i>
                                Agregar Primer Empleado
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        function cambiarEstatus(empleadoId, nuevoEstatus) {
            const mensaje = nuevoEstatus == 1 
                ? '¿Activar este empleado?' 
                : '¿Desactivar este empleado?';
            
            if(confirm(mensaje)) {
                const formData = new FormData();
                formData.append('empleado_id', empleadoId);
                formData.append('nuevo_estatus', nuevoEstatus);
                
                fetch('cambiar_estatus.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        alert(data.message);
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al cambiar el estatus');
                });
            }
        }
        
        // Hover effects para tarjetas
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.empleado-card');
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px)';
                    this.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.1)';
                });
                card.addEventListener('mouseleave', function() {
                    this.style.transform = '';
                    this.style.boxShadow = '';
                });
            });
        });
    </script>
</body>
</html>