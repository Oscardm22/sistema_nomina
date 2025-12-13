            </div> <!-- Cierre del main content container -->
            
            <!-- Footer -->
            <footer class="bg-white border-t mt-8 py-6">
                <div class="container mx-auto px-4">
                    <div class="flex flex-col md:flex-row justify-between items-center">
                        <p class="text-gray-600">
                            Sistema de Nómina v1.0 &copy; <?php echo date('Y'); ?> - Para Contadores
                        </p>
                        <div class="flex space-x-4 mt-2 md:mt-0">
                            <a href="#" class="text-gray-500 hover:text-primary-600">
                                <i class="fab fa-github"></i>
                            </a>
                            <a href="#" class="text-gray-500 hover:text-primary-600">
                                <i class="fas fa-question-circle"></i> Ayuda
                            </a>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script>
        // Toggle sidebar en móviles
        document.getElementById('sidebarToggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
        });
        
        // Cerrar sidebar al hacer clic fuera en móviles
        document.addEventListener('click', function(event) {
            const sidebar = document.querySelector('.sidebar');
            const toggleBtn = document.getElementById('sidebarToggle');
            
            if (window.innerWidth < 768 && 
                !sidebar.contains(event.target) && 
                !toggleBtn.contains(event.target)) {
                sidebar.classList.remove('active');
            }
        });
    </script>
</body>
</html>