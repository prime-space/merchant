<template>
    <div>
        <confirm-dialog/>
        <v-card>
            <v-card-text>
                <div v-lang.shopLimitTooltip></div>
                <div><span v-lang.shopLimitTooltipCounter></span> <b>{{ shopDailyLimitCounterPretty }}</b></div>
            </v-card-text>
        </v-card>
        <v-data-table :headers="headers" :items="shops" hide-actions class="elevation-1" :loading="loading">
            <v-progress-linear slot="progress" color="blue" indeterminate></v-progress-linear>
            <template slot="items" slot-scope="props">
                <td>{{ props.item.id }}</td>
                <td>{{ props.item.name }}</td>
                <td>{{ props.item.statusName }}</td>
                <td>{{ props.item.dailyStatistic }}</td>
                <td class="justify-center layout px-0">
                    <v-tooltip bottom>
                        <v-btn v-if="props.item.statusId === 1" icon class="mx-0"
                               @click.native.prevent="toChecking(props.item.id)" slot="activator">
                            <v-icon color="teal">visibility</v-icon>
                        </v-btn>
                        {{ translate('shopTableToCheckingTooltip') }}
                    </v-tooltip>
                    <v-tooltip bottom>
                        <v-btn icon class="mx-0" @click="openForm(props.item.id)" slot="activator">
                            <v-icon color="teal">edit</v-icon>
                        </v-btn>
                        {{ translate('shopTableToEditTooltip') }}
                    </v-tooltip>
                    <v-tooltip bottom>
                        <v-btn icon class="mx-0" @click="$router.push(`/shop/${props.item.id}`)" slot="activator">
                            <v-icon color="teal">
                                fa-info-circle
                            </v-icon>
                        </v-btn>
                        {{ translate('shopTableInfoTooltip') }}
                    </v-tooltip>
                </td>
            </template>
        </v-data-table>
        <v-btn color="primary" dark slot="activator" @click="openForm(null)">Добавить</v-btn>
        <v-dialog v-model="form.dialog" persistent max-width="500px">
            <v-form ref="form" @submit.prevent="submit">
                <v-card>
                    <v-card-title>
                        <div class="headline">
                            {{ translate('shopTitle') }}
                        </div>
                    </v-card-title>
                    <v-card-text>
                        <v-container grid-list-md>
                            <v-layout wrap>
                                <v-flex xs12>
                                    <v-text-field :error-messages="form.errors.name" name="name"
                                                  v-model="form.data.name" label="Название"></v-text-field>
                                </v-flex>
                                <v-flex xs12>
                                    <v-text-field :error-messages="form.errors.url" name="url"
                                                  v-model="form.data.url" label="URL (Не изменяемый)"></v-text-field>
                                </v-flex>
                                <v-flex xs12>
                                    <v-text-field :error-messages="form.errors.description" name="description"
                                                  v-model="form.data.description" label="Описание"></v-text-field>
                                </v-flex>
                                <v-flex xs12>
                                    <v-text-field :error-messages="form.errors.secret" name="secret"
                                                  v-model="form.data.secret" label="Секретная фраза (скрывается)"></v-text-field>
                                </v-flex>
                                <v-flex xs12>
                                    <v-text-field :error-messages="form.errors.successUrl" name="successUrl"
                                                  v-model="form.data.successUrl"
                                                  label="Адрес перенаправления при успешной оплате"></v-text-field>
                                </v-flex>
                                <v-flex xs12>
                                    <v-text-field :error-messages="form.errors.failUrl" name="failUrl"
                                                  v-model="form.data.failUrl"
                                                  label="Адрес перенаправления при ошибке оплаты"></v-text-field>
                                </v-flex>
                                <v-flex xs12>
                                    <v-text-field :error-messages="form.errors.resultUrl" name="resultUrl"
                                                  v-model="form.data.resultUrl"
                                                  label="Адрес отправки уведомлений"></v-text-field>
                                </v-flex>
                                <v-flex xs12>
                                    <v-checkbox :error-messages="form.errors.isFeeByClient" name="isFeeByClient"
                                                v-model="form.data.isFeeByClient"
                                                label="Комиссию оплачивает покупатель"></v-checkbox>
                                </v-flex>
                                <v-flex xs12>
                                    <v-checkbox :error-messages="form.errors.isTestMode" name="isTestMode"
                                                v-model="form.data.isTestMode" label="Тестовый режим"></v-checkbox>
                                </v-flex>
                                <v-flex xs12>
                                    <v-checkbox :error-messages="form.errors.isAllowedToRedefineUrl"
                                                name="isAllowedToRedefineUrl"
                                                v-model="form.data.isAllowedToRedefineUrl"
                                                label="Разрешить переопределение URL (В рамках домена)"></v-checkbox>
                                </v-flex>
                            </v-layout>
                        </v-container>
                    </v-card-text>
                    <v-card-actions>
                        <v-spacer></v-spacer>
                        <v-btn color="blue darken-1" flat @click.native="form.dialog = false">Закрыть</v-btn>
                        <v-btn type="submit" name="save" color="blue darken-1" flat :disabled="form.submitting">
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
        data() {
            return {
                shops: [],
                headers: [],
                paymentMethodFee: null,
                loading: false,
                form: {editId: null, data: {}, errors: {}, submitting: false, dialog: false},
                shopDailyLimitCounter: 5000,
            }
        },
        mounted: function () {
            this.headers = [
                {text: 'ID', value: 'id'},
                {text: this.translate('shopTableHeadName'), value: 'name'},
                {text: this.translate('shopTableHeadStatus'), value: 'status'},
                {text: this.translate('shopTableHeadDailyLimit'), value: 'dailyStatistic'},
                {value: 'control'},
            ];
            this.showShops();
            this.shopDailyLimitCounter = 10;
            setInterval(function(){
                if (this.shopDailyLimitCounter === 0) {
                    this.shopDailyLimitCounter = 86399;
                    this.shops.forEach(function(shop, index, shops) {
                        shop['dailyStatistic'] = this.compileDailyLimitView('0', shop['dailyLimit'], shop['dailyStatisticCurrency']);
                    }.bind(this));
                } else {
                    this.shopDailyLimitCounter--;
                }
            }.bind(this), 1000);
        },
        computed: {
            shopDailyLimitCounterPretty() {
                let hours = parseInt(this.shopDailyLimitCounter / 3600);
                let minutes = parseInt(this.shopDailyLimitCounter % 3600 / 60);
                let seconds = this.shopDailyLimitCounter % 60;

                return ('00'+hours).slice(-2)+":"+('00'+minutes).slice(-2)+":"+('00'+seconds).slice(-2);
            },
        },
        methods: {
            compileDailyLimitView(amount, limit, currencyLabel) {
                let dailyAmountFormatted = Main.formatMoney(parseFloat(amount));
                let dailyLimitFormatted = Main.formatMoney(parseFloat(limit));

                return `${dailyAmountFormatted} / ${dailyLimitFormatted} ${currencyLabel}`;
            },
            showShops() {
                this.loading = true;
                Main.request(this.$http, this.$snack, 'get', '/private/shops', [], function (response) {
                    this.loading = false;
                    this.shopDailyLimitCounter = response.body.resetInterval;
                    this.shops = response.body.shops;
                    this.shops.forEach(function(shop, index, shops) {
                        shop['dailyStatistic'] = this.compileDailyLimitView(shop['dailyAmount'], shop['dailyLimit'], shop['dailyStatisticCurrency']);
                    }.bind(this));
                }.bind(this));
            },
            openForm(editId) {
                this.form.data = {isTestMode: false, isFeeByClient: false, isAllowedToRedefineUrl: false};
                this.form.errors = {};
                this.form.editId = editId;
                if (editId !== null) {
                    let shop = Main.getItemByProperty(this.shops, 'id', editId);
                    this.form.data.name = shop.name;
                    this.form.data.url = shop.url;
                    this.form.data.description = shop.description;
                    this.form.data.secret = shop.secret;
                    this.form.data.successUrl = shop.successUrl;
                    this.form.data.failUrl = shop.failUrl;
                    this.form.data.resultUrl = shop.resultUrl;
                    this.form.data.isFeeByClient = shop.isFeeByClient;
                    this.form.data.isTestMode = shop.isTestMode;
                    this.form.data.isAllowedToRedefineUrl = shop.isAllowedToRedefineUrl;
                } else {
                    let chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
                    this.form.data.secret = '';
                    for (let i = 0; i < 64; i++) {
                        this.form.data.secret += chars.charAt(Math.floor(Math.random() * chars.length));
                    }
                }
                this.form.dialog = true;
            },
            submit(submitEvent) {
                this.form.submitting = true;
                let url = '/private/shop';
                if (null !== this.form.editId) {
                    url = url + '/' + this.form.editId;
                }
                Main.request(this.$http, this.$snack, 'post', url, this.form, function (response) {
                    this.form.dialog = false;
                    this.showShops();
                }.bind(this));
            },
            toChecking(shopId) {
                let url = '/private/shop/' + shopId + '/toChecking';
                this.$store.dispatch('confirmer/ask', {
                    title: 'Отправить магазин на проверку?',
                    body: 'Это займет некоторое время',
                })
                    .then(confirmation => {
                        if (confirmation) {
                            Main.request(this.$http, this.$snack, 'post', url, {}, function (response) {
                                this.showShops();
                            }.bind(this));
                        }
                    })
            },
        }
    }
</script>

<style>
</style>
