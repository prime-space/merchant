<template>
    <div>
        <v-tabs fixed-tabs>
            <v-tab
                    v-for="(tab, key) in tabs"
                    :key="key"
                    ripple
            >
                {{ translate(`shopDetailsTab${tab}`) }}

            </v-tab>
            <v-tabs-items touchless>
                <v-tab-item lazy>
                    <v-container>
                        <v-data-iterator v-if="shop.id"
                                         :items="[shop]"
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
                                    <v-card-title><h4>{{translate('shopDetailsDataIteratorHeader')}}</h4>
                                    </v-card-title>
                                    <v-divider></v-divider>
                                    <v-list dense>
                                        <v-list-tile v-for="row in dataIteratorRows"
                                                     :key=row.id
                                        >
                                            <v-list-tile-content>
                                                {{ row.title}}
                                            </v-list-tile-content>
                                            <v-list-tile-content class="align-end">
                                                <v-icon v-if="props.item[row.property] === true">fa-check</v-icon>
                                                <v-icon v-else-if="props.item[row.property] === false">fa-times</v-icon>
                                                <template v-else>
                                                    {{ props.item[row.property] }}
                                                </template>
                                            </v-list-tile-content>
                                        </v-list-tile>
                                    </v-list>
                                </v-card>
                            </v-flex>
                        </v-data-iterator>
                    </v-container>
                </v-tab-item>
                <v-tab-item lazy>
                    <v-container>
                        <v-card>
                            <PaymentStatisticChart :shopId="this.$route.params.id" v-if="shop.id"></PaymentStatisticChart>
                        </v-card>
                    </v-container>
                </v-tab-item>
                <v-tab-item lazy>
                    <v-container>
                        <v-form ref="paymentMethodsForm" @submit.prevent="submitPaymentMethodsForm" v-if="shop.id">
                            <v-card>
                                <v-container grid-list-md>
                                    <v-layout wrap row v-for="item in paymentMethods" :key="item.id">
                                        <v-flex xs6>
                                            <div>
                                                <v-checkbox :name="'methodId_' + item.id"
                                                            v-model="paymentMethodsForm.data['methodId_' + item.id]"
                                                            :label="item.name"
                                                            :disabled="isPaymentMethodExcludedByRole(item.id, 'admin')"></v-checkbox>
                                            </div>
                                        </v-flex>
                                        <v-flex xs6>
                                            <div class="fee__text pa-4">
                                                <!--{{ translate('shopPaymentMethodsFormFee') }} - {{ getFee(item) }} %-->
                                            </div>
                                        </v-flex>
                                    </v-layout>
                                </v-container>
                            </v-card>
                            <v-btn type="submit" name="save" color="primary"
                                   :disabled="paymentMethodsForm.submitting">
                                {{ translate('shopDetailsSave') }}
                            </v-btn>
                        </v-form>
                    </v-container>
                </v-tab-item>
                <v-tab-item lazy>
                    <ShopPostBack :shop="shop"></ShopPostBack>
                </v-tab-item>
            </v-tabs-items>
        </v-tabs>
        <v-snackbar :timeout="6000" :top="'top'" v-model="snackbar">
            <div>
                {{ translate('snackSuccessMessage')}}
            </div>
            <v-btn flat color="white" @click.native="snackbar = false" v-lang.snackHide></v-btn>
        </v-snackbar>
    </div>
</template>

<script>
    import PaymentStatisticChart from './components/PaymentStatisticChart.vue';
    import ShopPostBack from './components/ShopPostBack.vue';
    export default {
        components: {
            PaymentStatisticChart,
            ShopPostBack,
        },
        data() {
            return {
                snackbar: false,
                tabs: ['Info', 'Statistics', 'PaymentMethods', 'PostBack'],
                shop: {excludedMethodsByUser: [], excludedMethodsByAdmin: [], personalPaymentFees: []},
                paymentMethodsForm: {
                    editId: this.$route.params.id,
                    data: {},
                    errors: {},
                    submitting: false
                },
                paymentMethods: config.paymentMethods,
                dataIteratorRows: [],
            }
        },
        mounted: function () {
            this.dataIteratorRows = [
                {id: 1, title: this.translate('shopDetailsDataIteratorHeadId'), property: 'id'},
                {id: 2, title: this.translate('shopDetailsDataIteratorHeadName'), property: 'name'},
                {id: 3, title: this.translate('shopDetailsDataIteratorHeadUrl'), property: 'url'},
                {id: 4, title: this.translate('shopDetailsDataIteratorHeadDescription'), property: 'description'},
                {id: 5, title: this.translate('shopDetailsDataIteratorHeadSuccessUrl'), property: 'successUrl'},
                {id: 6, title: this.translate('shopDetailsDataIteratorHeadFailUrl'), property: 'failUrl'},
                {id: 7, title: this.translate('shopDetailsDataIteratorHeadResultUrl'), property: 'resultUrl'},
                {id: 8, title: this.translate('shopDetailsDataIteratorHeadIsTestMode'), property: 'isTestMode'},
                {id: 9, title: this.translate('shopDetailsDataIteratorHeadIsFeeByClient'), property: 'isFeeByClient'},
                {
                    id: 10,
                    title: this.translate('shopDetailsDataIteratorHeadIsAllowedToRedefineUrl'),
                    property: 'isAllowedToRedefineUrl'
                },
                {id: 11, title: this.translate('shopDetailsDataIteratorHeadStatus'), property: 'statusName'},
                {id: 12, title: this.translate('shopDetailsDataIteratorHeadDailyLimit'), property: 'dailyStatistic'},
            ];
            this.showShop();
        },
        methods: {
            showShop() {
                let shopId = this.$route.params.id;
                Main.request(this.$http, this.$snack, 'get', `/private/shop/${shopId}`, [], function (response) {
                    this.shop = response.body;
                    let dailyAmountFormatted = Main.formatMoney(parseFloat(this.shop.dailyAmount));
                    let dailyLimitFormatted = Main.formatMoney(parseFloat(this.shop.dailyLimit));
                    let currencyLabel = this.shop.dailyStatisticCurrency;
                    this.shop.dailyStatistic = `${dailyAmountFormatted} / ${dailyLimitFormatted} ${currencyLabel}`;
                    this.openPaymentMethodsForm();
                    this.$store.commit('changeTitle', `Магазин #${this.$route.params.id}`);
                }.bind(this));
            },
            openPaymentMethodsForm() {
                this.paymentMethodsForm.errors = {};
                let formData = {};
                for (let item in this.paymentMethods) {
                    let methodId = this.paymentMethods[item].id;
                    formData['methodId_' + methodId] = !this.isPaymentMethodExcludedByUserOrAdmin(methodId);
                }
                this.paymentMethodsForm.data = formData;
            },
            isPaymentMethodExcludedByRole(paymentMethodId, role) {
                let shopProperty = '';
                switch (role) {
                    case 'user':
                        shopProperty = 'excludedMethodsByUser';
                        break;
                    case 'admin':
                        shopProperty = 'excludedMethodsByAdmin';
                        break;
                    default:
                        shopProperty = 'excludedMethodsByUser';
                }

                return (this.shop[shopProperty].indexOf(paymentMethodId) !== -1);
            },
            isPaymentMethodExcludedByUserOrAdmin(paymentMethodId) {
                let isExcludedByUser = this.isPaymentMethodExcludedByRole(paymentMethodId, 'user');
                let isExcludedByAdmin = this.isPaymentMethodExcludedByRole(paymentMethodId, 'admin');

                return isExcludedByUser || isExcludedByAdmin;
            },
            submitPaymentMethodsForm(submitEvent) {
                this.paymentMethodsForm.submitting = true;
                let shopId = this.paymentMethodsForm.editId;
                let url = '/private/shop/' + shopId + '/paymentMethods';
                Main.request(this.$http, this.$snack, 'post', url, this.paymentMethodsForm, function (response) {
                    this.showShop();
                    this.snackbar = true;
                }.bind(this));
            },
            getFee(paymentMethod) {
                return this.shop.personalPaymentFees[paymentMethod.id] || paymentMethod.fee;
            }
        }
    }
</script>

<style>
    .fee__text {
        color: rgba(0, 0, 0, .54);
    }
</style>
