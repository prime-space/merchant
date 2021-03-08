<template>
    <div class="pay">
        <payout-head @back="back" :method="method"/>
        <div class="separator"></div>
        <div class="pay__form">
            <form method="POST" ref="form" name="payout">
                <div class="pay__form__flex-container">
                    <div class="pay__form__flex-container__block_left">
                        <label>
                            <span class="pay__form__label">Номер кошелька получателя</span>
                            <input type="text" name="receiver" class="pay__form__input">
                            <span class="input__error"
                                  :style="{visibility: form.errors.receiver === undefined ? 'hidden' : 'visible'}">{{form.errors.receiver}}</span>
                        </label>
                        <label>
                            <span class="pay__form__label">Введите сумму зачисления</span>
                            <input
                                    type="number"
                                    min="0"
                                    step="100"
                                    name="amount"
                                    autocomplete="off"
                                    class="pay__form__input"
                                    v-model="amount"
                                    @input="fillAmount"
                                    @change="fillAmount"
                            >
                            <span class="input__error"
                                  :style="{visibility: form.errors.amount === undefined ? 'hidden' : 'visible'}">{{form.errors.amount}}</span>
                        </label>
                        <label>
                            <span class="pay__form__label">Введите пароль от аккаунта</span>
                            <input type="password" name="password" class="pay__form__input">
                            <span class="input__error"
                                  :style="{visibility: form.errors.password === undefined ? 'hidden' : 'visible'}">{{form.errors.password}}</span>
                        </label>
                    </div>
                    <div class="pay__form__flex-container__block_right">
                        <label>
                            <span class="pay__form__label">Счет списания</span>
                            <div class=" pay__form__input_select">
                                <select class="pay__form__input" name="accountId" @change="setAccount">
                                    <option
                                            v-for="(item,i) in config.accounts"
                                            :key="i"
                                            :value="item.id"
                                    >#{{item.id}} - {{item.balance}} {{item.currencySign}}
                                    </option>
                                </select>
                            </div>
                            <span class="input__error"
                                  :style="{visibility: form.errors.accountId === undefined ? 'hidden' : 'visible'}">{{form.errors.accountId}}</span>
                        </label>
                        <label>
                            <span class="pay__form__label">Или сумму списания</span>
                            <input
                                    type="number"
                                    min="0"
                                    step="100"
                                    name="charge"
                                    autocomplete="off"
                                    class="pay__form__input"
                                    v-model="charge"
                                    @input="fillAmount"
                                    @change="fillAmount"
                            >
                            <span class="input__error"></span>
                        </label>
                    </div>
                </div>
                <div class="separator"></div>
                <div class="form__error"
                     :style="{visibility: form.errors.form === undefined ? 'hidden' : 'visible'}">
                    {{form.errors.form}}
                </div>
                <div class="submit-container">
                    <btn width="325px" height="55px" :handler="payout">
                        Перевести <span v-if="amountView != null">{{amountView}} {{currencySign}}</span>
                    </btn>
                    <div class="submit-container__commision-block">
                        <font-awesome-icon icon="check-double" style="color:#65D878" class="commision-block__icon">
                        </font-awesome-icon>
                        <span
                                class="commision-block__title"
                                :style="{visibility: feeAmount === null || feeAmount === 0 ? 'hidden' : 'visible'}"
                                v-if="">
                                + {{feeAmount}} {{currencySign}}
                            </span>
                        <span class="commision-block__subtitle">Комиссия {{feeView}}%</span>
                    </div>
                </div>
            </form>
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
        },
        data() {
            return {
                config: config,
                amount: '',
                charge: '',
                feeAmount: null,
                account: null,
                form: null,
            }
        },
        mounted() {
            this.setAccount();
        },
        created() {
            this.form = Main.initForm({});
        },
        methods: {
            back() {
                this.$emit('back');
            },
            fillAmount({target}) {
                let amount = this.amount - 0;
                let charge = this.charge - 0;
                let fee = this.method.fee - 0;
                let feeAmount = null;
                if (target.name === 'amount') {
                    feeAmount = fee / 100 * amount;
                    this.charge = parseFloat((amount + feeAmount).toFixed(2));
                } else {
                    feeAmount = fee / (100 + fee) * charge;
                    this.amount = parseFloat((charge - feeAmount).toFixed(2));
                }
                this.feeAmount = parseFloat(feeAmount.toFixed(2));
            },
            setAccount() {
                let select = this.$refs.form.accountId;
                let accountId = parseInt(select.options[select.selectedIndex].value);
                this.account = Main.getItemByProperty(this.config.accounts, 'id', accountId);
            },
            payout(after) {
                let formData = new FormData(document.forms.payout);
                formData.append('method', this.code);
                formData.delete('charge');
                for (var pair of formData.entries()) {
                    this.form.data[pair[0]] = pair[1];
                }
                let url = '/private/payout';
                Main.request(this.$http, this.$snack, 'post', url, this.form, function (response) {
                    this.account.balance = (this.account.balance - response.body.credit).toFixed(2);
                    let amount = this.amount;
                    let payoutData = {
                        amount: this.amount,
                        feeAmount: this.feeAmount,
                        currencySign: this.currencySign,
                        receiver: formData.get('receiver'),
                    };
                    this.$emit('success', payoutData);
                    after();
                }.bind(this), function (errors) {
                    after();
                }.bind(this));
            },
        },
        computed: {
            method() {
                return Main.getItemByProperty(this.config.payoutMethods, 'code', this.code);
            },
            feeView() {
                return parseFloat(this.method.fee);
            },
            amountView() {
                return this.amount === '' || this.amount === 0 ? null : this.amount;
            },
            currencySign() {
                if (this.account === null) {
                    return null;
                }
                return this.account.currencySign;
            }
        }
    }
</script>
<style lang="scss" scoped>
    .input__error {
        display: block;
        padding-left: 4px;
        font-size: 80%;
        color: #ff2727;
        min-height: 15px;
    }

    .form__error {
        line-height: 35px;
        vertical-align: middle;
        padding-left: 9px;
        font-size: 80%;
        color: #ff2727;
        min-height: 35px;
    }

    .separator {
        display: block;
        width: 100%;
        height: 3px;
        background: #F8F9FA;
    }

    .pay__form {
        margin-top: 30px;
        font-family: 'Roboto', sans-serif;
    }

    .pay__form__label {
        display: block;
        margin: 16px 0;
        font-size: 15px;
        font-weight: bold;
        padding-left: 4px;

    }

    .max-symbols {
        font-size: 11px;
        font-weight: medium;
        color: #CED0D1;
        padding-left: 10px;
    }

    .pay__form__input {
        display: block;
        width: 100%;
        height: 55px;
        box-sizing: border-box;
        background: #fff;
        border: 3px solid #F3F5F6;
        border-radius: 4px;
        outline: none;
        text-indent: 17px;
        color: #95989B;
        font-size: 15px;


        &::placeholder {
            font-size: 15px;
            text-indent: 19px;
            font-weight: bold;
            color: #95989B;

            @media screen and (max-width: 630px) {
                font-size: 13px;
                text-indent: 10px;
            }
        }
    }

    .pay__form__input_select {
        position: relative;
        cursor: pointer;

        select {
            appearance: none;
            cursor: pointer;
        }

        &:before {
            content: '';
            display: block;
            position: absolute;
            right: 17px;
            cursor: pointer;
            top: 50%;
            transform: translateY(-50%);
            border: 6px solid transparent;
            border-top: 6px solid #95989B;
            z-index: 2;
        }
    }

    .pay__form__flex-container {
        margin: 14px 0 34px;
        display: flex;
        justify-content: space-between;

        @media screen and (max-width: 830px) {
            flex-wrap: wrap;
            justify-content: center;
        }
    }

    .pay__form__flex-container__block_left,
    .pay__form__flex-container__block_right {
        width: 325px;
    }

    .submit-container {
        padding: 0 0 35px 0;
        box-sizing: border-box;
        display: flex;
        justify-content: space-between;
        align-items: center;

        @media screen and (max-width: 830px) {
            flex-wrap: wrap;
            justify-content: center;
        }
    }

    .submit-container__commision-block {
        margin: -2px 68px 0 0;
        padding: 5px 0 5px 29px;
        position: relative;
        min-width: 165px;

        @media screen and (max-width: 830px) {
            margin-top: 20px;
        }
    }

    .commision-block__icon {
        position: absolute;
        left: 0;
        top: 7px;
    }

    .commision-block__title, .commision-block__subtitle {
        display: block;
    }

    .commision-block__title {
        font-size: 15px;
        font-weight: bold;
        color: #54565E;

        @media screen and (max-width: 630px) {
            font-size: 14px;
        }
    }

    .commision-block__subtitle {
        margin-top: 5px;
        font-size: 13px;
        font-weight: bold;
        color: #95989B;


    }

    .pay__footer {
        font-family: 'Roboto', sans-serif;
        color: #979A9D;
        font-size: 15px;
        font-weight: bold;
        margin-top: 50px;
        padding-left: 39px;
        position: relative;

        @media screen and (max-width: 830px) {
            text-align: center;
        }
    }

    .pay__footer__icon {
        width: 25px;
        height: 25px;
        color: #fff;
        background: #979A9D;
        border-radius: 50%;
        top: 50%;
        left: 3px;
        transform: translateY(-50%);
        text-align: center;
        position: absolute;

        svg {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }
    }

    .offert__link {
        text-decoration: underline;
        color: #FF6E50;
    }
</style>
