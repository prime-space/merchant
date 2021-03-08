<template>
    <div>
        <v-layout row wrap>
            <v-flex xs6></v-flex>
            <v-flex xs6>
                <v-select :items="shops" v-model="shopId" :label="translates.shopSelectLabel" single-line
                          @change="changeShopSelect"></v-select>
            </v-flex>
        </v-layout>
        <v-data-table :headers="headers" :items="payments" hide-actions class="elevation-1" :loading="loading"
                      :pagination.sync="pagination" :total-items="total">
            <v-progress-linear slot="progress" color="blue" indeterminate></v-progress-linear>
            <template slot="items" slot-scope="props">
                <td>{{ props.item.id }}</td>
                <td>{{ props.item.payment }}</td>
                <td>{{ props.item.amount }}</td>
                <td>{{ props.item.methodName }}</td>
                <td>{{ props.item.createdTs }}</td>
                <td>
                    <v-tooltip bottom>
                        <v-btn icon class="mx-0" @click="$router.push(`/payment/${props.item.id}`)" slot="activator">
                            <v-icon :color="colors[props.item.notificationStatusId]">
                                fa-info-circle
                            </v-icon>
                        </v-btn>
                        {{appendTooltipByStatus(props.item.notificationStatusId)}}
                    </v-tooltip>
                </td>
            </template>
        </v-data-table>
        <div class="text-xs-center pt-2">
            <v-pagination v-model="pagination.page" :length="pages" @input="showPayments"></v-pagination>
        </div>
    </div>
</template>

<script>
    export default {
        computed: {
            pages() {
                return Math.ceil(this.total / this.rowsPerPage);
            }
        },
        data() {
            return {
                shops: [],
                payments: [],
                loading: false,
                pagination: {},
                headers: [],
                shopId: 0,
                total: 0,
                rowsPerPage: 13,
                translates: {},
                colors: {
                    [config.paymentNotificationStatuses.undefined]: 'grey',
                    [config.paymentNotificationStatuses.sending]: 'yellow',
                    [config.paymentNotificationStatuses.sent]: 'green',
                    [config.paymentNotificationStatuses.error]: 'red',
                },
            }
        },
        mounted: function () {
            this.translates = {
                shopSelectLabel: this.translate('paymentShopSelectLabel')
            };
            this.headers = [
                {text: this.translate('paymentTableHeadID'), value: 'id', sortable: false,},
                {text: this.translate('paymentTableHeadPayment'), value: 'payment', sortable: false,},
                {text: this.translate('paymentTableHeadAmount'), value: 'amount', sortable: false,},
                {text: this.translate('paymentTableHeadMethod'), value: 'methodName', sortable: false,},
                {text: this.translate('paymentTableHeadCreatedTs'), value: 'createdTs', sortable: false,},
                {}
            ];
            this.loading = true;
            Main.request(this.$http, this.$snack, 'get', '/private/shops', [], function (response) {
                for (let i in response.body.shops) {
                    if (0 === this.shopId) {
                        this.shopId = response.body.shops[i].id;
                    }
                    this.shops.push({text: response.body.shops[i].name, value: response.body.shops[i].id});
                }
                if (0 !== this.shopId) {
                    this.showPayments(1);
                } else {
                    this.loading = false;
                }
            }.bind(this));
        },
        methods: {
            showPayments(pageId) {
                this.loading = true;
                let url = '/private/payments/' + this.shopId + '/' + this.rowsPerPage + '/' + pageId;
                Main.request(this.$http, this.$snack, 'get', url, [], function (response) {
                    this.loading = false;
                    this.payments = response.body.payments;
                    this.payments.forEach(function (payment) {
                        payment.createdTs = Main.convertTimeZone(payment.createdTs);
                    }.bind(this));
                    this.total = response.body.total;
                }.bind(this));
            },
            changeShopSelect(shopId) {
                this.shopId = shopId;
                this.showPayments(1);
            },
            appendTooltipByStatus(statusId) {
                if (statusId === config.paymentNotificationStatuses.error) {
                    return this.translate('paymentStatusIconTooltipError');
                } else {
                    return this.translate('paymentStatusIconTooltipInfo');
                }
            }
        }
    }
</script>

<style>
</style>
