<?php
// Determinar la página actual basada en la URL si no está definida
if (!isset($paginaActual)) {
    $urlActual = $_SERVER['REQUEST_URI'];
    $paginaActual = 'dashboard'; // Por defecto

    if (strpos($urlActual, '/empresas/') !== false) {
        $paginaActual = 'empresas';
    } elseif (strpos($urlActual, '/empleados/') !== false) {
        $paginaActual = 'empleados';
    } elseif (strpos($urlActual, '/nominas/') !== false) {
        $paginaActual = 'nominas';
    } elseif (strpos($urlActual, '/reportes/') !== false) {
        $paginaActual = 'reportes';
    }
}

// Función para determinar si un ítem del menú está activo
function menuActivo($pagina, $item) {
    return $pagina === $item ? 'bg-blue-50 text-blue-700' : 'hover:bg-gray-100 transition';
}

// Obtener estadísticas si no están definidas (para los contadores)
if (!isset($stats)) {
    $stats = [
        'empresas' => 0,
        'empleados' => 0,
        'nominas_pendientes' => 0
    ];
}

// Asegurar que todas las claves existan
$stats['empresas'] = $stats['empresas'] ?? 0;
$stats['empleados'] = $stats['empleados'] ?? 0;
$stats['nominas_pendientes'] = $stats['nominas_pendientes'] ?? 0;
?>

<div class="w-64 bg-white shadow-lg min-h-screen">
    <div class="p-6">
        <div class="mb-8">
            <h3 class="font-bold text-gray-700 mb-2">Menú Principal</h3>
            <div class="text-sm text-gray-500"><?php echo $_SESSION['usuario_email'] ?? 'Usuario'; ?></div>
        </div>
        
        <ul class="space-y-2">
            <!-- Dashboard -->
            <li>
                <a href="<?php echo BASE_URL; ?>dashboard.php" 
                   class="flex items-center space-x-3 px-4 py-3 rounded-lg <?php echo menuActivo($paginaActual, 'dashboard'); ?>">
                    <i class="fas fa-tachometer-alt w-6"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            
            <!-- Empresas -->
            <li>
                <a href="<?php echo BASE_URL; ?>modules/empresas/" 
                   class="flex items-center space-x-3 px-4 py-3 rounded-lg <?php echo menuActivo($paginaActual, 'empresas'); ?>">
                    <i class="fas fa-building w-6"></i>
                    <span>Empresas</span>
                    <?php if($stats['empresas'] > 0): ?>
                    <span class="ml-auto bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full">
                        <?php echo $stats['empresas']; ?>
                    </span>
                    <?php endif; ?>
                </a>
            </li>
            
            <!-- Empleados -->
            <li>
                <a href="<?php echo BASE_URL; ?>modules/empleados/" 
                   class="flex items-center space-x-3 px-4 py-3 rounded-lg <?php echo menuActivo($paginaActual, 'empleados'); ?>">
                    <i class="fas fa-users w-6"></i>
                    <span>Empleados</span>
                    <?php if($stats['empleados'] > 0): ?>
                    <span class="ml-auto bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full">
                        <?php echo $stats['empleados']; ?>
                    </span>
                    <?php endif; ?>
                </a>
            </li>
            
            <!-- Nóminas -->
            <li>
                <a href="<?php echo BASE_URL; ?>modules/nominas/" 
                   class="flex items-center space-x-3 px-4 py-3 rounded-lg <?php echo menuActivo($paginaActual, 'nominas'); ?>">
                    <i class="fas fa-file-invoice-dollar w-6"></i>
                    <span>Nóminas</span>
                    <?php if(isset($stats['nominas_pendientes']) && $stats['nominas_pendientes'] > 0): ?>
                    <span class="ml-auto bg-yellow-100 text-yellow-800 text-xs px-2 py-1 rounded-full">
                        <?php echo $stats['nominas_pendientes']; ?>
                    </span>
                    <?php endif; ?>
                </a>
            </li>
            
            <!-- Reportes -->
            <li>
                <a href="<?php echo BASE_URL; ?>modules/reportes/" 
                   class="flex items-center space-x-3 px-4 py-3 rounded-lg <?php echo menuActivo($paginaActual, 'reportes'); ?>">
                    <i class="fas fa-chart-bar w-6"></i>
                    <span>Reportes</span>
                </a>
            </li>
        </ul>
        
        <!-- Quick Stats Sidebar (solo en dashboard o si se especifica) -->
        <?php if($paginaActual === 'dashboard'): ?>
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
        <?php endif; ?>
    </div>
</div>

<style>
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