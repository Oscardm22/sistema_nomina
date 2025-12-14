<?php
?>
<nav class="bg-gradient-to-r from-blue-600 to-indigo-700 text-white shadow-lg">
    <div class="container mx-auto px-4">
        <div class="flex justify-between items-center py-4">
            <div class="flex items-center space-x-3">
                <a href="<?php echo BASE_URL; ?>dashboard.php" class="flex items-center space-x-3">
                    <i class="fas fa-calculator text-2xl"></i>
                    <span class="text-xl font-bold">NominaContadores</span>
                </a>
            </div>
            <div class="flex items-center space-x-4">
                <a href="<?php echo BASE_URL; ?>dashboard.php" class="hover:text-blue-200">
                    <i class="fas fa-home"></i> Dashboard
                </a>
                <a href="<?php echo BASE_URL; ?>modules/auth/logout.php" class="hover:text-blue-200">
                    <i class="fas fa-sign-out-alt"></i> Salir
                </a>
            </div>
        </div>
    </div>
</nav>