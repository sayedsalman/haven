// Admin chart example using ApexCharts
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('usersChart')) {
        // Load chart library and render
        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/apexcharts';
        script.onload = function() {
            var options = {
                series: [{ name: 'Users', data: [30, 40, 35, 50, 49, 60, 70] }],
                chart: { type: 'line', height: 350 },
                xaxis: { categories: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] },
                colors: ['#5B8DEF']
            };
            var chart = new ApexCharts(document.getElementById('usersChart'), options);
            chart.render();
        };
        document.head.appendChild(script);
    }
});