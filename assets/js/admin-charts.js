

function initAdminDashboardCharts(ceData, passFailData, aiAccuracyData) {
    
    const barCtx = document.getElementById('ceSpecializationChart');
    if (barCtx) {
        new Chart(barCtx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: ceData.labels,
                datasets: [{
                    label: 'Active Questions Generated',
                    data: ceData.counts,
                    backgroundColor: [
                        'rgba(234, 88, 12, 0.85)',
                        'rgba(14, 165, 233, 0.85)',
                        'rgba(16, 185, 129, 0.85)',
                        'rgba(168, 85, 247, 0.85)',
                        'rgba(245, 158, 11, 0.85)'
                    ],
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    
    const pieCtx = document.getElementById('passFailPieChart');
    if (pieCtx) {
        new Chart(pieCtx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Passed (>=75%)', 'Failed (<75%)', 'Pending Checking'],
                datasets: [{
                    data: passFailData,
                    backgroundColor: ['#10b981', '#f43f5e', '#fbbf24']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    }

    
    const radarCtx = document.getElementById('aiAccuracyRadarChart');
    if (radarCtx) {
        new Chart(radarCtx.getContext('2d'), {
            type: 'radar',
            data: {
                labels: ['Vision OCR', 'Math Formulas', 'Theory Items', 'Auto Grading', 'Remediation'],
                datasets: [{
                    label: 'AI Model Precision %',
                    data: aiAccuracyData,
                    borderColor: '#ea580c',
                    backgroundColor: 'rgba(234, 88, 12, 0.2)',
                    pointBackgroundColor: '#ea580c'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    r: { min: 80, max: 100 }
                }
            }
        });
    }
}
