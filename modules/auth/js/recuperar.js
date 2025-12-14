/**
 * recuperar.js - Lógica para recuperación de contraseña
 */

class RecuperarManager {
    constructor() {
        this.form = document.getElementById('recuperarForm');
        if (!this.form) return;
        
        this.emailInput = document.getElementById('email');
        this.submitButton = this.form.querySelector('button[type="submit"]');
        
        this.init();
    }
    
    init() {
        this.bindEvents();
        this.preventMultipleSubmissions();
        this.autoFocusEmail();
    }
    
    bindEvents() {
        // Validación básica de email en tiempo real
        this.emailInput.addEventListener('blur', () => this.validateEmail());
    }
    
    validateEmail() {
        const email = this.emailInput.value.trim();
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        
        if (email && !emailRegex.test(email)) {
            this.showEmailError('Email inválido');
            return false;
        }
        
        this.clearEmailError();
        return true;
    }
    
    showEmailError(message) {
        // Podrías implementar un mensaje de error visual aquí
        this.emailInput.classList.add('border-red-500');
        console.error(message);
    }
    
    clearEmailError() {
        this.emailInput.classList.remove('border-red-500');
    }
    
    preventMultipleSubmissions() {
        this.form.addEventListener('submit', (e) => {
            if (!this.validateEmail()) {
                e.preventDefault();
                return false;
            }
            
            this.disableSubmitButton();
        });
    }
    
    disableSubmitButton() {
        this.submitButton.disabled = true;
        this.submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Enviando...';
        this.submitButton.classList.add('opacity-70', 'cursor-not-allowed');
    }
    
    autoFocusEmail() {
        setTimeout(() => {
            this.emailInput.focus();
        }, 100);
    }
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('recuperarForm')) {
        window.recuperarManager = new RecuperarManager();
    }
});