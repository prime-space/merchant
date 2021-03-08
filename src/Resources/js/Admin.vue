<template>
    <div>
        <v-container grid-list-lg class="pt-0 pa-0">
            <v-card class="pa-4">
                <v-layout row wrap>
                    <v-flex v-if="isUserBlocked" class="pt-0 ma-0">
                        <v-alert :value="true"
                                 class="mt-1"
                                 color="error"
                        >
                            {{ this.translate('blockedUserMessage') }}
                        </v-alert>
                    </v-flex>
                </v-layout>
                <v-layout row wrap class="admin-balance">
                    <account
                            v-if="accounts.length > 0"
                            v-for="(account, key) in accounts"
                            :key="key"
                            :id="account.id"
                            :balance="account.balance"
                            :sign="account.currencySign"
                    ></account>
                    <account v-if="accounts.length === 0" balance="0.00"></account>
                </v-layout>
            </v-card>
            <v-layout>
                <v-flex class="mt-1">
                    <v-card class="pa-4">
                        <div class="headline">
                            {{ translate('adminChartTitle') }}
                        </div>
                        <shops-statistics-chart></shops-statistics-chart>
                    </v-card>
                </v-flex>
            </v-layout>
        </v-container>
    </div>
</template>

<script>
    import ShopsStatisticsChart from './components/PaymentStatisticChart.vue';
    import Account from './components/Account';
    export default {
        components: {
            ShopsStatisticsChart,
            Account,
        },
        data() {
            return {
                accounts: config.accounts,
                isUserBlocked: config.userSettings.isBlocked,
            }
        },
    }
</script>

<style>
    .admin-balance {
        max-width: 400px;
    }

    .dev-notification__link:link {
        color: #fff;
    }

    .dev-notification__link:visited {
        color: #fff;
    }

    .dev-notification__link:hover {
        color: #fff;
    }

    .dev-notification__link:active {
        color: #fff;
    }
</style>
