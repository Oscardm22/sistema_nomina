/**
 * validation.js - Utilidades de validación reutilizables
 * Sistema de Nómina para Contadores
 */

class FormValidator {
    constructor(formId) {
        this.form = document.getElementById(formId);
        if (!this.form) {
            console.error(`Formulario con ID "${formId}" no encontrado`);
            return;
        }
        
        this.fields = [];
        this.errors = [];
        this.init();
    }
    
    init() {
        this.setupValidationRules();
        this.setupEventListeners();
    }
    
    setupValidationRules() {
        // Buscar todos los campos con atributo data-validation
        const inputs = this.form.querySelectorAll('[data-validation]');
        inputs.forEach(input => {
            const rules = input.getAttribute('data-validation').split('|');
            this.fields.push({
                element: input,
                rules: rules,
                errorElement: document.getElementById(`${input.id}-error`) || null
            });
        });
    }
    
    setupEventListeners() {
        // Validación en tiempo real al perder foco
        this.fields.forEach(field => {
            field.element.addEventListener('blur', () => this.validateField(field));
            field.element.addEventListener('input', () => this.clearFieldError(field));
        });
        
        // Validar todo el formulario al enviar
        this.form.addEventListener('submit', (e) => {
            if (!this.validateAll()) {
                e.preventDefault();
                this.showValidationErrors();
            }
        });
    }
    
    validateField(field) {
        const value = field.element.value.trim();
        let isValid = true;
        let errorMessage = '';
        
        for (const rule of field.rules) {
            const result = this.checkRule(rule, value, field);
            if (!result.isValid) {
                isValid = false;
                errorMessage = result.message;
                break;
            }
        }
        
        this.displayFieldResult(field, isValid, errorMessage);
        return isValid;
    }
    
    checkRule(rule, value, field) {
        if (rule === 'required' && !value) {
            return { isValid: false, message: 'Este campo es requerido' };
        }
        
        if (rule.startsWith('min:')) {
            const minLength = parseInt(rule.split(':')[1]);
            if (value && value.length < minLength) {
                return { isValid: false, message: `Mínimo ${minLength} caracteres` };
            }
        }
        
        if (rule.startsWith('max:')) {
            const maxLength = parseInt(rule.split(':')[1]);
            if (value && value.length > maxLength) {
                return { isValid: false, message: `Máximo ${maxLength} caracteres` };
            }
        }
        
        if (rule === 'email' && value) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(value)) {
                return { isValid: false, message: 'Email inválido' };
            }
        }
        
        if (rule.startsWith('match:')) {
            const targetField = rule.split(':')[1];
            const targetElement = document.getElementById(targetField);
            if (targetElement && value !== targetElement.value) {
                return { isValid: false, message: 'Los campos no coinciden' };
            }
        }
        
        if (rule.startsWith('regex:')) {
            const pattern = new RegExp(rule.split(':')[1]);
            if (value && !pattern.test(value)) {
                return { isValid: false, message: 'Formato inválido' };
            }
        }
        
        return { isValid: true, message: '' };
    }
    
    displayFieldResult(field, isValid, errorMessage) {
        if (isValid) {
            field.element.classList.remove('error');
            field.element.classList.add('success');
            if (field.errorElement) {
                field.errorElement.classList.add('hidden');
                field.errorElement.textContent = '';
            }
        } else {
            field.element.classList.remove('success');
            field.element.classList.add('error');
            if (field.errorElement) {
                field.errorElement.textContent = errorMessage;
                field.errorElement.classList.remove('hidden');
            }
        }
    }
    
    clearFieldError(field) {
        field.element.classList.remove('error', 'success');
        if (field.errorElement) {
            field.errorElement.classList.add('hidden');
            field.errorElement.textContent = '';
        }
    }
    
    validateAll() {
        this.errors = [];
        let allValid = true;
        
        this.fields.forEach(field => {
            if (!this.validateField(field)) {
                allValid = false;
                this.errors.push({
                    field: field.element.id,
                    message: field.errorElement?.textContent || 'Error de validación'
                });
            }
        });
        
        return allValid;
    }
    
    showValidationErrors() {
        if (this.errors.length === 0) return;
        
        // Mostrar resumen de errores
        const errorMessages = this.errors.map(error => 
            `• ${this.getFieldLabel(error.field)}: ${error.message}`
        ).join('\n');
        
        // Podrías mostrar un modal o notificación aquí
        console.log('Errores de validación:\n' + errorMessages);
        
        // Enfocar el primer campo con error
        const firstErrorField = this.errors[0].field;
        const firstErrorElement = document.getElementById(firstErrorField);
        if (firstErrorElement) {
            firstErrorElement.focus();
            firstErrorElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
    
    getFieldLabel(fieldId) {
        const label = document.querySelector(`label[for="${fieldId}"]`);
        if (label) {
            return label.textContent.replace('*', '').trim();
        }
        return fieldId;
    }
    
    // Métodos estáticos para validación rápida
    static validateEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
    
    static validateRequired(value) {
        return value && value.trim().length > 0;
    }
    
    static validateMinLength(value, minLength) {
        return value && value.length >= minLength;
    }
    
    static validateMaxLength(value, maxLength) {
        return value && value.length <= maxLength;
    }
    
    static validateMatch(value1, value2) {
        return value1 === value2;
    }
}

// Inicializar validadores automáticamente para formularios con data-validate
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form[data-validate]').forEach(form => {
        const formId = form.id;
        if (formId) {
            window[`${formId}Validator`] = new FormValidator(formId);
        }
    });
});

// Exportar utilidades
window.ValidationUtils = {
    FormValidator: FormValidator,
    
    createValidator: function(formId) {
        return new FormValidator(formId);
    },
    
    validateField: function(inputElement) {
        const validator = new FormValidator(inputElement.closest('form').id);
        const field = {
            element: inputElement,
            rules: inputElement.getAttribute('data-validation').split('|'),
            errorElement: document.getElementById(`${inputElement.id}-error`)
        };
        return validator.validateField(field);
    }
};