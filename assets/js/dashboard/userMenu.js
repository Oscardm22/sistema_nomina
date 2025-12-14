// Control del menú desplegable
const userMenuBtn = document.getElementById('userMenuBtn');
const userDropdown = document.getElementById('userDropdown');
const chevronIcon = document.getElementById('chevronIcon');

let isDropdownOpen = false;

// Abrir/cerrar menú al hacer clic
userMenuBtn.addEventListener('click', function(e) {
    e.stopPropagation(); // Evitar que el clic se propague
    isDropdownOpen = !isDropdownOpen;
    
    if (isDropdownOpen) {
        userDropdown.classList.add('show');
        chevronIcon.style.transform = 'rotate(180deg)';
    } else {
        userDropdown.classList.remove('show');
        chevronIcon.style.transform = 'rotate(0deg)';
    }
});

// Cerrar menú al hacer clic fuera
document.addEventListener('click', function(e) {
    if (!userMenuBtn.contains(e.target) && !userDropdown.contains(e.target)) {
        userDropdown.classList.remove('show');
        chevronIcon.style.transform = 'rotate(0deg)';
        isDropdownOpen = false;
    }
});

// Cerrar menú al hacer clic en una opción
userDropdown.addEventListener('click', function() {
    userDropdown.classList.remove('show');
    chevronIcon.style.transform = 'rotate(0deg)';
    isDropdownOpen = false;
});

// Evitar que el menú se cierre al hacer clic dentro de él
userDropdown.addEventListener('click', function(e) {
    e.stopPropagation();
});