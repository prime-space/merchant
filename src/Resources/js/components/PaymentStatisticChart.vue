<template>
    <div>
        <v-radio-group hide-details row v-model="switchModel" @click.native="fillData()">
            <v-radio :label="translate('paymentStatisticChartTrueLabel')" :value="true" class="noMarginBottomVRadio"></v-radio>
            <v-radio :label="translate('paymentStatisticChartFalseLabel')" :value="false"></v-radio>
        </v-radio-group>
        <LineChart v-if="dataCollection !== null" :chart-data="dataCollection"></LineChart>
    </div>
</template>

<script>
    import LineChart from './PaymentStatisticChart.js';
    export default {
        components: {
            LineChart
        },
        props: ['shopId'],
        data() {
            return {
                dataCollection: null,
                switchModel: false,
                apiChartData: null
            }
        },
        mounted() {
            this.getChartData();
        },
        methods: {
            fillData() {
                this.dataCollection = {
                    labels: this.dataForRender.intervals,
                    datasets: [{
                        type: 'bar',
                        label: this.translate('paymentStatisticChartTotalLabel'),
                        yAxisID: "y-axis-0",
                        backgroundColor: "rgba(128, 128, 128, 0.5)",
                        data: this.dataForRender.total
                    }, {
                        type: 'bar',
                        label: this.translate('paymentStatisticChartSuccessLabel'),
                        yAxisID: "y-axis-0",
                        backgroundColor: "#2d7aff",
                        data: this.dataForRender.success
                    },
                        {
                            type: 'line',
                            label: this.translate('paymentStatisticChartAmountLabel'),
                            yAxisID: "y-axis-1",
                            backgroundColor: "#b3d4fc",
                            data: this.dataForRender.amount
                        }]
                };
            },
            getChartData() {
                let url = '/private/payments/chartData';
                if (this.shopId) {
                    url += '/' + this.shopId;
                }
                Main.request(this.$http, this.$snack, 'get', url, [], function (response) {
                    this.apiChartData = response.body;
                    this.apiChartData.byMonths.intervals.forEach(function (interval, index) {
                        this.apiChartData.byMonths.intervals[index] = this.translate(`chartIntervalMonth${interval}`);
                    }.bind(this));
                    this.fillData();
                }.bind(this));
            }
        },
        computed: {
            dataForRender() {
                if (this.switchModel) {
                    return this.apiChartData.byMonths;
                } else {
                    return this.apiChartData.byDays;
                }
            }
        }
    }
</script>

<style>
    .noMarginBottomVRadio {
        margin-bottom: 0!important;
    }
</style>
