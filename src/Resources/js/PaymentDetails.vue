<template>
    <div>
        <v-tabs fixed-tabs>
            <v-tab
                    v-for="(tab, key) in tabs"
                    :key="key"
                    ripple
            >
                {{ translate(`paymentDetailsTab${tab}`) }}

            </v-tab>
            <v-tabs-items touchless>
                <v-tab-item>
                    <v-container>
                        <v-data-iterator
                                :items="payment"
                                content-tag="v-layout"
                                hide-actions
                                row
                                wrap
                        >
                            <v-flex
                                    slot="item"
                                    slot-scope="props"
                            >
                                <v-card>
                                    <v-list dense>
                                        <v-list-tile v-for="row in dataIteratorRows"
                                                     :key=row.id
                                        >
                                            <v-list-tile-content class="pr-3 show-overflow">
                                                {{ translate(`paymentDetailsDataIteratorTitle${row.title}`) }}
                                            </v-list-tile-content>
                                            <v-list-tile-content class="align-end">
                                                {{ props.item[row.property] }}
                                            </v-list-tile-content>
                                        </v-list-tile>
                                    </v-list>
                                </v-card>
                            </v-flex>
                        </v-data-iterator>
                    </v-container>
                </v-tab-item>
                <v-tab-item>
                    <v-container>
                        <v-data-table :headers="headers" :items="notifications" hide-actions class="elevation-1"
                                      :loading="loading">
                            <v-progress-linear slot="progress" color="blue" indeterminate></v-progress-linear>
                            <template slot="items" slot-scope="props">
                                <tr @click="">
                                    <td>{{ props.item.created }}</td>
                                    <td>{{ props.item.status }}</td>
                                    <td>{{ props.item.httpCode }}</td>
                                    <td>{{ props.item.result }}</td>
                                </tr>
                            </template>
                        </v-data-table>
                    </v-container>
                </v-tab-item>
            </v-tabs-items>
        </v-tabs>
    </div>
</template>

<script>
    export default {
        data() {
            return {
                headers: [],
                tabs: ['Info', 'Notifications'],
                payment: [],
                notifications: [],
                loading: false,
                dataIteratorRows: [
                    {id: 1, title: 'Id', property: 'id'},
                    {id: 2, title: 'NotificationStatusId', property: 'notificationStatusId'},
                    {id: 3, title: 'Amount', property: 'amount'},
                    {id: 4, title: 'MethodName', property: 'methodName'},
                    {id: 5, title: 'CreatedDate', property: 'createdDate'},
                    {id: 6, title: 'Fee', property: 'fee'},
                    {id: 7, title: 'PaysFee', property: 'paysFee'},
                    {id: 8, title: 'Credit', property: 'credit'},
                    {id: 9, title: 'Description', property: 'description'},
                    {id: 10, title: 'SuccessDate', property: 'successDate'},
                    {id: 11, title: 'NotificationStatus', property: 'notificationStatus'},
                    {id: 12, title: 'Email', property: 'email'},
                ],
            }
        },
        mounted: function () {
            this.headers = [
                {text: this.translate('paymentDetailsTableHeadCreated'), value: 'created'},
                {text: this.translate('paymentDetailsTableHeadStatus'), value: 'status'},
                {text: this.translate('paymentDetailsTableHeadCode'), value: 'httpCode'},
                {text: this.translate('paymentDetailsTableHeadResult'), value: 'result'},
            ];
            this.showPayment();
        },
        methods: {
            showPayment() {
                this.loading = true;
                let paymentId = this.$route.params.id;
                let url = '/private/payment/' + paymentId;
                Main.request(this.$http, this.$snack, 'get', url, [], function (response) {
                    this.loading = false;
                    this.payment = [response.body.payment];
                    this.notifications = response.body.notifications;
                    this.notifications.forEach(function(notification) {
                        notification.created = Main.convertTimeZone(notification.created);
                    }.bind(this));
                    for (let property in this.payment[0]) {
                        if (property === 'createdDate' || property === 'successDate') {
                            this.payment[0][property] = Main.convertTimeZone(this.payment[0][property], Main.TIMEZONE_DETAILS_FORMAT);
                        }
                    }
                    this.$store.commit('changeTitle', `Операция #${this.$route.params.id}`);
                }.bind(this));
            },
        }
    }
</script>

<style>
    .v-list__tile {
        min-height: 40px;
        height: auto !important;
    }
</style>
