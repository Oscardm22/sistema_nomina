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

// Obtener información del empleado
$stmt = $conn->prepare("
    SELECT e.*, emp.nombre as empresa_nombre, emp.rif as empresa_rif
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

// Calcular salarios
$salario_semanal = $empleado['salario_diario'] * 7;
$salario_quincenal = $empleado['salario_diario'] * 15;
$salario_mensual = $empleado['salario_diario'] * 30;

// Obtener estadísticas de nóminas usando neto_pagar
$stats_nominas = [
    'total_nominas' => 0,
    'total_pagado' => 0,
    'primera_nomina' => null,
    'ultima_nomina' => null
];

try {
    // Verificar si la tabla nominas existe
    $stmt_check = $conn->query("SHOW TABLES LIKE 'nominas'");
    if ($stmt_check->rowCount() > 0) {
        // Usar neto_pagar como columna para el total
        $stmt_nominas = $conn->prepare("
            SELECT 
                COUNT(*) as total_nominas,
                COALESCE(SUM(neto_pagar), 0) as total_pagado,
                MIN(fecha_calculo) as primera_nomina,
                MAX(fecha_calculo) as ultima_nomina
            FROM nominas
            WHERE empleado_id = ? AND estatus IN ('Calculada', 'Pagada')
        ");
        
        $stmt_nominas->execute([$empleado_id]);
        $stats_nominas = $stmt_nominas->fetch();
        
        // Formatear fechas si existen
        if ($stats_nominas['primera_nomina']) {
            $stats_nominas['primera_nomina'] = date('d/m/Y', strtotime($stats_nominas['primera_nomina']));
        }
        if ($stats_nominas['ultima_nomina']) {
            $stats_nominas['ultima_nomina'] = date('d/m/Y', strtotime($stats_nominas['ultima_nomina']));
        }
    }
} catch (PDOException $e) {
    // Si hay error, no mostramos las estadísticas
    error_log("Error al obtener estadísticas de nóminas: " . $e->getMessage());
}

// Obtener últimas nóminas del empleado
$ultimas_nominas = [];
try {
    $stmt_ultimas = $conn->prepare("
        SELECT 
            n.id,
            n.periodo_id,
            n.dias_trabajados,
            n.total_percepciones,
            n.total_deducciones,
            n.neto_pagar,
            n.estatus,
            DATE_FORMAT(n.fecha_calculo, '%d/%m/%Y') as fecha_calculo_formatted
        FROM nominas n
        WHERE n.empleado_id = ?
        ORDER BY n.fecha_calculo DESC
        LIMIT 5
    ");
    
    $stmt_ultimas->execute([$empleado_id]);
    $ultimas_nominas = $stmt_ultimas->fetchAll();
} catch (PDOException $e) {
    error_log("Error al obtener últimas nóminas: " . $e->getMessage());
}

$paginaActual = 'empleados';
$pageTitle = "Detalles del Empleado";
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Sistema de Nómina</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        .status-borrador { background-color: #fef3c7; color: #92400e; }
        .status-calculada { background-color: #dbeafe; color: #1e40af; }
        .status-pagada { background-color: #d1fae5; color: #065f46; }
        .status-cancelada { background-color: #fee2e2; color: #991b1b; }
    </style>
</head>
<body class="bg-gray-50">
    <?php include '../../partials/navbar.php'; ?>

    <div class="flex">
        <?php include '../../partials/sidebar.php'; ?>

        <div class="flex-1">
            <div class="container mx-auto px-4 py-8">
                <!-- Header -->
                <div class="mb-8">
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900">Detalles del Empleado</h1>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <a href="index.php" class="px-3 py-1 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">
                                    <i class="fas fa-arrow-left mr-1"></i> Listado
                                </a>
                                <a href="editar.php?id=<?php echo $empleado['id']; ?>" class="px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700">
                                    <i class="fas fa-edit mr-1"></i> Editar
                                </a>
                                <span class="px-3 py-1 rounded <?php echo $empleado['activo'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                    <i class="fas fa-circle text-xs mr-1"></i>
                                    <?php echo $empleado['activo'] ? 'Activo' : 'Inactivo'; ?>
                                </span>
                            </div>
                        </div>
                        <div class="mt-4 md:mt-0">
                            <span class="text-sm text-gray-600">
                                <i class="fas fa-id-card mr-1"></i>ID: <?php echo $empleado['id']; ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Información Principal -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Columna Izquierda -->
                    <div class="lg:col-span-2 space-y-6">
                        <!-- Información Personal -->
                        <div class="bg-white rounded-lg shadow p-6">
                            <h2 class="text-xl font-semibold text-gray-900 mb-4 flex items-center">
                                <i class="fas fa-user text-blue-600 mr-2"></i>
                                Información Personal
                            </h2>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <p class="text-sm text-gray-500">Nombre Completo</p>
                                    <p class="text-lg font-medium"><?php echo htmlspecialchars($empleado['nombre'] . ' ' . $empleado['apellidos']); ?></p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500">Fecha de Nacimiento</p>
                                    <p class="font-medium">
                                        <?php echo $empleado['fecha_nacimiento'] ? date('d/m/Y', strtotime($empleado['fecha_nacimiento'])) : 'No especificada'; ?>
                                        <?php if($edad_texto): ?>
                                        <span class="text-gray-600 ml-2">(<?php echo $edad_texto; ?>)</span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500">Empresa</p>
                                    <p class="font-medium"><?php echo htmlspecialchars($empleado['empresa_nombre']); ?></p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500">Antigüedad</p>
                                    <p class="font-medium"><?php echo $antiguedad_texto; ?></p>
                                </div>
                            </div>
                        </div>

                        <!-- Información Laboral -->
                        <div class="bg-white rounded-lg shadow p-6">
                            <h2 class="text-xl font-semibold text-gray-900 mb-4 flex items-center">
                                <i class="fas fa-briefcase text-blue-600 mr-2"></i>
                                Información Laboral
                            </h2>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="space-y-3">
                                    <div>
                                        <p class="text-sm text-gray-500">Puesto</p>
                                        <p class="font-medium"><?php echo htmlspecialchars($empleado['puesto']); ?></p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500">Departamento</p>
                                        <p class="font-medium"><?php echo htmlspecialchars($empleado['departamento']); ?></p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500">Fecha de Ingreso</p>
                                        <p class="font-medium"><?php echo date('d/m/Y', strtotime($empleado['fecha_ingreso'])); ?></p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500">Salario Diario</p>
                                        <p class="font-medium text-green-600">$<?php echo number_format($empleado['salario_diario'], 2); ?></p>
                                    </div>
                                </div>
                                <div class="space-y-3">
                                    <div>
                                        <p class="text-sm text-gray-500">Tipo de Contrato</p>
                                        <p class="font-medium"><?php echo $empleado['tipo_contrato']; ?></p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500">Régimen Contratación</p>
                                        <p class="font-medium"><?php echo $empleado['regimen_contratacion']; ?></p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500">Tipo de Jornada</p>
                                        <p class="font-medium"><?php echo $empleado['tipo_jornada']; ?></p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500">Periodicidad de Pago</p>
                                        <p class="font-medium"><?php echo $empleado['periodicidad_pago']; ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Últimas Nóminas -->
                        <?php if(count($ultimas_nominas) > 0): ?>
                        <div class="bg-white rounded-lg shadow p-6">
                            <h2 class="text-xl font-semibold text-gray-900 mb-4 flex items-center">
                                <i class="fas fa-file-invoice-dollar text-blue-600 mr-2"></i>
                                Últimas Nóminas
                            </h2>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Periodo</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Días Trab.</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Percepciones</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Deducciones</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Neto a Pagar</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Estatus</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach($ultimas_nominas as $nomina): ?>
                                        <tr>
                                            <td class="px-4 py-2 text-sm">#<?php echo $nomina['periodo_id']; ?></td>
                                            <td class="px-4 py-2 text-sm"><?php echo $nomina['dias_trabajados']; ?></td>
                                            <td class="px-4 py-2 text-sm">$<?php echo number_format($nomina['total_percepciones'], 2); ?></td>
                                            <td class="px-4 py-2 text-sm">$<?php echo number_format($nomina['total_deducciones'], 2); ?></td>
                                            <td class="px-4 py-2 text-sm font-bold text-green-700">$<?php echo number_format($nomina['neto_pagar'], 2); ?></td>
                                            <td class="px-4 py-2 text-sm">
                                                <span class="status-badge status-<?php echo strtolower($nomina['estatus']); ?>">
                                                    <?php echo $nomina['estatus']; ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-2 text-sm"><?php echo $nomina['fecha_calculo_formatted']; ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-4">
                                <a href="../nominas/?empleado_id=<?php echo $empleado['id']; ?>" 
                                   class="text-blue-600 hover:text-blue-800 text-sm">
                                    <i class="fas fa-list mr-1"></i> Ver todas las nóminas
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Información Bancaria -->
                        <?php if($empleado['banco'] || $empleado['cuenta_bancaria']): ?>
                        <div class="bg-white rounded-lg shadow p-6">
                            <h2 class="text-xl font-semibold text-gray-900 mb-4 flex items-center">
                                <i class="fas fa-university text-blue-600 mr-2"></i>
                                Información Bancaria
                            </h2>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <?php if($empleado['banco']): ?>
                                <div>
                                    <p class="text-sm text-gray-500">Banco</p>
                                    <p class="font-medium"><?php echo htmlspecialchars($empleado['banco']); ?></p>
                                </div>
                                <?php endif; ?>
                                <?php if($empleado['cuenta_bancaria']): ?>
                                <div>
                                    <p class="text-sm text-gray-500">Número de Cuenta</p>
                                    <p class="font-medium"><?php echo htmlspecialchars($empleado['cuenta_bancaria']); ?></p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Columna Derecha -->
                    <div class="space-y-6">
                        <!-- Resumen Financiero -->
                        <div class="bg-white rounded-lg shadow p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-3">Resumen Financiero</h3>
                            <div class="space-y-4">
                                <!-- Salario Diario -->
                                <div class="text-center p-4 bg-green-50 rounded-lg">
                                    <p class="text-sm text-gray-600">Salario Diario</p>
                                    <p class="text-3xl font-bold text-green-700">
                                        $<?php echo number_format($empleado['salario_diario'], 2); ?>
                                    </p>
                                </div>
                                
                                <!-- Cálculos -->
                                <div class="grid grid-cols-2 gap-3">
                                    <div class="text-center p-3 bg-blue-50 rounded">
                                        <p class="text-sm text-gray-600">Semanal</p>
                                        <p class="font-bold text-blue-700">$<?php echo number_format($salario_semanal, 2); ?></p>
                                    </div>
                                    <div class="text-center p-3 bg-purple-50 rounded">
                                        <p class="text-sm text-gray-600">Quincenal</p>
                                        <p class="font-bold text-purple-700">$<?php echo number_format($salario_quincenal, 2); ?></p>
                                    </div>
                                    <div class="text-center p-3 bg-orange-50 rounded">
                                        <p class="text-sm text-gray-600">Mensual</p>
                                        <p class="font-bold text-orange-700">$<?php echo number_format($salario_mensual, 2); ?></p>
                                    </div>
                                    <div class="text-center p-3 bg-indigo-50 rounded">
                                        <p class="text-sm text-gray-600">Anual*</p>
                                        <p class="font-bold text-indigo-700">$<?php echo number_format($salario_mensual * 12, 2); ?></p>
                                    </div>
                                </div>
                                
                                <!-- Estimación de prestaciones -->
                                <div class="mt-4 pt-4 border-t">
                                    <p class="text-sm text-gray-600 mb-2">Estimación Prestaciones*</p>
                                    <div class="grid grid-cols-2 gap-2">
                                        <div class="text-center">
                                            <p class="text-xs font-medium">Vacaciones</p>
                                            <p class="text-sm">$<?php echo number_format($empleado['salario_diario'] * 15, 2); ?></p>
                                        </div>
                                        <div class="text-center">
                                            <p class="text-xs font-medium">Utilidades</p>
                                            <p class="text-sm">$<?php echo number_format($salario_mensual, 2); ?></p>
                                        </div>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-2">*Cálculos estimados</p>
                                </div>
                            </div>
                        </div>

                        <!-- Estadísticas de Nóminas -->
                        <?php if($stats_nominas['total_nominas'] > 0): ?>
                        <div class="bg-white rounded-lg shadow p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-3">
                                <i class="fas fa-chart-bar mr-2 text-blue-600"></i>
                                Estadísticas de Nóminas
                            </h3>
                            <div class="space-y-4">
                                <div class="text-center p-3 bg-blue-50 rounded-lg">
                                    <p class="text-2xl font-bold text-blue-700"><?php echo $stats_nominas['total_nominas']; ?></p>
                                    <p class="text-sm text-gray-600">Nóminas Procesadas</p>
                                </div>
                                
                                <?php if($stats_nominas['total_pagado'] > 0): ?>
                                <div class="text-center p-3 bg-green-50 rounded-lg">
                                    <p class="text-2xl font-bold text-green-700">$<?php echo number_format($stats_nominas['total_pagado'], 2); ?></p>
                                    <p class="text-sm text-gray-600">Total Pagado</p>
                                </div>
                                <?php endif; ?>
                                
                                <?php if($stats_nominas['primera_nomina']): ?>
                                <div>
                                    <p class="text-sm text-gray-600">Primera Nómina</p>
                                    <p class="font-medium"><?php echo $stats_nominas['primera_nomina']; ?></p>
                                </div>
                                <?php endif; ?>
                                
                                <?php if($stats_nominas['ultima_nomina']): ?>
                                <div>
                                    <p class="text-sm text-gray-600">Última Nómina</p>
                                    <p class="font-medium"><?php echo $stats_nominas['ultima_nomina']; ?></p>
                                </div>
                                <?php endif; ?>
                                
                                <?php if($stats_nominas['total_nominas'] > 0 && $stats_nominas['total_pagado'] > 0): ?>
                                <div>
                                    <p class="text-sm text-gray-600">Promedio por Nómina</p>
                                    <p class="font-medium text-purple-700">
                                        $<?php echo number_format($stats_nominas['total_pagado'] / $stats_nominas['total_nominas'], 2); ?>
                                    </p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Acciones Rápidas -->
                        <div class="bg-white rounded-lg shadow p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-3">
                                <i class="fas fa-bolt mr-2 text-blue-600"></i>
                                Acciones Rápidas
                            </h3>
                            <div class="space-y-3">
                                <a href="editar.php?id=<?php echo $empleado['id']; ?>" 
                                   class="block w-full text-center px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition">
                                    <i class="fas fa-edit mr-2"></i> Editar Empleado
                                </a>
                                
                                <a href="../nominas/calcular.php?empleado_id=<?php echo $empleado['id']; ?>" 
                                   class="block w-full text-center px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 transition">
                                    <i class="fas fa-calculator mr-2"></i> Calcular Nómina
                                </a>
                                
                                <?php if($empleado['activo']): ?>
                                <button onclick="cambiarEstatus(<?php echo $empleado['id']; ?>, 0)"
                                        class="w-full px-4 py-2 bg-yellow-600 text-white rounded hover:bg-yellow-700 transition">
                                    <i class="fas fa-ban mr-2"></i> Desactivar
                                </button>
                                <?php else: ?>
                                <button onclick="cambiarEstatus(<?php echo $empleado['id']; ?>, 1)"
                                        class="w-full px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 transition">
                                    <i class="fas fa-check mr-2"></i> Activar
                                </button>
                                <?php endif; ?>
                                
                                <a href="index.php" 
                                   class="block w-full text-center px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300 transition">
                                    <i class="fas fa-arrow-left mr-2"></i> Volver al Listado
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function cambiarEstatus(empleadoId, nuevoEstatus) {
            if(confirm(nuevoEstatus == 1 ? '¿Activar empleado?' : '¿Desactivar empleado?')) {
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
                    alert('Error al cambiar estatus');
                });
            }
        }
        
        // Estilos para los badges de estatus
        document.addEventListener('DOMContentLoaded', function() {
            // Agregar clases CSS para los badges
            const style = document.createElement('style');
            style.textContent = `
                .status-badge {
                    padding: 0.25rem 0.75rem;
                    border-radius: 9999px;
                    font-size: 0.75rem;
                    font-weight: 500;
                    display: inline-block;
                }
                .status-borrador { background-color: #fef3c7; color: #92400e; }
                .status-calculada { background-color: #dbeafe; color: #1e40af; }
                .status-pagada { background-color: #d1fae5; color: #065f46; }
                .status-cancelada { background-color: #fee2e2; color: #991b1b; }
            `;
            document.head.appendChild(style);
        });
    </script>
</body>
</html>