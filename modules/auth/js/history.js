/**
 * history.js - Manejo del historial del navegador y prevención de caché
 */

class HistoryManager {
    constructor() {
        this.isInitialized = false;
        this.init();
    }
    
    init() {
        if (this.isInitialized) return;
        
        this.preventBackNavigation();
        this.preventCache();
        this.setupEventListeners();
        
        this.isInitialized = true;
    }
    
    preventBackNavigation() {
        // Reemplazar estado actual
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
        
        // Manejar navegación hacia atrás/adelante
        window.onpopstate = (event) => {
            this.handlePopState(event);
        };
        
        // Agregar estado al historial
        window.history.pushState(null, null, window.location.href);
    }
    
    handlePopState(event) {
        // Forzar al usuario a permanecer en la página actual
        window.history.pushState(null, null, window.location.href);
        
        // Registrar el intento de navegación (opcional, para debugging)
        this.logNavigationAttempt('popstate');
    }
    
    preventCache() {
        // Detectar si la página se carga desde caché
        window.onpageshow = (event) => {
            if (event.persisted) {
                this.handlePageFromCache();
            }
        };
        
        // Prevenir atajos de teclado para navegación
        document.addEventListener('keydown', (e) => {
            this.preventNavigationShortcuts(e);
        });
    }
    
    handlePageFromCache() {
        // La página viene del caché, forzar recarga
        console.log('Página cargada desde caché, recargando...');
        
        // Opción 1: Recargar inmediatamente
        // window.location.reload();
        
        // Opción 2: Mostrar mensaje y luego recargar
        this.showCacheWarning();
    }
    
    showCacheWarning() {
        // Puedes mostrar un mensaje sutil al usuario
        const warningMsg = 'Actualizando datos de sesión...';
        console.log(warningMsg);
        
        // Opcional: mostrar un toast o notificación
        // this.showToast(warningMsg, 'info');
    }
    
    setupEventListeners() {
        // Prevenir clic derecho (opcional)
        document.addEventListener('contextmenu', (e) => {
            e.preventDefault();
        }, false);
        
        // Prevenir arrastrar imágenes (opcional)
        document.addEventListener('dragstart', (e) => {
            if (e.target.tagName === 'IMG') {
                e.preventDefault();
                return false;
            }
        }, false);
    }
    
    preventNavigationShortcuts(event) {
        // Bloquear atajos comunes de navegación
        const blockedShortcuts = [
            // Alt + Flecha izquierda (Atrás en Firefox/Chrome)
            { altKey: true, key: 'ArrowLeft' },
            // Command + [ (Atrás en Mac)
            { metaKey: true, key: '[' },
            // Command + Flecha izquierda (Atrás en Mac)
            { metaKey: true, key: 'ArrowLeft' }
        ];
        
        const isBlocked = blockedShortcuts.some(shortcut => 
            event.altKey === (shortcut.altKey || false) &&
            event.metaKey === (shortcut.metaKey || false) &&
            event.key === shortcut.key
        );
        
        if (isBlocked) {
            event.preventDefault();
            this.logNavigationAttempt('keyboard-shortcut', event.key);
            return false;
        }
    }
    
    logNavigationAttempt(type, details = '') {
        // Solo registrar en desarrollo
        if (window.location.hostname === 'localhost' || 
            window.location.hostname === '127.0.0.1') {
            console.log(`Intento de navegación bloqueado: ${type} ${details}`);
        }
    }
    
    // Método para mostrar notificaciones toast
    showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `fixed bottom-4 right-4 px-4 py-2 rounded-lg shadow-lg 
                          ${type === 'info' ? 'bg-blue-500 text-white' : 
                            type === 'warning' ? 'bg-yellow-500 text-black' : 
                            'bg-red-500 text-white'} 
                          transition-opacity duration-300 opacity-0`;
        toast.textContent = message;
        toast.id = 'history-toast';
        
        document.body.appendChild(toast);
        
        // Animación de entrada
        setTimeout(() => {
            toast.classList.remove('opacity-0');
            toast.classList.add('opacity-100');
        }, 10);
        
        // Auto-eliminar después de 3 segundos
        setTimeout(() => {
            toast.classList.remove('opacity-100');
            toast.classList.add('opacity-0');
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 300);
        }, 3000);
    }
    
    // Método para limpiar el historial (útil para logout)
    static clearHistoryState() {
        if (window.history.replaceState) {
            window.history.replaceState(null, '', window.location.href);
        }
    }
}

// Inicializar gestión del historial
document.addEventListener('DOMContentLoaded', () => {
    window.historyManager = new HistoryManager();
});

// Exportar funciones útiles
window.HistoryUtils = {
    preventBackButton: function() {
        window.history.pushState(null, null, window.location.href);
        window.onpopstate = function() {
            window.history.pushState(null, null, window.location.href);
        };
    },
    
    disableCache: function() {
        // Headers que deberían estar en el servidor, pero por si acaso
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) {
                // Página volvió a ser visible, verificar sesión
                window.dispatchEvent(new Event('visibilitychange-detected'));
            }
        });
    }
};