/**
 * reset_password.js - Lógica para establecer nueva contraseña
 */

class ResetPasswordManager {
    constructor() {
        this.form = document.getElementById('resetForm');
        if (!this.form) return;
        
        this.passwordInput = document.getElementById('password');
        this.confirmPasswordInput = document.getElementById('confirm_password');
        this.passwordMatchElement = document.getElementById('passwordMatch');
        this.passwordStrengthElement = document.getElementById('passwordStrength');
        this.submitButton = this.form.querySelector('button[type="submit"]');
        
        this.init();
    }
    
    init() {
        this.bindEvents();
        this.setupPasswordToggle();
        this.preventMultipleSubmissions();
        this.autoFocusPassword();
    }
    
    bindEvents() {
        // Validación en tiempo real
        this.passwordInput.addEventListener('input', () => {
            this.checkPasswordStrength();
            this.checkPasswordMatch();
        });
        
        this.confirmPasswordInput.addEventListener('input', () => {
            this.checkPasswordMatch();
        });
    }
    
    setupPasswordToggle() {
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
    
    checkPasswordStrength() {
        const password = this.passwordInput.value;
        let strength = 0;
        let strengthClass = '';
        let strengthText = '';
        
        if (password.length >= 6) strength++;
        if (password.length >= 8) strength++;
        if (/[A-Z]/.test(password)) strength++;
        if (/[0-9]/.test(password)) strength++;
        if (/[^A-Za-z0-9]/.test(password)) strength++;
        
        // Determinar fuerza
        if (password.length === 0) {
            strengthClass = '';
            strengthText = '';
        } else if (strength <= 2) {
            strengthClass = 'weak';
            strengthText = 'Débil';
        } else if (strength === 3) {
            strengthClass = 'fair';
            strengthText = 'Moderada';
        } else if (strength === 4) {
            strengthClass = 'good';
            strengthText = 'Buena';
        } else {
            strengthClass = 'strong';
            strengthText = 'Fuerte';
        }
        
        // Actualizar UI
        this.passwordStrengthElement.className = `password-strength-bar ${strengthClass}`;
        this.passwordStrengthElement.setAttribute('title', strengthText);
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
    }
    
    showPasswordMatchError() {
        this.passwordMatchElement.innerHTML = '<i class="fas fa-times-circle text-red-500 mr-1"></i>Las contraseñas no coinciden';
        this.passwordMatchElement.className = 'mt-1 text-xs text-red-600';
    }
    
    validateForm() {
        const password = this.passwordInput.value;
        const confirmPassword = this.confirmPasswordInput.value;
        
        // Validar longitud mínima
        if (password.length < 6) {
            alert('La contraseña debe tener al menos 6 caracteres');
            return false;
        }
        
        // Validar coincidencia
        if (password !== confirmPassword) {
            alert('Las contraseñas no coinciden');
            return false;
        }
        
        return true;
    }
    
    preventMultipleSubmissions() {
        this.form.addEventListener('submit', (e) => {
            if (!this.validateForm()) {
                e.preventDefault();
                return false;
            }
            
            this.disableSubmitButton();
        });
    }
    
    disableSubmitButton() {
        this.submitButton.disabled = true;
        this.submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Guardando...';
        this.submitButton.classList.add('opacity-70', 'cursor-not-allowed');
    }
    
    autoFocusPassword() {
        setTimeout(() => {
            this.passwordInput.focus();
        }, 100);
    }
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('resetForm')) {
        window.resetPasswordManager = new ResetPasswordManager();
    }
});