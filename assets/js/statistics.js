(function($) {
    'use strict';

    $(document).ready(function() {
        if (typeof emSmtpStats === 'undefined') {
            return;
        }

        // Daily Chart
        const dailyCtx = document.getElementById('dailyChart');
        if (dailyCtx) {
            new Chart(dailyCtx, {
                type: 'line',
                data: {
                    labels: emSmtpStats.dailyData.labels,
                    datasets: [
                        {
                            label: 'Sent',
                            data: emSmtpStats.dailyData.sent,
                            borderColor: 'rgb(75, 192, 192)',
                            backgroundColor: 'rgba(75, 192, 192, 0.2)',
                            tension: 0.4
                        },
                        {
                            label: 'Failed',
                            data: emSmtpStats.dailyData.failed,
                            borderColor: 'rgb(255, 99, 132)',
                            backgroundColor: 'rgba(255, 99, 132, 0.2)',
                            tension: 0.4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        title: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
        }

        // Hourly Chart
        const hourlyCtx = document.getElementById('hourlyChart');
        if (hourlyCtx) {
            new Chart(hourlyCtx, {
                type: 'bar',
                data: {
                    labels: emSmtpStats.hourlyData.labels,
                    datasets: [
                        {
                            label: 'Sent',
                            data: emSmtpStats.hourlyData.sent,
                            backgroundColor: 'rgba(75, 192, 192, 0.8)',
                        },
                        {
                            label: 'Failed',
                            data: emSmtpStats.hourlyData.failed,
                            backgroundColor: 'rgba(255, 99, 132, 0.8)',
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        },
                        x: {
                            ticks: {
                                maxRotation: 45,
                                minRotation: 45
                            }
                        }
                    }
                }
            });
        }
    });
})(jQuery);