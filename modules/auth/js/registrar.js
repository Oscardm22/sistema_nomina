/**
 * registrar.js - Lógica principal del formulario de registro
 * Sistema de Nómina para Contadores
 */

// Configuración
const REGISTER_CONFIG = {
    minPasswordLength: 6,
    redirectDelay: 2000,
    localStorageKey: 'nomina_register_'
};

// Clase principal del registro
class RegisterManager {
    constructor() {
        this.form = document.getElementById('registerForm');
        this.nombreInput = document.getElementById('nombre');
        this.emailInput = document.getElementById('email');
        this.passwordInput = document.getElementById('password');
        this.confirmPasswordInput = document.getElementById('confirm_password');
        this.passwordMatchElement = document.getElementById('passwordMatch');
        this.submitButton = this.form.querySelector('button[type="submit"]');
        
        this.init();
    }
    
    init() {
        this.bindEvents();
        this.setupPasswordToggle();
        this.setupRealTimeValidation();
        this.preventMultipleSubmissions();
        this.autoFocusFirstField();
    }
    
    bindEvents() {
        // Validación en tiempo real para coincidencia de contraseñas
        this.passwordInput.addEventListener('input', () => this.checkPasswordMatch());
        this.confirmPasswordInput.addEventListener('input', () => this.checkPasswordMatch());
        
        // Guardar datos en localStorage mientras se escribe
        this.setupAutoSave();
        
        // Cargar datos guardados si existen
        this.loadSavedFormData();
    }
    
    setupPasswordToggle() {
        // Configurar botones para mostrar/ocultar contraseña
        document.querySelectorAll('.password-toggle').forEach(button => {
            button.addEventListener('click', (e) => {
                this.togglePasswordVisibility(e);
            });
        });
    }
    
    togglePasswordVisibility(event) {
        const button = event.currentTarget;
        const targetId = button.getAttribute('data-target');
        const passwordInput = document.getElementById(targetId);
        const icon = button.querySelector('i');
        
        if (passwordInput) {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            icon.className = type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
        }
    }
    
    checkPasswordMatch() {
        const password = this.passwordInput.value;
        const confirmPassword = this.confirmPasswordInput.value;
        
        if (!password || !confirmPassword) {
            this.passwordMatchElement.textContent = '';
            return;
        }
        
        if (password === confirmPassword) {
            this.showPasswordMatchSuccess();
        } else {
            this.showPasswordMatchError();
        }
    }
    
    showPasswordMatchSuccess() {
        this.passwordMatchElement.innerHTML = '<i class="fas fa-check-circle text-green-500 mr-1"></i>Las contraseñas coinciden';
        this.passwordMatchElement.className = 'mt-1 text-xs text-green-600';
        
        // Marcar campos como válidos
        this.passwordInput.classList.add('success');
        this.passwordInput.classList.remove('error');
        this.confirmPasswordInput.classList.add('success');
        this.confirmPasswordInput.classList.remove('error');
    }
    
    showPasswordMatchError() {
        this.passwordMatchElement.innerHTML = '<i class="fas fa-times-circle text-red-500 mr-1"></i>Las contraseñas no coinciden';
        this.passwordMatchElement.className = 'mt-1 text-xs text-red-600';
        
        // Marcar campos como inválidos
        this.passwordInput.classList.remove('success');
        this.passwordInput.classList.add('error');
        this.confirmPasswordInput.classList.remove('success');
        this.confirmPasswordInput.classList.add('error');
    }
    
    setupRealTimeValidation() {
        // Configurar validación en tiempo real para todos los campos
        const inputs = this.form.querySelectorAll('.register-input[data-validation]');
        inputs.forEach(input => {
            input.addEventListener('blur', () => this.validateField(input));
            input.addEventListener('input', () => this.clearFieldError(input));
        });
    }
    
    validateField(input) {
        const value = input.value.trim();
        const validationRules = input.getAttribute('data-validation');
        const fieldId = input.id;
        
        if (!validationRules) return true;
        
        const rules = validationRules.split('|');
        let isValid = true;
        let errorMessage = '';
        
        for (const rule of rules) {
            if (rule === 'required' && !value) {
                isValid = false;
                errorMessage = 'Este campo es requerido';
                break;
            }
            
            if (rule.startsWith('min:') && value) {
                const minLength = parseInt(rule.split(':')[1]);
                if (value.length < minLength) {
                    isValid = false;
                    errorMessage = `Mínimo ${minLength} caracteres`;
                    break;
                }
            }
            
            if (rule === 'email' && value) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(value)) {
                    isValid = false;
                    errorMessage = 'Email inválido';
                    break;
                }
            }
            
            if (rule === 'match:password' && value) {
                if (value !== this.passwordInput.value) {
                    isValid = false;
                    errorMessage = 'Las contraseñas no coinciden';
                    break;
                }
            }
        }
        
        this.displayFieldValidation(input, fieldId, isValid, errorMessage);
        return isValid;
    }
    
    displayFieldValidation(input, fieldId, isValid, errorMessage) {
        const errorElement = document.getElementById(`${fieldId}-error`);
        
        if (isValid) {
            input.classList.remove('error');
            input.classList.add('success');
            if (errorElement) {
                errorElement.classList.add('hidden');
                errorElement.textContent = '';
            }
        } else {
            input.classList.remove('success');
            input.classList.add('error');
            if (errorElement) {
                errorElement.textContent = errorMessage;
                errorElement.classList.remove('hidden');
            }
        }
    }
    
    clearFieldError(input) {
        const fieldId = input.id;
        const errorElement = document.getElementById(`${fieldId}-error`);
        
        input.classList.remove('error', 'success');
        if (errorElement) {
            errorElement.classList.add('hidden');
            errorElement.textContent = '';
        }
    }
    
    setupAutoSave() {
        // Guardar datos del formulario en localStorage mientras se escribe
        const fieldsToSave = ['nombre', 'email'];
        fieldsToSave.forEach(fieldName => {
            const input = document.getElementById(fieldName);
            if (input) {
                input.addEventListener('input', () => {
                    this.saveFormData();
                });
            }
        });
    }
    
    saveFormData() {
        try {
            const formData = {
                nombre: this.nombreInput.value,
                email: this.emailInput.value
            };
            localStorage.setItem(`${REGISTER_CONFIG.localStorageKey}form_data`, JSON.stringify(formData));
        } catch (error) {
            console.warn('No se pudieron guardar los datos del formulario:', error);
        }
    }
    
    loadSavedFormData() {
        try {
            const savedData = localStorage.getItem(`${REGISTER_CONFIG.localStorageKey}form_data`);
            if (savedData) {
                const formData = JSON.parse(savedData);
                if (formData.nombre) this.nombreInput.value = formData.nombre;
                if (formData.email) this.emailInput.value = formData.email;
                
                // Limpiar datos guardados después de cargarlos
                localStorage.removeItem(`${REGISTER_CONFIG.localStorageKey}form_data`);
            }
        } catch (error) {
            console.warn('No se pudieron cargar los datos guardados:', error);
        }
    }
    
    preventMultipleSubmissions() {
        this.form.addEventListener('submit', (e) => {
            if (!this.validateForm()) {
                e.preventDefault();
                return false;
            }
            
            this.disableSubmitButton();
            this.clearSavedFormData();
        });
    }
    
    validateForm() {
        let isValid = true;
        
        // Validar todos los campos
        const inputs = this.form.querySelectorAll('.register-input[data-validation]');
        inputs.forEach(input => {
            if (!this.validateField(input)) {
                isValid = false;
            }
        });
        
        // Validación adicional de contraseñas
        if (this.passwordInput.value !== this.confirmPasswordInput.value) {
            isValid = false;
            this.showPasswordMatchError();
        }
        
        // Validar longitud mínima de contraseña
        if (this.passwordInput.value.length < REGISTER_CONFIG.minPasswordLength) {
            isValid = false;
            this.displayFieldValidation(
                this.passwordInput, 
                'password', 
                false, 
                `La contraseña debe tener al menos ${REGISTER_CONFIG.minPasswordLength} caracteres`
            );
        }
        
        if (!isValid) {
            this.showValidationSummary();
        }
        
        return isValid;
    }
    
    showValidationSummary() {
        // Podrías implementar un modal o mensaje flotante aquí
        console.log('Por favor, corrige los errores en el formulario');
        
        // Enfocar el primer campo con error
        const firstError = this.form.querySelector('.register-input.error');
        if (firstError) {
            firstError.focus();
        }
    }
    
    disableSubmitButton() {
        this.submitButton.disabled = true;
        this.submitButton.classList.add('btn-register-loading');
        this.submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Creando cuenta...';
    }
    
    enableSubmitButton() {
        this.submitButton.disabled = false;
        this.submitButton.classList.remove('btn-register-loading');
        this.submitButton.innerHTML = '<i class="fas fa-user-plus mr-2"></i>Crear Cuenta';
    }
    
    clearSavedFormData() {
        try {
            localStorage.removeItem(`${REGISTER_CONFIG.localStorageKey}form_data`);
        } catch (error) {
            console.warn('No se pudieron limpiar los datos del formulario:', error);
        }
    }
    
    autoFocusFirstField() {
        // Enfocar automáticamente el primer campo
        const firstInput = this.form.querySelector('input[required]');
        if (firstInput) {
            setTimeout(() => {
                firstInput.focus();
            }, 100);
        }
    }
    
    // Método para resetear el formulario
    resetForm() {
        this.form.reset();
        this.enableSubmitButton();
        this.passwordMatchElement.textContent = '';
        
        // Limpiar clases de validación
        this.form.querySelectorAll('.register-input').forEach(input => {
            input.classList.remove('error', 'success');
        });
        
        // Limpiar mensajes de error
        this.form.querySelectorAll('.validation-message').forEach(msg => {
            msg.classList.add('hidden');
            msg.textContent = '';
        });
        
        this.autoFocusFirstField();
    }
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
    window.registerManager = new RegisterManager();
});

// Exponer funciones útiles para otros scripts
window.RegisterUtils = {
    validateEmail: function(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    },
    
    validatePassword: function(password) {
        return password.length >= REGISTER_CONFIG.minPasswordLength;
    },
    
    checkPasswordsMatch: function(password, confirmPassword) {
        return password === confirmPassword;
    },
    
    getFormData: function() {
        const form = document.getElementById('registerForm');
        return new FormData(form);
    },
    
    // Método para agregar usuario al sistema de autocompletado
    addToAutoCompleteSystem: function(email, name) {
        if (window.autoCompleteManager && typeof window.autoCompleteManager.addSystemUser === 'function') {
            return window.autoCompleteManager.addSystemUser(email, name);
        }
        return false;
    }
};