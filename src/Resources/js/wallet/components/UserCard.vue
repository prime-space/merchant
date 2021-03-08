<template>
    <div class="user-card">
        <header class="user-card__header">
            <div class="account-info">
                <div class="account-info__balance">
                    <img class="account-info__balance__icon " src="/inc/wallet/wallet.png" alt="wallet-icon">

                    <div class="account-info__balance__text">
                        <div>Ваш баланс (#{{config.accounts[0].id}}):</div>
                        <span>{{balance}} {{config.accounts[0].currencySign}}</span>
                    </div>
                </div>
                <!--<div class="account-info__avatar desktop" :style="{background:user.avatar}">
                    <img :src="user.avatar" alt="Аватар">
                </div>-->
            </div>

            <!--<div class="wallet-status">
                <img class="wallet-status__icon" src="../assets/alert.png" alt="">     
                <div class="wallet-status__text">
                    <span>Статус вашего кошелька: </span> <span class="wallet-status__text_bold">{{user.status}}</span>
                </div>
            </div>-->

        </header>

        <div class="buttons-container">
            <form class="buttons-container__lkModeForm" method="post" action="/private/lkMode/merchant" ref="goToMerchantForm">
                <input type="hidden" name="_token" :value="config.token">
                <btn icon="store" :handler="goToMerchant">мерчант</btn>
            </form>
        </div>
    </div>
</template>
<script>
    import Btn from './Button'

    export default {
        components: {
            Btn
        },
        computed: {
            balance() {
                return Main.formatMoney(parseFloat(this.config.accounts[0].balance));
            }
        },
        data() {
            return {
                config: config,
            }
        },
        methods: {
            goToMerchant(after) {
                this.$refs.goToMerchantForm.submit();
            }
        },
    }
</script>
<style lang="scss" scoped>
    .user-card {
        width: 350px;
        border-radius: 8px;
        box-sizing: border-box;
        background: #fff;

        box-shadow: 0px 3px 3px rgba(0, 0, 0, 0.03);

        @media screen and (max-width: 1170px) {
            width: 100%;
            height: auto;
        }
    }

    .user-card__header {
        border-top-left-radius: 8px;
        border-top-right-radius: 8px;
        padding: 25px 20px;
        background: url('/inc/wallet/green-gradient.png');
        background-size: cover;

    }

    .account-info {
        display: flex;
        justify-content: space-between;

        @media screen and (max-width: 1170px) {
            width: 100%;
            justify-content: center;
        }
    }

    .account-info__avatar {
        margin-right: 8px;
        margin-top: -3px;
        width: 50px;
        height: 50px;
        background: #fff;
        border-radius: 50%;
        overflow: hidden;
        border: 3px solid #fff;
        box-sizing: border-box;

        img {
            object-fit: cover;
            object-position: 0% 50%;
            width: 100%;
            height: 100%;
        }
    }

    .account-info__balance {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .account-info__balance__text {
        margin-left: 15px;
        font-size: 12px;
        font-family: 'Roboto', sans-serif;
        font-weight: 500;
        color: #fff;

        span {
            font-size: 24px;
            font-weight: bold;
            text-transform: uppercase;

        }
    }

    .wallet-status {
        margin: 14px auto 0;
        margin-top: 13px;
        width: 300px;
        height: 31px;
        box-shadow: 0px 3px 3px rgba(0, 0, 0, 0.03);
        line-height: 31px;
        border-radius: 4px;
        background: rgba(255, 255, 255, 0.15);
        text-align: center;
        color: #fff;
        font-family: 'Roboto', sans-serif;
        font-size: 14px;
        position: relative;

        @media screen and (max-width: 1170px) {
            width: 80%;
            height: auto;
            padding: 0 10px;
        }

        @media screen and (max-width: 700px) {
            display: none;
        }
    }

    .wallet-status__icon {
        width: 14px;
        height: 16px;
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        margin-top: 0px;
        left: 13px;

        @media screen and (max-width: 335px) {
            left: 10px;
        }
    }

    .wallet-status__text {
        text-align: center;
        padding-left: 15px;
        padding-top: 2px;
    }

    .wallet-status__text_bold {
        text-transform: capitalize;
        font-weight: bold;
    }

    .buttons-container {
        margin: 0 auto;
        width: 350px;
        box-sizing: border-box;
        padding: 17px;
        display: flex;
        justify-content: space-between;

        @media screen and (max-width: 470px) {
            width: 100%;
        }
    }

    .buttons-container__lkModeForm {
        margin: auto;
    }
</style>
