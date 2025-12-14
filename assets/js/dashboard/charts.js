// Gráfico de actividad con datos reales
const ctx = document.getElementById('activityChart');
if (ctx && window.dashboardData && window.dashboardData.tieneDatos) {
    const activityChart = new Chart(ctx.getContext('2d'), {
        type: 'line',
        data: {
            labels: window.dashboardData.meses,
            datasets: [{
                label: 'Nóminas procesadas',
                data: window.dashboardData.nominasProcesadas,
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            }, {
                label: 'Empleados agregados',
                data: window.dashboardData.empleadosAgregados,
                borderColor: '#10b981',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.parsed.y !== null) {
                                label += context.parsed.y + (context.dataset.label.includes('Empleados') ? ' empleados' : ' nóminas');
                            }
                            return label;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    },
                    ticks: {
                        stepSize: 1,
                        callback: function(value) {
                            if (Number.isInteger(value)) {
                                return value;
                            }
                        }
                    }
                },
                x: {
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    }
                }
            }
        }
    });
    
    // Opcional: Manejar cambio de período (si implementas AJAX)
    document.getElementById('periodoSelect')?.addEventListener('change', function(e) {
        console.log('Período cambiado a:', e.target.value, 'meses');
        // Aquí podrías hacer una petición AJAX para actualizar los datos
    });
}