<template>
    <div class="container">
        <header
                @click="openDetails"
                :class="{
            'grey-bg':!backgroundColor, 
            'white-bg':backgroundColor,
            'border-bottom':active
        }"
                class="transaction-main-info"
        >
            <div :class="['transaction-type', className]">
                <font-awesome-icon :icon="icon"></font-awesome-icon>
            </div>

            <div class="transfer-to">
                <span class="transfer-to__info">{{data.description}}</span>
                <span class="transfer-to__time">Время {{time}}</span>
            </div>
            <div class="bill">
            <span :class="{'bill__sum':true,'red-text':!sumColor,'green-text':sumColor}">
            <b v-if="positiveTransaction">+</b> 
            {{data.amount}}  {{data.currencySign}}
            </span>
                <!--<span class="bill__commision">{{billCommision}}</span>-->
            </div>
        </header>

        <slide-up-down :active="active" :duration="300">
            <div class="transaction-details">
                <!--<div class="transaction-details__item">-->
                    <!--<span class="transaction-details__item__appointment">Сумма платежа:</span>-->
                    <!--<span class="transaction-details__item__info">  {{data.amount}} {{data.currencySign}}</span>-->
                <!--</div>-->
                <!--<div class="transaction-details__item">-->
                    <!--<span class="transaction-details__item__appointment">Коммисия:</span>-->
                    <!--<span class="transaction-details__item__info"> {{data.commision}} {{data.currencySign}}</span>-->
                <!--</div>-->
                <!--<div class="transaction-details__item">-->
                    <!--<span class="transaction-details__item__info transaction-details__item__info_capitalize">Итог:</span>-->
                    <!--<span class="transaction-details__item__info"> {{total}}</span>-->
                <!--</div>-->

                <!--<div class="divider"></div>-->

                <div class="transaction-details__item">
                    <span class="transaction-details__item__appointment">Дата:</span>
                    <span class="transaction-details__item__info"> {{date}}</span>
                </div>
                <div class="transaction-details__item">
                    <span class="transaction-details__item__appointment">Номер операции:</span>
                    <span class="transaction-details__item__info"> {{data.id}}</span>
                </div>
                <!--<div class="transaction-details__item">-->
                    <!--<span class="transaction-details__item__appointment">Номер телефона получателя:</span>-->
                    <!--<span class="transaction-details__item__info"> {{data.recipientPhoneNumber}}</span>-->
                <!--</div>-->
            </div>
        </slide-up-down>
    </div>
</template>
<script>
    import SlideUpDown from 'vue-slide-up-down'
    export default {
        components: {
            SlideUpDown
        },
        props: {
            data: Object,
            number: Number
        },
        data() {
            return {
                active: false,
                className: '',
                icon: ''
            }
        },
        methods: {
            openDetails() {
                this.active = !this.active;
            }
        },
        computed: {
            time() {
                return Main.convertTimeZone(this.data.date, Main.TIMEZONE_TIME_FORMAT);
            },
            date() {
                return Main.convertTimeZone(this.data.date, Main.TIMEZONE_DETAILS_FORMAT);
            },
            sumColor() {
                return this.data.amount > 0;
            },
            positiveTransaction() {
                return this.data.amount > 0;
            },
            total() {
                return `${this.data.amount} ${this.data.currencySign}`;//`${this.data.amount + this.data.commision} ${this.data.currencySign}`;
            },
            billCommision() {
                return this.data.commision > 0 ? `${this.data.commision} ${this.data.currencySign}` : 'Без коммисии';
            },
            backgroundColor() {
                return this.number % 2 > 0;
            },
        },
        created() {
            this.className = this.data.state;
            if (this.data.state === 'process') {
                this.icon = 'exclamation';
            } else if (this.data.state === 'error') {
                this.icon = 'times'
            } else {
                this.icon = 'check'
            }
        }
    }
</script>
<style lang="scss" scoped>
    .container {
        margin: 7px 0 0;
        width: 100%;
        box-sizing: border-box;
        border: 3px solid #FBFBFB;
        font-family: 'Roboto', sans-serif;
    }

    .transaction-main-info {
        padding: 20px 14px 20px 43px;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        max-height: 56px;
        box-sizing: border-box;
        position: relative;
        vertical-align: middle;

        -webkit-user-select: none; /* Chrome all / Safari all */
        -moz-user-select: none; /* Firefox all */
        -ms-user-select: none; /* IE 10+ */
        user-select: none;

        span {
            display: block;
        }

        @media screen and (max-width: 700px) {
            padding: 25px 14px 25px 37px;
            height: auto;
        }
    }

    .transaction-type {
        position: absolute;
        top: 50%;
        left: 10px;
        transform: translateY(-50%);
        width: 18px;
        height: 18px;
        border-radius: 50%;

        svg {
            height: 10px;
            width: 10px;
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            color: #fff;
        }
    }

    .executed {
        background: #65D878;
    }

    .process {
        background: #FFC507;
    }

    .error {
        background: #FF9292;
    }

    .border-bottom {
        border-bottom: 3px solid #FBFBFB;
    }

    .grey-bg {
        background: #FBFBFB;
    }

    .white-bg {
        background: #fff;
    }

    .transfer-to__info {
        font-size: 15px;
        font-weight: bold;
        color: #5B5F63;

        &::first-letter {
            text-transform: uppercase;
        }

        @media screen and (max-width: 700px) {
            font-size: 12px;
        }

        @media screen and (max-width: 450px) {
            font-size: 12px;
        }
    }


    .transfer-to__time {
        font-size: 12px;
        font-weight: 500;
        color: #95989B;
        padding-top: 5px;
    }

    .bill {
        text-align: right;
    }

    .bill__sum {
        font-size: 18px;
        font-weight: bold;
        text-transform: uppercase;

        @media screen and (max-width: 700px) {
            font-size: 15px;
        }

        @media screen and (max-width: 450px) {
            font-size: 13px;
        }

    }

    .bill__commision {
        font-size: 12px;
        color: #95989B;
        font-weight: 500;

        &:first-letter {
            text-transform: uppercase;
        }
    }

    .red-text {
        color: #FF6E50;
    }

    .green-text {
        color: #65D878;
    }

    .transaction-details {
        padding: 8px 20px 20px 43px;
        box-sizing: border-box;
    }

    .transaction-details__item {
        margin-bottom: 5px;
    }

    .transaction-details__item__appointment {
        font-family: 'Roboto', sans-serif;
        font-size: 15px;
        font-weight: bold;
        color: #979A9D;
        margin: 3px 0;

        &::first-letter {
            text-transform: uppercase;
        }

        @media screen and (max-width: 700px) {
            font-size: 12px;
        }
    }

    .transaction-details__item__info {
        font-family: 'Roboto', sans-serif;
        font-size: 15px;
        font-weight: bold;
        color: #54565E;
        text-transform: uppercase;

        @media screen and (max-width: 700px) {
            font-size: 12px;
        }
    }

    .transaction-details__item__info_capitalize {
        text-transform: capitalize;
    }

    .divider {
        margin: 11px 0;
        width: 100%;
        height: 3px;
        background: #F8F9FA;
    }
</style>
