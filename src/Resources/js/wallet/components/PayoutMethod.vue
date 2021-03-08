<template>
    <div>
        <div class="payout-method__container">
            <div class="payout-method__icon">
                <img class="payout-method__icon__image" :src="src" alt="">
            </div>

            <div class="payout-method__title">
                <span>{{title}}</span>
            </div>

            <div class="button-container" @click.prevent="select">
                <btn width="111px">перевести</btn>
            </div>
        </div>
    </div>
</template>
<script>
    import Btn from './../components/Button'

    export default {
        components: {
            Btn
        },
        props: {
            code: String,
            title: String
        },
        methods: {
            select() {
                this.$emit('showPayoutForm', this.code);
            }
        },
        computed: {
            src() {
                return '/inc/wallet/payout/' + this.code + '.svg';
            }
        }
    }
</script>
<style lang="scss" scoped>
    .payout-method__container {
        height: 194px;
        width: 158px;
        padding: 18px 21px;
        box-sizing: border-box;
        border: 3px solid #F8F9FA;
        transition: .3s;
        border-radius: 4px;

        &:hover {
            border-color: #65D878;
            box-shadow: 0 12px 6px rgba(101, 216, 120, .2);
        }

        @media screen and (max-width: 1170px) {
            margin: 10px auto;
        }
    }

    .payout-method__icon {
        margin: 0 auto;
        width: 60px;
        text-align: center;
        box-sizing: border-box;
        border-bottom: 3px solid #F8F9FA;
    }

    .payout-method__icon__image {
        width: 34px;
        height: 45px;
        padding-bottom: 13px;
    }

    .payout-method__title {
        margin: 11px 0 0;
        font-family: 'Roboto', sans-serif;
        font-size: 15px;
        font-weight: bold;
        color: #979A9D;
        text-align: center;
    }

    .button-container {
        margin-top: 20px;
    }
</style>
