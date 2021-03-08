<template>
    <div class="container">
        <payout-head :method="method" :success="true"/>
        <div class="separator"></div>
        <div class="detail-info">
            <span class="detail-info__title">Подробная информация</span>
            <span class="detail-info__item">
                <span class="detail-info__item_left-block">Сумма: </span>
                <span class="detail-info__item_right-block">{{payoutData.amount}} {{payoutData.currencySign}}</span>
            </span>
            <span class="detail-info__item">
                <span class="detail-info__item_left-block">С комиссией: </span>
                <span class="detail-info__item_right-block">{{payoutData.feeAmount}} {{payoutData.currencySign}}</span>
            </span>
            <span class="detail-info__item">
                <span class="detail-info__item_left-block">Номер кошелька получателя: </span>
                <span class="detail-info__item_right-block">{{payoutData.receiver}}</span>
            </span>
        </div>
        <div class="buttons-container">
            <btn width="325px" height="55px" :handler="oneMore" icon="arrow-left">
                Сделать еще выплату
            </btn>
        </div>
    </div>
</template>
<script>
    import Btn from './../components/Button'
    import PayoutHead from './../components/PayoutHead'

    export default {
        components: {
            Btn,
            PayoutHead,
        },
        props: {
            code: String,
            payoutData: Object,
        },
        data() {
            return {
                config: config,
                method: null,
            }
        },
        created() {
            this.method = Main.getItemByProperty(this.config.payoutMethods, 'code', this.code);
        },
        methods: {
            oneMore() {
                this.$emit('oneMore')
            }
        }
    }
</script>
<style lang="scss" scoped>
    .separator {
        display: block;
        width: 100%;
        height: 3px;
        background: #F8F9FA;
    }

    .pay__heading {
        margin: -4px 0 29px;
        display: flex;
        justify-content: space-between;
        width: 100%;
        align-items: flex-end;
    }

    .pay__block_left {
        display: flex;
        align-items: flex-end;
    }

    .pay__heading__logo {
        width: 72px;
        height: 72px;
        background: #54565E;
        border-radius: 4px;

        display: flex;
        justify-content: center;
        align-items: center;

        img {
            width: 35px;
            height: 44px;
        }
    }


    .pay__text {
        margin-left: 30px;
        font-family: 'Roboto', sans-serif;

        @media screen and (max-width: 580px) {
            display: none;
        }
    }

    .pay__text__title, .pay__text__badge {
        display: block;
    }

    .pay__text__title {
        font-size: 24px;
        font-weight: bold;
        color: #5B5F63;
    }

    .pay__text__badge {
        margin: 12px 0 0;
        width: 220px;
        height: 33px;

        box-sizing: border-box;
        text-align: center;
        background: #95989B;
        border-radius: 4px;
        color: #fff;
        font-size: 15px;
        font-weight: bold;
        line-height: 33px;
    }

    .pay__text__badge_green {
        background: #65D878;
    }

    .pay__heading__icon_succes {
        position: relative;
        top: -11px;
    }

    .detail-info {
        margin-top: 10px;
        font-family: 'Roboto';
    }

    .detail-info__title {
        display: block;
        margin: 30px 0 13px;
        font-size: 24px;
        font-weight: bold;
        color: #54565E;
    }

    .detail-info__item {
        display: block;
        margin-bottom: 6px;
    }

    .detail-info__item_left-block,
    .detail-info__item_right-block {
        color: #979A9D;
        font-weight: bold;
        font-size: 15px;
    }

    .detail-info__item_right-block {
        color: #54565E;
        text-transform: uppercase;
    }

    .buttons-container {
        margin: 64px 0 0;
        display: flex;
        justify-content: space-between;
        flex-wrap: wrap;

        * {
            @media screen and (max-width: 580px) {
                margin-top: 10px;
            }
        }
    }
</style>
