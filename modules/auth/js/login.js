/**
 * login.js - Lógica principal del formulario de login
 * Sistema de Nómina para Contadores
 */

// Configuración
const CONFIG = {
    localStorageKey: 'nomina_',
    autoCompleteDelay: 300
};

// Clase principal del login
class LoginManager {
    constructor() {
        this.form = document.getElementById('loginForm');
        this.emailInput = document.getElementById('email');
        this.passwordInput = document.getElementById('password');
        this.togglePasswordBtn = document.getElementById('togglePassword');
        this.rememberCheckbox = document.getElementById('remember');
        this.submitButton = this.form.querySelector('button[type="submit"]');
        
        this.init();
    }
    
    init() {
        this.bindEvents();
        this.loadSavedCredentials();
        this.preventMultipleSubmissions();
        this.setupAutoCompleteDetection();
    }
    
    bindEvents() {
        // Mostrar/ocultar contraseña
        if (this.togglePasswordBtn) {
            this.togglePasswordBtn.addEventListener('click', () => this.togglePasswordVisibility());
        }
        
        // Recordar credenciales
        if (this.rememberCheckbox) {
            this.rememberCheckbox.addEventListener('change', () => this.handleRememberChange());
        }
        
        // Guardar email cuando pierde el foco
        this.emailInput.addEventListener('blur', () => this.saveEmailIfRemembered());
        
        // Enfocar automáticamente el campo de email
        this.emailInput.focus();
    }
    
    togglePasswordVisibility() {
        const type = this.passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        this.passwordInput.setAttribute('type', type);
        
        const eyeIcon = this.togglePasswordBtn.querySelector('i');
        eyeIcon.className = type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
    }
    
    handleRememberChange() {
        if (this.rememberCheckbox.checked) {
            this.saveCredentials();
        } else {
            this.clearSavedCredentials();
        }
    }
    
    saveEmailIfRemembered() {
        if (this.rememberCheckbox.checked && this.emailInput.value) {
            this.saveCredentials();
        }
    }
    
    loadSavedCredentials() {
        try {
            const savedEmail = localStorage.getItem(`${CONFIG.localStorageKey}email`);
            const savedRemember = localStorage.getItem(`${CONFIG.localStorageKey}remember`);
            
            if (savedEmail && savedRemember === 'true') {
                this.emailInput.value = savedEmail;
                this.rememberCheckbox.checked = true;
                
                // Notificar que hay credenciales guardadas
                this.dispatchEmailLoadedEvent(savedEmail);
            }
        } catch (error) {
            console.warn('No se pudieron cargar las credenciales guardadas:', error);
        }
    }
    
    saveCredentials() {
        try {
            const email = this.emailInput.value.trim();
            if (email) {
                localStorage.setItem(`${CONFIG.localStorageKey}email`, email);
                localStorage.setItem(`${CONFIG.localStorageKey}remember`, 'true');
            }
        } catch (error) {
            console.warn('No se pudieron guardar las credenciales:', error);
        }
    }
    
    clearSavedCredentials() {
        try {
            localStorage.removeItem(`${CONFIG.localStorageKey}email`);
            localStorage.removeItem(`${CONFIG.localStorageKey}remember`);
        } catch (error) {
            console.warn('No se pudieron eliminar las credenciales:', error);
        }
    }
    
    preventMultipleSubmissions() {
        this.form.addEventListener('submit', (e) => {
            this.disableSubmitButton();
        });
    }
    
    disableSubmitButton() {
        this.submitButton.disabled = true;
        this.submitButton.classList.add('btn-submit-loading');
        this.submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Iniciando sesión...';
    }
    
    enableSubmitButton() {
        this.submitButton.disabled = false;
        this.submitButton.classList.remove('btn-submit-loading');
        this.submitButton.innerHTML = '<i class="fas fa-sign-in-alt mr-2"></i>Iniciar Sesión';
    }
    
    setupAutoCompleteDetection() {
        // Detectar autocompletado después de un breve retraso
        setTimeout(() => {
            if (this.emailInput.value && !this.emailInput.matches(':autofill')) {
                this.dispatchAutocompleteDetectedEvent();
            }
        }, CONFIG.autoCompleteDelay);
    }
    
    // Eventos personalizados para comunicación entre módulos
    dispatchEmailLoadedEvent(email) {
        const event = new CustomEvent('emailLoaded', { 
            detail: { email: email },
            bubbles: true 
        });
        this.emailInput.dispatchEvent(event);
    }
    
    dispatchAutocompleteDetectedEvent() {
        const event = new CustomEvent('autocompleteDetected', { 
            bubbles: true 
        });
        document.dispatchEvent(event);
    }
    
    // Método para resetear el formulario (si es necesario)
    resetForm() {
        this.form.reset();
        this.enableSubmitButton();
        this.emailInput.focus();
    }
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
    window.loginManager = new LoginManager();
});

// Exponer funciones útiles para otros scripts
window.LoginUtils = {
    getFormData: function() {
        const form = document.getElementById('loginForm');
        return new FormData(form);
    },
    
    isValidEmail: function(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    },
    
    validateForm: function() {
        const email = document.getElementById('email').value;
        const password = document.getElementById('password').value;
        
        if (!email || !password) {
            return { isValid: false, message: 'Por favor completa todos los campos' };
        }
        
        if (!this.isValidEmail(email)) {
            return { isValid: false, message: 'Por favor ingresa un email válido' };
        }
        
        if (password.length < 6) {
            return { isValid: false, message: 'La contraseña debe tener al menos 6 caracteres' };
        }
        
        return { isValid: true, message: '' };
    }
};