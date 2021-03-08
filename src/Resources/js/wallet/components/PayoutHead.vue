<template>
    <div class="pay__heading">
        <div class="pay__block_left">
            <div class="pay__heading__logo">
                <img :src="logo" alt="">
            </div>
            <div class="pay__text">
                <span class="pay__text__title">Вывод средств</span>
                <span class="pay__text__badge">{{method.name}}</span>
            </div>
        </div>
        <btn v-if="!success" class="pay__close"
             :handler="back"
             width="115px"
             height="72px"
             icon="arrow-left"
        >назад
        </btn>
        <div v-if="success" class="pay__heading__icon_success">
            <img src="/inc/wallet/successPayout.png" alt="">
        </div>
    </div>
</template>
<script>
    import Btn from './../components/Button';

    export default {
        components: {
            Btn
        },
        props: {
            method: Object,
            success: {
                type: Boolean,
                default: false,
            },
        },
        methods: {
            back() {
                this.$emit('back');
            },
        },
        computed: {
            logo() {
                return '/inc/wallet/payout/' + this.method.code + '.svg';
            },
        }
    }
</script>
<style lang="scss" scoped>
    .pay__heading {
        margin: -4px 0 29px;
        display: flex;
        justify-content: space-between;
        width: 100%;
        align-items: flex-end;
    }

    .pay__close {
        margin: 0;
    }

    .pay__block_left {
        display: flex;
        align-items: flex-end;
        /*align-items: center;*/
    }

    .pay__heading__logo {
        width: 72px;
        height: 72px;
        /*background: #54565E;*/
        border-radius: 4px;
        text-align: center;
        display: flex;
        justify-content: center;
        align-items: center;

        img {
            width: 35px;
            height: 44px;
        }
    }

    .pay__text {
        margin-left: 33px;
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

    .pay__heading__icon_success {
        position: relative;
        top: -11px;
    }
</style>
