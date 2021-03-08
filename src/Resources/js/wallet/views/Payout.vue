<template>
    <div>
        <div v-if="isPayoutMethodsShow">
            <div class="heading">
                <span class="heading__title">Вывод средств</span>
                <span class="heading__subtitle">Выберите метод</span>
            </div>
            <div class="payout-methods__container">
                <payout-method
                        v-for="(item,i) in config.payoutMethods"
                        @showPayoutForm="showPayoutForm"
                        :key="i"
                        :title="item.name"
                        :code="item.code"
                />
            </div>
        </div>
        <payout-form v-if="isPayoutFormShow" :code="code" @back="hidePayoutForm" @success="showPayoutSuccess"/>
        <payout-success v-if="isPayoutSuccessShow" :code="code" :payoutData="payoutData" @oneMore="hidePayoutSuccess"/>
    </div>
</template>
<script>
    import PayoutMethod from './../components/PayoutMethod'
    import PayoutForm from './../components/PayoutForm'
    import PayoutSuccess from './../components/PayoutSuccess'

    export default {
        components: {
            PayoutMethod,
            PayoutForm,
            PayoutSuccess,
        },
        data() {
            return {
                config: config,
                isPayoutMethodsShow: true,
                isPayoutFormShow: false,
                isPayoutSuccessShow: false,
                code: null,
                payoutData: null,
            }
        },
        methods: {
            hidePayoutForm() {
                this.isPayoutFormShow = false;
                this.isPayoutMethodsShow = true;
                this.isPayoutSuccessShow = false;
            },
            showPayoutForm(code) {
                this.code = code;
                this.isPayoutFormShow = true;
                this.isPayoutMethodsShow = false;
                this.isPayoutSuccessShow = false;
            },
            showPayoutSuccess(payoutData) {
                this.payoutData = payoutData;
                this.isPayoutFormShow = false;
                this.isPayoutMethodsShow = false;
                this.isPayoutSuccessShow = true;
            },
            hidePayoutSuccess() {
                this.isPayoutFormShow = false;
                this.isPayoutMethodsShow = true;
                this.isPayoutSuccessShow = false;
            },
        }
    }
</script>
<style lang="scss" scoped>
    .payout-methods__container {
        display: flex;
        justify-content: space-between;
        margin-top: 35px;

        flex-wrap: wrap;

        @media screen and (max-width: 700px) {
            justify-content: space-around;
        }
    }

    .heading {
        font-family: 'Roboto', sans-serif;
        margin-top: -2px;
    }

    .heading__title, .heading__subtitle {
        display: block;
        font-weight: bold;
    }

    .heading__title {
        font-size: 24px;

        color: #5B5F63;
        padding-left: 4px;
    }

    .heading__subtitle {
        padding-top: 3px;
        padding-left: 4px;
        font-size: 15px;
        color: #95989B;
    }
</style>
