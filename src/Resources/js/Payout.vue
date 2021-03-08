<template>
    <div>
        <v-btn color="primary" class="payoutPage__createButton" @click="showPayoutForm">{{this.translate('payoutCreateNewPayout')}}</v-btn>
        <v-expansion-panel>
            <v-expansion-panel-content>
                <div slot="header">{{this.translate('payoutFilterFormName')}}</div>
                <v-card>
                    <v-card-text>
                        <v-form ref="filterForm" @submit.prevent="showPayouts">
                            <v-layout row>
                                <v-flex xs6>
                                    <v-text-field
                                            v-model="filterForm.data.id"
                                            :error-messages="filterForm.errors.id"
                                            :label="translate('payoutFilterFormId')"
                                            single-line
                                    ></v-text-field>
                                </v-flex>
                                <v-flex xs6>
                                    <v-text-field
                                            v-model="filterForm.data.internalUsersId"
                                            :error-messages="filterForm.errors.internalUsersId"
                                            :label="translate('payoutFilterFormInternalUsersId')"
                                            single-line
                                    ></v-text-field>
                                </v-flex>
                            </v-layout>
                            <v-layout row>
                                <v-flex xs12>
                                    <v-autocomplete
                                            name="paymentSystem"
                                            v-model="filterForm.data.payoutMethod"
                                            :error-messages="filterForm.errors.payoutMethod"
                                            :label="translate('payoutFilterFormPayoutMethod')"
                                            :items="payoutMethodsSelect"
                                            clearable
                                    ></v-autocomplete>
                                </v-flex>
                                <!--<v-flex xs6>-->
                                    <!--<v-autocomplete-->
                                            <!--name="status"-->
                                            <!--v-model="filterForm.data.statusId"-->
                                            <!--:error-messages="filterForm.errors.statusId"-->
                                            <!--:label="translate('payoutFilterFormStatusId')"-->
                                            <!--:items="statusSelect"-->
                                            <!--clearable-->
                                    <!--&gt;</v-autocomplete>-->
                                <!--</v-flex>-->
                            </v-layout>
                            <v-layout row>
                                <v-flex xs12>
                                    <v-text-field
                                            v-model="filterForm.data.receiver"
                                            :error-messages="filterForm.errors.receiver"
                                            :label="translate('payoutFilterFormReceiver')"
                                            single-line
                                    ></v-text-field>
                                </v-flex>
                            </v-layout>
                            <v-layout :class="{[$vuetify.breakpoint.smAndDown ? 'column' : 'row']: true}">
                                <v-btn color="primary" @click="showPayouts()">
                                    {{this.translate('payoutTableFilterButton')}}
                                </v-btn>
                                <v-btn color="primary" @click="clearFilterForm">
                                    {{this.translate('payoutTableClearButton')}}
                                </v-btn>
                            </v-layout>
                        </v-form>
                    </v-card-text>
                </v-card>
            </v-expansion-panel-content>
        </v-expansion-panel>
        <v-card>
            <v-data-table :headers="headers" :items="payouts" hide-actions class="elevation-1" :loading="loading"
                          :pagination.sync="pagination" :total-items="total">
                <v-progress-linear slot="progress" color="blue" indeterminate></v-progress-linear>
                <template slot="headerCell" slot-scope="props">
                    <v-tooltip bottom :disabled="!props.header.tooltip">
                        <template v-slot:activator="{ on }">
                            <span v-on="on">
                                {{ props.header.text }}
                            </span>
                        </template>
                        <span>{{ props.header.tooltip }}</span>
                    </v-tooltip>
                </template>
                <template slot="items" slot-scope="props">
                    <td>{{ props.item.id }}</td>
                    <td>{{ props.item.internalUsersId }}</td>
                    <td>{{ props.item.payoutMethodName }}</td>
                    <td>{{ props.item.receiver }}</td>
                    <td>{{ props.item.amount }}</td>
                    <!--<td>{{ props.item.fee }}</td>-->
                    <td>{{ props.item.chunks }}</td>
                    <td>{{ props.item.status }}</td>
                    <td>{{ props.item.created }}</td>
                </template>
            </v-data-table>
        </v-card>
        <div class="text-xs-center pt-2">
            <v-pagination v-model="pagination.page" :length="pages" @input="showPayouts"></v-pagination>
        </div>
        <v-dialog v-model="payoutForm.dialog" persistent max-width="500px">
            <v-form ref="payoutForm" @submit.prevent="submitPayoutForm">
                <v-card>
                    <v-card-title>
                        <div class="headline">
                            {{ translate('payoutPayoutFormTitle') }}
                        </div>
                    </v-card-title>
                    <v-card-text>
                        <v-container grid-list-md>
                            <v-layout wrap>
                                <v-flex xs12>
                                    <v-text-field :error-messages="payoutForm.errors.receiver" name="receiver"
                                                  v-model="payoutForm.data.receiver"
                                                  :label="translate('payoutPayoutFormReceiver')"></v-text-field>
                                </v-flex>
                                <v-flex xs12>
                                    <v-select :error-messages="payoutForm.errors.method" name="method"
                                              v-model="payoutForm.data.method"
                                              :items="payoutMethodsSelect"
                                              :label="translate('payoutPayoutFormMethod')"></v-select>
                                </v-flex>
                                <v-flex xs12>
                                    <v-select :error-messages="payoutForm.errors.accountId" name="accountId"
                                              v-model="payoutForm.data.accountId"
                                              :items="userAccounts"
                                              item-text="balance"
                                              item-value="id"
                                              :label="translate('payoutPayoutFormAccount')">
                                        <template slot="selection" slot-scope="data">
                                            #{{ data.item.id }} - {{ data.item.balance }}{{ data.item.currencySign }}
                                        </template>
                                        <template slot="item" slot-scope="data">
                                            #{{ data.item.id }} - {{ data.item.balance }}{{ data.item.currencySign }}
                                        </template>
                                    </v-select>
                                </v-flex>
                                <v-flex xs12>
                                    <v-text-field :error-messages="payoutForm.errors.amount" name="amount"
                                                  v-model="payoutForm.data.amount"
                                                  type="number"
                                                  step="0.01"
                                                  min="0"
                                                  :label="translate('payoutPayoutFormAmount')"></v-text-field>
                                </v-flex>
                                <template v-if="!doNotAskPass">
                                    <v-flex xs12>
                                        <v-text-field
                                                :type="passwordVisible ? 'password' : 'text'"
                                                :error-messages="payoutForm.errors.password"
                                                name="password"
                                                v-model="payoutForm.data.password"
                                                :label="this.translate('payoutPayoutFormPassword')"
                                                :append-icon="passwordVisible ? 'visibility' : 'visibility_off'"
                                                @click:append="() => (passwordVisible = !passwordVisible)"
                                        ></v-text-field>
                                    </v-flex>
                                    <v-flex xs12>
                                        <v-checkbox :error-messages="payoutForm.errors.rememberPassword"
                                                    name="rememberPassword"
                                                    v-model="payoutForm.data.rememberPassword"
                                                    :label="this.translate('payoutPayoutFormRememberPassword')">
                                        </v-checkbox>
                                    </v-flex>
                                </template>
                            </v-layout>
                        </v-container>
                        <div class="error--text" v-if="payoutForm.errors.form">
                            {{ payoutForm.errors.form }}
                        </div>
                    </v-card-text>
                    <v-card-actions>
                        <v-spacer></v-spacer>
                        <v-btn color="blue darken-1" flat @click.native="payoutForm.dialog = false">Закрыть</v-btn>
                        <v-btn type="submit" name="save" color="blue darken-1" flat
                               :disabled="payoutForm.submitting">
                            Сохранить
                        </v-btn>
                    </v-card-actions>
                </v-card>
            </v-form>
        </v-dialog>
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
                doNotAskPass: config.userSettings.doNotAskPass,
                userAccounts: config.accounts,
                passwordVisible: true,
                filterForm: {
                    data: {
                        id: '',
                        internalUsersId: '',
                        payoutMethod: '',
                        receiver: '',
                        // statusId: ''
                    },
                    errors: {}
                },
                payoutForm: {
                    data: {
                        receiver: '',
                        method: '',
                        accountId: '',
                        amount: '',
                        rememberPassword: false,
                        password: ''
                    },
                    errors: {},
                    submitting: false,
                    dialog: false
                },
                // statusSelect: [],
                payoutMethodsSelect: [],
                payouts: [],
                loading: false,
                pagination: {page: 1},
                headers: [],
                total: 0,
                rowsPerPage: 13,
            }
        },
        mounted: function () {
            this.headers = [
                {text: this.translate('payoutTableHeadID'), value: 'id', sortable: false,},
                {text: this.translate('payoutTableHeadInternalUsersId'), value: 'internalUsersId', sortable: false,},
                {text: this.translate('payoutTableHeadPaymentSystem'), value: 'payoutMethodName', sortable: false,},
                {text: this.translate('payoutTableHeadReceiver'), value: 'receiver', sortable: false,},
                {text: this.translate('payoutTableHeadAmount'), value: 'amount', sortable: false,},
                // {text: this.translate('payoutTableHeadFee'), value: 'fee', sortable: false,},
                {text: this.translate('payoutTableHeadParts'), tooltip: this.translate('payoutTableHeadPartsTooltip'), value: 'chunks', sortable: false,},
                {text: this.translate('payoutTableHeadStatus'), value: 'status', sortable: false,},
                {text: this.translate('payoutTableHeadCreated'), value: 'created', sortable: false,},
            ];
            this.showPayouts();
        },
        methods: {
            showPayouts(pageId = 1) {
                this.pagination.page = pageId;
                this.loading = true;
                let url = '/private/payouts/' + this.rowsPerPage + '/' + pageId;
                Main.request(this.$http, this.$snack, 'post', url, this.filterForm, function (response) {
                    this.loading = false;
                    // this.statusSelect = response.body.statuses;
                    this.payoutMethodsSelect = response.body.payoutMethods;
                    this.payouts = response.body.payouts;
                    this.payouts.forEach(function (payout) {
                        payout.created = Main.convertTimeZone(payout.created);
                    }.bind(this));
                    this.total = response.body.total;
                }.bind(this), function () {
                    this.loading = false;
                }.bind(this));
            },
            clearFilterForm() {
                for (let property in this.filterForm.data) {
                    this.filterForm.data[property] = '';
                }
                this.showPayouts();
            },
            submitPayoutForm() {
                this.payoutForm.submitting = true;
                let url = '/private/payout';
                Main.request(this.$http, this.$snack, 'post', url, this.payoutForm, function (response) {
                    this.payoutForm.dialog = false;
                    let accountToChargeMoneyFrom = config.accounts.find(x => x.id === this.payoutForm.data.accountId);
                    accountToChargeMoneyFrom.balance = (accountToChargeMoneyFrom.balance - response.body.credit).toFixed(2);
                    if (this.payoutForm.data.rememberPassword) {
                        this.doNotAskPass = true;
                    }
                    this.showPayouts();
                }.bind(this), function (errors) {
                    if (errors.password !== undefined) {
                        this.doNotAskPass = false;
                    }
                }.bind(this));
            },
            showPayoutForm() {
                this.payoutForm.data = {rememberPassword: false};
                this.payoutForm.errors = {};
                this.payoutForm.dialog = true;
            }
        },
        watch: {
            'filterForm.data': {
                handler: function (newValue, oldValue) {
                    for (let property in newValue) {
                        if (typeof newValue[property] === 'undefined') {
                            this.filterForm.data[property] = '';
                        }
                    }
                },
                deep: true
            },
        }
    }
</script>

<style>
    .payoutPage__createButton {
        margin: 0 0 10px;
    }
</style>
