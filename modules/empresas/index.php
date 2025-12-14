<?php
require_once '../../config/database.php';
requerirLogin();

$conn = conectarDB();
$usuario_id = $_SESSION['usuario_id'];

// Manejar búsqueda y filtros
$busqueda = isset($_GET['busqueda']) ? limpiar($_GET['busqueda']) : '';
$estatus = isset($_GET['estatus']) ? limpiar($_GET['estatus']) : 'activas';
$pagina = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$por_pagina = 10;
$offset = ($pagina - 1) * $por_pagina;

// Construir consulta base
$sql = "SELECT * FROM empresas WHERE usuario_id = ?";
$params = [$usuario_id];

// Aplicar filtros
if (!empty($busqueda)) {
    $sql .= " AND (nombre LIKE ? OR rif LIKE ? OR email LIKE ?)";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
}

if ($estatus === 'activas') {
    $sql .= " AND activa = 1";
} elseif ($estatus === 'inactivas') {
    $sql .= " AND activa = 0";
}

// Contar total de registros
$sql_count = str_replace('SELECT *', 'SELECT COUNT(*) as total', $sql);
$stmt_count = $conn->prepare($sql_count);
$stmt_count->execute($params);
$total_empresas = $stmt_count->fetch()['total'];
$total_paginas = ceil($total_empresas / $por_pagina);

// Obtener empresas con paginación
$sql .= " ORDER BY fecha_alta DESC LIMIT ? OFFSET ?";
$params[] = $por_pagina;
$params[] = $offset;

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$empresas = $stmt->fetchAll();

$pageTitle = "Gestión de Empresas";
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
    
    <style>
        .empresa-card {
            transition: all 0.3s ease;
        }
        .empresa-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
        }
        .table-row:hover {
            background-color: #f9fafb;
        }
        .pagination-link {
            padding: 0.5rem 0.75rem;
            border-radius: 0.375rem;
            transition: all 0.2s;
        }
        .pagination-link:hover {
            background-color: #f3f4f6;
        }
        .pagination-link.active {
            background-color: #3b82f6;
            color: white;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navbar -->
    <?php 
    // Crear navbar temporal si no existe el partial
    if(!file_exists('../../partials/navbar.php')):
    ?>
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
    <?php else: ?>
        <?php include '../../partials/navbar.php'; ?>
    <?php endif; ?>

    <!-- Main Layout -->
    <div class="flex">
        <!-- Sidebar -->
        <?php 
        // Crear sidebar temporal si no existe el partial
        if(!file_exists('../../partials/sidebar.php')):
        ?>
        <div class="w-64 bg-white shadow-lg min-h-screen">
            <div class="p-6">
                <div class="mb-8">
                    <h3 class="font-bold text-gray-700 mb-2">Menú Principal</h3>
                    <div class="text-sm text-gray-500"><?php echo $_SESSION['usuario_email'] ?? 'Usuario'; ?></div>
                </div>
                <ul class="space-y-2">
                    <li><a href="../../dashboard.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg bg-blue-50 text-blue-700"><i class="fas fa-tachometer-alt w-6"></i><span>Dashboard</span></a></li>
                    <li><a href="index.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-gray-100 transition"><i class="fas fa-building w-6"></i><span>Empresas</span></a></li>
                    <li><a href="../empleados/" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-gray-100 transition"><i class="fas fa-users w-6"></i><span>Empleados</span></a></li>
                    <li><a href="../nominas/" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-gray-100 transition"><i class="fas fa-file-invoice-dollar w-6"></i><span>Nóminas</span></a></li>
                </ul>
            </div>
        </div>
        <?php else: ?>
            <?php include '../../partials/sidebar.php'; ?>
        <?php endif; ?>

        <!-- Main Content -->
        <div class="flex-1">
            <div class="container mx-auto px-4 py-8">
                <!-- Header y Acciones -->
                <div class="mb-8">
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900">Empresas</h1>
                            <p class="text-gray-600 mt-2">Gestiona las empresas de tus clientes</p>
                        </div>
                        <div class="mt-4 md:mt-0">
                            <a href="agregar.php" 
                               class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                                <i class="fas fa-plus mr-2"></i>
                                Nueva Empresa
                            </a>
                        </div>
                    </div>

                    <!-- Filtros y Búsqueda -->
                    <div class="bg-white rounded-lg shadow p-4 mb-6">
                        <form method="GET" action="" class="flex flex-col md:flex-row gap-4">
                            <!-- Búsqueda -->
                            <div class="flex-1">
                                <div class="relative">
                                    <input type="text" 
                                           name="busqueda" 
                                           value="<?php echo htmlspecialchars($busqueda); ?>"
                                           placeholder="Buscar por nombre, RIF o email..."
                                           class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                                </div>
                            </div>
                            
                            <!-- Filtro de estatus -->
                            <div>
                                <select name="estatus" 
                                        class="w-full md:w-auto px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="activas" <?php echo $estatus === 'activas' ? 'selected' : ''; ?>>Activas</option>
                                    <option value="inactivas" <?php echo $estatus === 'inactivas' ? 'selected' : ''; ?>>Inactivas</option>
                                    <option value="todas" <?php echo $estatus === 'todas' ? 'selected' : ''; ?>>Todas</option>
                                </select>
                            </div>
                            
                            <!-- Botones -->
                            <div class="flex gap-2">
                                <button type="submit" 
                                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                                    <i class="fas fa-filter mr-2"></i>Filtrar
                                </button>
                                <?php if($busqueda || $estatus !== 'activas'): ?>
                                <a href="index.php" 
                                   class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                                    <i class="fas fa-times mr-2"></i>Limpiar
                                </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Contador y Estadísticas -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="bg-blue-100 p-3 rounded-lg mr-4">
                                <i class="fas fa-building text-blue-600 text-xl"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Total Empresas</p>
                                <p class="text-2xl font-bold text-gray-900"><?php echo $total_empresas; ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="bg-green-100 p-3 rounded-lg mr-4">
                                <i class="fas fa-check-circle text-green-600 text-xl"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Empresas Activas</p>
                                <p class="text-2xl font-bold text-gray-900">
                                    <?php 
                                    $stmt_activas = $conn->prepare("SELECT COUNT(*) as activas FROM empresas WHERE usuario_id = ? AND activa = 1");
                                    $stmt_activas->execute([$usuario_id]);
                                    echo $stmt_activas->fetch()['activas'];
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="bg-purple-100 p-3 rounded-lg mr-4">
                                <i class="fas fa-chart-line text-purple-600 text-xl"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Este Mes</p>
                                <p class="text-2xl font-bold text-gray-900">
                                    <?php 
                                    $stmt_mes = $conn->prepare("SELECT COUNT(*) as mes FROM empresas WHERE usuario_id = ? AND MONTH(fecha_alta) = MONTH(CURRENT_DATE())");
                                    $stmt_mes->execute([$usuario_id]);
                                    echo $stmt_mes->fetch()['mes'];
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Listado de Empresas -->
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <?php if(count($empresas) > 0): ?>
                        <!-- Tabla para pantallas grandes -->
                        <div class="hidden md:block overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Empresa
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Contacto
                                        </th>
                                        <th scope="col" class="px 6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            RIF
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Fecha Alta
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
                                    <?php foreach($empresas as $empresa): ?>
                                    <tr class="table-row hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-10 w-10 bg-blue-100 rounded-lg flex items-center justify-center">
                                                    <i class="fas fa-building text-blue-600"></i>
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-gray-900">
                                                        <?php echo htmlspecialchars($empresa['nombre']); ?>
                                                    </div>
                                                    <div class="text-sm text-gray-500">
                                                        <?php echo htmlspecialchars($empresa['direccion'] ?? 'Sin dirección'); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($empresa['email'] ?? 'No especificado'); ?></div>
                                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($empresa['telefono'] ?? 'No especificado'); ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo htmlspecialchars($empresa['rif']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo date('d/m/Y', strtotime($empresa['fecha_alta'])); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="status-badge <?php echo $empresa['activa'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                <i class="fas fa-circle text-xs mr-1"></i>
                                                <?php echo $empresa['activa'] ? 'Activa' : 'Inactiva'; ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="flex space-x-2">
                                                <a href="editar.php?id=<?php echo $empresa['id']; ?>" 
                                                   class="text-blue-600 hover:text-blue-900" 
                                                   title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <?php if($empresa['activa']): ?>
                                                <a href="#" 
                                                   onclick="cambiarEstatus(<?php echo $empresa['id']; ?>, 0)"
                                                   class="text-yellow-600 hover:text-yellow-900"
                                                   title="Desactivar">
                                                    <i class="fas fa-ban"></i>
                                                </a>
                                                <?php else: ?>
                                                <a href="#" 
                                                   onclick="cambiarEstatus(<?php echo $empresa['id']; ?>, 1)"
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
                                <?php foreach($empresas as $empresa): ?>
                                <div class="empresa-card bg-white border border-gray-200 rounded-lg p-4">
                                    <div class="flex justify-between items-start mb-3">
                                        <div class="flex items-center">
                                            <div class="bg-blue-100 p-2 rounded-lg mr-3">
                                                <i class="fas fa-building text-blue-600"></i>
                                            </div>
                                            <div>
                                                <h3 class="font-medium text-gray-900">
                                                    <?php echo htmlspecialchars($empresa['nombre']); ?>
                                                </h3>
                                                <span class="status-badge <?php echo $empresa['activa'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?> text-xs">
                                                    <?php echo $empresa['activa'] ? 'Activa' : 'Inactiva'; ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="flex space-x-2">
                                            <a href="editar.php?id=<?php echo $empresa['id']; ?>" 
                                               class="text-blue-600 hover:text-blue-900">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        </div>
                                    </div>
                                    
                                    <div class="grid grid-cols-2 gap-4 text-sm">
                                        <div>
                                            <p class="text-gray-600">RIF</p>
                                            <p class="font-medium"><?php echo htmlspecialchars($empresa['rif']); ?></p>
                                        </div>
                                        <div>
                                            <p class="text-gray-600">Email</p>
                                            <p class="font-medium"><?php echo htmlspecialchars($empresa['email'] ?? 'No especificado'); ?></p>
                                        </div>
                                        <div>
                                            <p class="text-gray-600">Teléfono</p>
                                            <p class="font-medium"><?php echo htmlspecialchars($empresa['telefono'] ?? 'No especificado'); ?></p>
                                        </div>
                                        <div>
                                            <p class="text-gray-600">Fecha Alta</p>
                                            <p class="font-medium"><?php echo date('d/m/Y', strtotime($empresa['fecha_alta'])); ?></p>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-4 pt-3 border-t">
                                        <div class="flex justify-between">
                                            <?php if($empresa['activa']): ?>
                                            <a href="#" 
                                               onclick="cambiarEstatus(<?php echo $empresa['id']; ?>, 0)"
                                               class="text-sm text-yellow-600 hover:text-yellow-800">
                                                <i class="fas fa-ban mr-1"></i> Desactivar
                                            </a>
                                            <?php else: ?>
                                            <a href="#" 
                                               onclick="cambiarEstatus(<?php echo $empresa['id']; ?>, 1)"
                                               class="text-sm text-green-600 hover:text-green-800">
                                                <i class="fas fa-check mr-1"></i> Activar
                                            </a>
                                            <?php endif; ?>
                                            <a href="editar.php?id=<?php echo $empresa['id']; ?>" 
                                               class="text-sm text-blue-600 hover:text-blue-800">
                                                Editar <i class="fas fa-edit ml-1"></i>
                                            </a>
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
                                        <span class="font-medium"><?php echo min($offset + $por_pagina, $total_empresas); ?></span> 
                                        de 
                                        <span class="font-medium"><?php echo $total_empresas; ?></span> 
                                        empresas
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
                        <!-- Mensaje cuando no hay empresas -->
                        <div class="text-center py-12">
                            <div class="text-gray-400 mb-4">
                                <i class="fas fa-building text-5xl"></i>
                            </div>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">No hay empresas registradas</h3>
                            <p class="text-gray-600 mb-6">Comienza agregando la primera empresa de tu cliente</p>
                            <a href="agregar.php" 
                               class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                                <i class="fas fa-plus mr-2"></i>
                                Agregar Primera Empresa
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        function cambiarEstatus(empresaId, nuevoEstatus) {
            if(confirm(nuevoEstatus == 1 ? '¿Activar esta empresa?' : '¿Desactivar esta empresa?')) {
                const formData = new FormData();
                formData.append('empresa_id', empresaId);
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
    </script>
</body>
</html>