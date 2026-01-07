$(function() {
    const chartDataBundle = JSON.parse(document.getElementById('dashboard-chart-data').textContent);

    // Modern color palette
    const colorPrimary = '#5627FF'; // A more vibrant purple
    const colorSuccess = '#00C853'; // A brighter green
    const colorWarning = '#FFAB00'; // A rich amber
    const colorDanger = '#D50000';
    const colorInfo = '#2962FF';
    const colorMuted = '#9E9E9E';
    const gridColor = '#E0E0E0';
    const zeroLineColor = '#BDBDBD';
    const textColor = '#424242';

    // Chart.js global settings
    Chart.defaults.font.family = "'Roboto', 'Helvetica', 'Arial', sans-serif";
    Chart.defaults.font.size = 13;
    Chart.defaults.color = textColor;
    Chart.defaults.plugins.legend.position = 'top';
    Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(0, 0, 0, 0.8)';
    Chart.defaults.plugins.tooltip.titleFont = {
        weight: 'bold',
        size: 14
    };
    Chart.defaults.plugins.tooltip.bodyFont = {
        size: 13
    };
    Chart.defaults.plugins.tooltip.padding = 10;
    Chart.defaults.plugins.tooltip.cornerRadius = 5;
    Chart.defaults.plugins.tooltip.displayColors = true;

    function createGradient(ctx, color1, color2) {
        const gradient = ctx.createLinearGradient(0, 0, 0, ctx.canvas.height);
        gradient.addColorStop(0, color1);
        gradient.addColorStop(1, color2);
        return gradient;
    }

    // 1. Doughnut Chart (Status Pesanan) - Updated
    if (chartDataBundle.orderStatusData && chartDataBundle.orderStatusData.length > 0 && $("#doughnutChart1").length) {
        const doughnutCtx = $("#doughnutChart1").get(0).getContext("2d");
        const backgroundColorsDoughnut = Object.keys(chartDataBundle.orderStatusRawCounts).map(key =>
            chartDataBundle.statusColorsMap[key.toLowerCase()] || colorMuted
        );

        const doughnutPieData = {
            labels: chartDataBundle.orderStatusLabels,
            datasets: [{
                data: chartDataBundle.orderStatusData,
                backgroundColor: backgroundColorsDoughnut,
                borderColor: '#FFFFFF',
                borderWidth: 3,
                hoverOffset: 12,
                hoverBorderColor: '#FFFFFF',
                hoverBorderWidth: 3
            }]
        };
        const doughnutPieOptions = {
            responsive: true,
            maintainAspectRatio: false,
            animation: {
                animateScale: true,
                animateRotate: true,
                duration: 1200,
                easing: 'easeInOutQuart'
            },
            cutout: '70%',
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return `${context.label}: ${context.parsed} Pesanan`;
                        }
                    }
                }
            }
        };

        if (window.doughnutChartInstance1) window.doughnutChartInstance1.destroy();
        window.doughnutChartInstance1 = new Chart(doughnutCtx, {
            type: 'doughnut',
            data: doughnutPieData,
            options: doughnutPieOptions
        });

        // Custom Legend
        if (document.getElementById('doughnut-chart-legend')) {
            const legendContainer = $('#doughnut-chart-legend');
            let legendHtml = '<ul class="chart-legend legend-vertical legend-bottom-left">';
            doughnutPieData.labels.forEach((label, i) => {
                const bgColor = doughnutPieData.datasets[0].backgroundColor[i];
                const value = doughnutPieData.datasets[0].data[i];
                legendHtml += `<li><span class="legend-dots" style="background-color:${bgColor}"></span>${label} (${value})</li>`;
            });
            legendHtml += '</ul>';
            legendContainer.html(legendHtml);
        }

    } else if ($("#doughnutChart1").length) {
        $("#doughnutChart1").closest('.doughnut-chart-container').html("<p class='text-center text-muted p-3'>Data status pesanan tidak tersedia.</p>");
    }

    // 2. Sales Revenue Chart (was Flot, now Chart.js) - Modernized
    if (chartDataBundle.flotRevenueCatData && chartDataBundle.flotRevenueCatData.length > 0 && $("#flotChart").length) {
        const revenueCtx = $("#flotChart").get(0).getContext("2d");
        $("#flotChart").removeAttr("style").parent().addClass('chartjs-wrapper'); // Make it a canvas

        const revenueLabels = chartDataBundle.flotRevenueCatTicks.map(tick => tick[1]);
        const revenueData = chartDataBundle.flotRevenueCatData.map(point => point[1]);

        const gradientFillRevenue = createGradient(revenueCtx, Chart.helpers.color(colorPrimary).alpha(0.3).rgbString(), Chart.helpers.color(colorPrimary).alpha(0.0).rgbString());

        const revenueChartData = {
            labels: revenueLabels,
            datasets: [{
                label: 'Pendapatan Harian',
                data: revenueData,
                borderColor: colorPrimary,
                backgroundColor: gradientFillRevenue,
                borderWidth: 3,
                fill: true,
                pointBackgroundColor: '#FFFFFF',
                pointBorderColor: colorPrimary,
                pointRadius: 5,
                pointHoverRadius: 7,
                pointHoverBorderWidth: 2,
                tension: 0.4
            }]
        };

        const revenueChartOptions = {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        color: colorMuted,
                        callback: function(value) {
                            if (value >= 1E6) return `Rp ${(value / 1E6).toFixed(1)} Jt`;
                            if (value >= 1E3) return `Rp ${(value / 1E3).toFixed(0)} Rb`;
                            return `Rp ${value.toLocaleString()}`;
                        }
                    },
                    grid: {
                        color: gridColor,
                        drawBorder: false,
                    }
                },
                x: {
                    ticks: {
                        color: colorMuted,
                        maxRotation: 0,
                        minRotation: 0,
                        autoSkip: true,
                        maxTicksLimit: 15
                    },
                    grid: {
                        display: false,
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: c => `Pendapatan: Rp ${c.parsed.y.toLocaleString()}`
                    }
                }
            },
            interaction: {
                mode: 'index',
                intersect: false
            }
        };

        if (window.salesRevenueChartInstance) window.salesRevenueChartInstance.destroy();
        window.salesRevenueChartInstance = new Chart(revenueCtx, {
            type: 'line',
            data: revenueChartData,
            options: revenueChartOptions
        });

    } else if ($("#flotChart").length) {
        $("#flotChart").html("<p class='text-center text-muted p-5'>Data pendapatan tidak tersedia.</p>");
    }


    // 3. Line Chart (New Customers) - Modernized
    if (chartDataBundle.customerLineLabels && chartDataBundle.customerLineLabels.length > 1 && $("#linechart").length) {
        const lineChartCtx = $("#linechart").get(0).getContext("2d");
        const gradientFillCustomers = createGradient(lineChartCtx, Chart.helpers.color(colorSuccess).alpha(0.3).rgbString(), Chart.helpers.color(colorSuccess).alpha(0.0).rgbString());

        const customerChartData = {
            labels: chartDataBundle.customerLineLabels,
            datasets: [{
                label: 'Pelanggan Baru',
                data: chartDataBundle.customerLineData,
                borderColor: colorSuccess,
                backgroundColor: gradientFillCustomers,
                borderWidth: 3,
                fill: true,
                pointBackgroundColor: '#FFFFFF',
                pointBorderColor: colorSuccess,
                pointRadius: 5,
                pointHoverRadius: 7,
                pointHoverBorderWidth: 2,
                tension: 0.4
            }]
        };
        const customerChartOptions = {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        color: colorMuted,
                        stepSize: 1
                    },
                    grid: {
                        color: gridColor,
                        drawBorder: false
                    }
                },
                x: {
                    ticks: {
                        color: colorMuted,
                        maxRotation: 0,
                        minRotation: 0,
                        autoSkip: true,
                        maxTicksLimit: 15
                    },
                    grid: {
                        display: false
                    }
                }
            },
            plugins: {
                legend: {
                    display: false,
                },
                tooltip: {
                    callbacks: {
                        label: c => ` ${c.parsed.y} Pelanggan Baru`
                    }
                }
            },
            interaction: {
                mode: 'index',
                intersect: false
            }
        };
        if (window.customerLineChartInstance) window.customerLineChartInstance.destroy();
        window.customerLineChartInstance = new Chart(lineChartCtx, {
            type: 'line',
            data: customerChartData,
            options: customerChartOptions
        });
    } else if ($("#linechart").length) {
        $("#linechart").parent().html("<p class='text-center text-muted p-5'>Data pelanggan tidak cukup.</p>");
    }

    // 4. Bar Chart (Top Menu Items) - Modernized
    if (chartDataBundle.menuBarLabels && chartDataBundle.menuBarLabels.length > 0 && $("#barchart").length) {
        const barChartCtx = $("#barchart").get(0).getContext("2d");

        const barColors = [
            '#FFAB00', '#FFC107', '#FFD54F', '#FFECB3',
            '#FFA000', '#FF8F00', '#FF6F00'
        ];

        const menuChartData = {
            labels: chartDataBundle.menuBarLabels,
            datasets: [{
                label: 'Jumlah Dipesan',
                data: chartDataBundle.menuBarData,
                backgroundColor: barColors,
                borderColor: barColors,
                borderWidth: 1,
                borderRadius: 5,
                hoverBackgroundColor: barColors.map(color => Chart.helpers.color(color).alpha(0.8).rgbString())
            }]
        };
        const menuChartOptions = {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        color: colorMuted
                    },
                    grid: {
                        color: gridColor,
                        drawBorder: false
                    }
                },
                x: {
                    ticks: {
                        color: textColor,
                        font: {
                            weight: '500'
                        }
                    },
                    grid: {
                        display: false
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: c => ` Dipesan: ${c.parsed.y} kali`
                    }
                }
            }
        };
        if (window.menuBarChartInstance) window.menuBarChartInstance.destroy();
        window.menuBarChartInstance = new Chart(barChartCtx, {
            type: 'bar',
            data: menuChartData,
            options: menuChartOptions
        });
    } else if ($("#barchart").length) {
        $("#barchart").parent().html("<p class='text-center text-muted p-5'>Data menu terlaris tidak tersedia.</p>");
    }

    // 5. Column Chart (was Flot Bar, now Chart.js mini bar)
    if (chartDataBundle.columnChartFlotData && chartDataBundle.columnChartFlotData.length > 0 && $("#column-chart").length) {
        const columnCtx = $("#column-chart").get(0).getContext("2d");
        $("#column-chart").removeAttr("style"); // Make it a canvas

        const columnData = chartDataBundle.columnChartFlotData.map(d => d[1]);
        const columnLabels = chartDataBundle.columnChartFlotData.map(d => `Tgl ${d[0]}`);

        const columnChartData = {
            labels: columnLabels,
            datasets: [{
                label: 'Pendapatan',
                data: columnData,
                backgroundColor: '#FFFFFF',
                borderRadius: 2,
            }]
        };

        const columnChartOptions = {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    display: false,
                    beginAtZero: true
                },
                x: {
                    display: false
                }
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    enabled: true,
                    displayColors: false,
                    backgroundColor: 'rgba(0,0,0,0.7)',
                    callbacks: {
                        title: () => null,
                        label: c => `Rp ${c.parsed.y.toLocaleString()}`
                    }
                }
            }
        };

        if(window.miniBarChart) window.miniBarChart.destroy();
        window.miniBarChart = new Chart(columnCtx, {
            type: 'bar',
            data: columnChartData,
            options: columnChartOptions
        });
    } else if ($("#column-chart").length) {
        $("#column-chart").html("<div class='text-white text-center small' style='padding-top:30px;'>No data</div>");
    }

});