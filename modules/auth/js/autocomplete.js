/**
 * autocomplete.js - Gestión de autocompletado para el formulario de login
 */

class AutoCompleteManager {
    constructor() {
        this.emailInput = document.getElementById('email');
        this.autocompleteContainer = document.getElementById('email-autocomplete');
        this.commonDomains = [
            '@gmail.com',
            '@hotmail.com',
            '@outlook.com',
            '@yahoo.com',
            '@empresa.com',
            '@empresa.mx',
            '@company.com'
        ];
        
        this.init();
    }
    
    init() {
        this.bindEvents();
        this.setupSystemUsers();
    }
    
    bindEvents() {
        // Escuchar cambios en el campo email
        this.emailInput.addEventListener('input', (e) => {
            this.handleEmailInput(e.target.value);
        });
        
        // Ocultar sugerencias al hacer clic fuera
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.relative')) {
                this.hideSuggestions();
            }
        });
        
        // Escuchar evento de email cargado
        document.addEventListener('emailLoaded', (e) => {
            if (e.detail.email) {
                this.showDomainSuggestions(e.detail.email);
            }
        });
    }
    
    handleEmailInput(inputValue) {
        if (!inputValue || inputValue.length < 2) {
            this.hideSuggestions();
            return;
        }
        
        // Si hay @, mostrar sugerencias de dominio
        if (inputValue.includes('@')) {
            this.showDomainSuggestions(inputValue);
        } else {
            // Mostrar sugerencias de usuarios del sistema
            this.showSystemUserSuggestions(inputValue);
        }
    }
    
    showDomainSuggestions(emailInput) {
        const input = emailInput.toLowerCase();
        const atIndex = input.indexOf('@');
        
        if (atIndex === -1) {
            this.hideSuggestions();
            return;
        }
        
        const userPart = input.substring(0, atIndex + 1);
        const domainPart = input.substring(atIndex);
        
        const matchingDomains = this.commonDomains.filter(domain => 
            domain.toLowerCase().startsWith(domainPart)
        );
        
        if (matchingDomains.length > 0) {
            this.displaySuggestions(
                matchingDomains.map(domain => ({
                    value: userPart + domain.substring(1),
                    label: userPart + domain.substring(1),
                    type: 'domain'
                }))
            );
        } else {
            this.hideSuggestions();
        }
    }
    
    showSystemUserSuggestions(searchTerm) {
        const systemUsers = this.getSystemUsers();
        const filteredUsers = systemUsers.filter(user => 
            user.email.toLowerCase().includes(searchTerm.toLowerCase()) ||
            user.name.toLowerCase().includes(searchTerm.toLowerCase())
        );
        
        if (filteredUsers.length > 0) {
            this.displaySuggestions(
                filteredUsers.map(user => ({
                    value: user.email,
                    label: `${user.email} (${user.name})`,
                    name: user.name,
                    type: 'system'
                }))
            );
        } else {
            this.hideSuggestions();
        }
    }
    
    displaySuggestions(suggestions) {
        this.autocompleteContainer.innerHTML = '';
        
        suggestions.forEach(suggestion => {
            const suggestionElement = this.createSuggestionElement(suggestion);
            this.autocompleteContainer.appendChild(suggestionElement);
        });
        
        this.autocompleteContainer.style.display = 'block';
    }
    
    createSuggestionElement(suggestion) {
        const div = document.createElement('div');
        div.className = 'autocomplete-item';
        
        if (suggestion.type === 'system') {
            div.innerHTML = `
                <div class="font-medium text-gray-900">${suggestion.value}</div>
                <div class="text-sm text-gray-500">${suggestion.name}</div>
            `;
        } else {
            div.textContent = suggestion.label;
        }
        
        div.addEventListener('click', () => {
            this.selectSuggestion(suggestion.value);
        });
        
        return div;
    }
    
    selectSuggestion(email) {
        this.emailInput.value = email;
        this.hideSuggestions();
        
        // Enfocar el campo de contraseña automáticamente
        document.getElementById('password').focus();
        
        // Disparar evento personalizado
        const event = new CustomEvent('suggestionSelected', {
            detail: { email: email },
            bubbles: true
        });
        this.emailInput.dispatchEvent(event);
    }
    
    hideSuggestions() {
        this.autocompleteContainer.style.display = 'none';
    }
    
    setupSystemUsers() {
        // Cargar usuarios del sistema desde localStorage o usar valores por defecto
        const defaultUsers = [
            { email: 'admin@nominas.com', name: 'Administrador' },
            { email: 'contador1@empresa.com', name: 'Juan Pérez' },
            { email: 'contador2@empresa.com', name: 'María López' }
        ];
        
        try {
            const savedUsers = localStorage.getItem('nomina_system_users');
            if (!savedUsers) {
                localStorage.setItem('nomina_system_users', JSON.stringify(defaultUsers));
            }
        } catch (error) {
            console.warn('No se pudo configurar usuarios del sistema:', error);
        }
    }
    
    getSystemUsers() {
        try {
            const savedUsers = localStorage.getItem('nomina_system_users');
            return savedUsers ? JSON.parse(savedUsers) : [];
        } catch (error) {
            console.warn('No se pudieron obtener usuarios del sistema:', error);
            return [];
        }
    }
    
    // Método para agregar un nuevo usuario al sistema (cuando se registra un nuevo contador)
    addSystemUser(email, name) {
        try {
            const users = this.getSystemUsers();
            
            // Evitar duplicados
            if (!users.some(user => user.email === email)) {
                users.push({ email, name });
                localStorage.setItem('nomina_system_users', JSON.stringify(users));
                return true;
            }
        } catch (error) {
            console.warn('No se pudo agregar usuario al sistema:', error);
        }
        return false;
    }
}

// Inicializar autocompletado
document.addEventListener('DOMContentLoaded', () => {
    window.autoCompleteManager = new AutoCompleteManager();
});