<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'Sistema de Nómina'; ?></title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS (opcional) -->
    <style>
        .sidebar {
            transition: all 0.3s;
        }
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.active {
                transform: translateX(0);
            }
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-800">
    <!-- Navbar -->
    <nav class="bg-gradient-to-r from-primary-700 to-primary-900 text-white shadow-lg">
        <div class="container mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <!-- Logo -->
                <a href="<?php echo BASE_URL; ?>dashboard.php" class="flex items-center space-x-3 text-xl font-bold">
                    <i class="fas fa-calculator text-2xl"></i>
                    <span>NominaContadores</span>
                </a>
                
                <!-- User Menu -->
                <?php if(estaLogueado()): ?>
                <div class="relative group">
                    <button class="flex items-center space-x-2 bg-white/10 hover:bg-white/20 px-4 py-2 rounded-lg transition">
                        <i class="fas fa-user-circle"></i>
                        <span><?php echo $_SESSION['usuario_nombre'] ?? 'Usuario'; ?></span>
                        <i class="fas fa-chevron-down text-sm"></i>
                    </button>
                    
                    <div class="absolute right-0 mt-2 w-48 bg-white text-gray-800 rounded-lg shadow-xl py-2 hidden group-hover:block z-50">
                        <a href="<?php echo BASE_URL; ?>modulos/perfil/" class="block px-4 py-2 hover:bg-gray-100">
                            <i class="fas fa-user mr-2"></i> Mi Perfil
                        </a>
                        <a href="<?php echo BASE_URL; ?>modulos/auth/logout.php" class="block px-4 py-2 hover:bg-gray-100 text-red-600">
                            <i class="fas fa-sign-out-alt mr-2"></i> Cerrar Sesión
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    
    <!-- Main Layout -->
    <div class="flex">
        <!-- Sidebar -->
        <?php if(estaLogueado()): ?>
        <div class="sidebar w-64 bg-white shadow-lg min-h-screen fixed md:relative z-40">
            <div class="p-6">
                <h3 class="font-bold text-lg text-gray-700 mb-4">Menú Principal</h3>
                <ul class="space-y-2">
                    <li>
                        <a href="<?php echo BASE_URL; ?>dashboard.php" 
                           class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-primary-50 hover:text-primary-700 transition">
                            <i class="fas fa-tachometer-alt w-6"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo BASE_URL; ?>modulos/empresas/" 
                           class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-primary-50 hover:text-primary-700 transition">
                            <i class="fas fa-building w-6"></i>
                            <span>Empresas</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo BASE_URL; ?>modulos/empleados/" 
                           class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-primary-50 hover:text-primary-700 transition">
                            <i class="fas fa-users w-6"></i>
                            <span>Empleados</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo BASE_URL; ?>modulos/nominas/" 
                           class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-primary-50 hover:text-primary-700 transition">
                            <i class="fas fa-file-invoice-dollar w-6"></i>
                            <span>Nóminas</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo BASE_URL; ?>modulos/reportes/" 
                           class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-primary-50 hover:text-primary-700 transition">
                            <i class="fas fa-chart-bar w-6"></i>
                            <span>Reportes</span>
                        </a>
                    </li>
                    <li class="pt-6 border-t">
                        <a href="<?php echo BASE_URL; ?>modulos/configuracion/" 
                           class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-gray-100 transition">
                            <i class="fas fa-cog w-6"></i>
                            <span>Configuración</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
        
        <!-- Mobile menu button -->
        <button id="sidebarToggle" class="md:hidden fixed top-4 left-4 z-50 bg-primary-600 text-white p-2 rounded-lg shadow-lg">
            <i class="fas fa-bars"></i>
        </button>
        <?php endif; ?>
        
        <!-- Main Content -->
        <div class="flex-1">
            <div class="container mx-auto px-4 py-8">