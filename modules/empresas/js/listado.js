// Funciones para el listado de empresas

function cambiarEstatus(empresaId, nuevoEstatus) {
    const mensaje = nuevoEstatus == 1 
        ? '¿Activar esta empresa?' 
        : '¿Desactivar esta empresa?';
    
    if(confirm(mensaje)) {
        const formData = new FormData();
        formData.append('empresa_id', empresaId);
        formData.append('nuevo_estatus', nuevoEstatus);
        
        fetch('cambiar_estatus.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Error en la red');
            }
            return response.json();
        })
        .then(data => {
            if(data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert('Error: ' + (data.message || 'Error desconocido'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al cambiar el estatus. Por favor, intenta nuevamente.');
        });
    }
}

// Inicializar cuando el DOM esté cargado
document.addEventListener('DOMContentLoaded', function() {
    // Añadir efectos hover a las filas de la tabla
    const tableRows = document.querySelectorAll('.table-row');
    tableRows.forEach(row => {
        row.addEventListener('mouseenter', function() {
            this.style.backgroundColor = '#f9fafb';
        });
        row.addEventListener('mouseleave', function() {
            this.style.backgroundColor = '';
        });
    });
    
    // Añadir efectos a las tarjetas
    const empresaCards = document.querySelectorAll('.empresa-card');
    empresaCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
            this.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.1)';
        });
        card.addEventListener('mouseleave', function() {
            this.style.transform = '';
            this.style.boxShadow = '';
        });
    });
    
    // Mejorar experiencia del formulario de búsqueda
    const searchForm = document.querySelector('form');
    if (searchForm) {
        searchForm.addEventListener('submit', function(e) {
            // Opcional: añadir animación de carga
            const submitButton = this.querySelector('button[type="submit"]');
            if (submitButton) {
                const originalText = submitButton.innerHTML;
                submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Buscando...';
                submitButton.disabled = true;
                
                setTimeout(() => {
                    submitButton.innerHTML = originalText;
                    submitButton.disabled = false;
                }, 1000);
            }
        });
    }
    
    // Añadir tooltips a los botones de acción
    const actionButtons = document.querySelectorAll('a[title]');
    actionButtons.forEach(button => {
        button.addEventListener('mouseenter', function() {
            const title = this.getAttribute('title');
            if (title) {
                // Crear tooltip si no existe
                if (!this.querySelector('.custom-tooltip')) {
                    const tooltip = document.createElement('span');
                    tooltip.className = 'custom-tooltip absolute bg-gray-900 text-white text-xs rounded py-1 px-2 -mt-8 -ml-2';
                    tooltip.textContent = title;
                    this.style.position = 'relative';
                    this.appendChild(tooltip);
                }
            }
        });
        
        button.addEventListener('mouseleave', function() {
            const tooltip = this.querySelector('.custom-tooltip');
            if (tooltip) {
                tooltip.remove();
            }
        });
    });
});