// Funciones para formulario de empresas

function formatRIF(input) {
    // Convertir a mayúsculas
    input.value = input.value.toUpperCase();
    
    // Remover cualquier carácter que no sea J, números o guión
    input.value = input.value.replace(/[^J0-9\-]/g, '');
    
    // Asegurar que empiece con J
    if (!input.value.startsWith('J')) {
        input.value = 'J' + input.value.replace('J', '');
    }
    
    // Insertar guión después de la J si no está presente
    if (input.value.length > 1 && input.value.charAt(1) !== '-') {
        input.value = input.value.charAt(0) + '-' + input.value.substring(1);
    }
    
    // Limitar a J + guión + 9 números
    let parts = input.value.split('-');
    if (parts.length > 1) {
        // Limitar números a 9 dígitos
        parts[1] = parts[1].replace(/\D/g, '').substring(0, 9);
        input.value = parts[0] + '-' + parts[1];
    }
    
    // Validar en tiempo real
    const rifValue = input.value.trim();
    const rifPattern = /^J-\d{9}$/;
    
    if (rifValue && !rifPattern.test(rifValue)) {
        input.classList.add('border-red-500');
        input.classList.remove('border-gray-300');
    } else {
        input.classList.remove('border-red-500');
        input.classList.add('border-gray-300');
    }
}

// Auto-formato del teléfono
document.addEventListener('DOMContentLoaded', function() {
    const telefonoInput = document.getElementById('telefono');
    if (telefonoInput) {
        telefonoInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^\d\s\-\+\(\)]/g, '');
        });
    }
    
    // Validación al enviar el formulario
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function(e) {
            const rifInput = document.getElementById('rif');
            if (rifInput) {
                const rifValue = rifInput.value.trim();
                const rifPattern = /^J-\d{9}$/;
                
                if (!rifPattern.test(rifValue)) {
                    e.preventDefault();
                    alert('Por favor ingrese un RIF válido. Formato: J-123456789');
                    rifInput.focus();
                    rifInput.classList.add('border-red-500');
                    return false;
                }
            }
            return true;
        });
    }
});