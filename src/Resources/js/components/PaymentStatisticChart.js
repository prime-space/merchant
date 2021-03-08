const DATASET_AMOUNT_INDEX = 2;
export default {
    extends: VueChartJs.Bar,
    mixins: [VueChartJs.mixins.reactiveProp],
    props: [],
    data() {
        return {
            options: {
                tooltips: {
                    mode: 'index',
                    callbacks: {
                        label: function (tooltipItems, data) {
                            let label = data.datasets[tooltipItems.datasetIndex].label;
                            let value = tooltipItems.yLabel;
                            if (tooltipItems.datasetIndex === DATASET_AMOUNT_INDEX) {
                                let rubLabel = this.translate('chartCurrencyRub');
                                value = Main.formatMoney(value);

                                return `${label}: ${value} ${rubLabel}`;
                            } else {
                                return `${label}: ${value}`;
                            }
                        }.bind(this)
                    }
                },
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    xAxes: [{
                        stacked: true
                    }],
                    yAxes: [{
                        stacked: false,
                        position: "right",
                        id: "y-axis-0",
                        ticks: {
                            beginAtZero: true,
                            callback: function (value) {
                                if (Number.isInteger(value)) {
                                    return value;
                                }
                            }
                        }
                    }, {
                        stacked: false,
                        position: "left",
                        id: "y-axis-1",
                        ticks: {
                            beginAtZero: true,
                            callback: function (value, index, values) {
                                let rubLabel = this.translate('chartCurrencyRub');
                                value = Main.formatMoney(value);
                                return `${value} ${rubLabel}`;
                            }.bind(this)
                        }
                    }]
                }
            }
        }
    },
    mounted() {
        this.renderChart(this.chartData, this.options);
    }
}
