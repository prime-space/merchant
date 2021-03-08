<template>
    <div class="activation-code-block">
        <div class="activation-code-block__heading">Применить ваучер</div>
        <div class="activation-code-block__subheading">Введите ключ для применения ваучера!</div>

        <alert v-if="successAlert" type="success" class="voucher__alert">Код применен, средства скоро будут зачислены на ваш баланс!</alert>
        <alert v-if="errorMessage != null" type="error" class="voucher__alert">{{errorMessage}}</alert>

        <div class="activation-code-block__form">
            <input type="text" class="activation-code-block__form__input" placeholder="ключ" ref="keyInput">
            <btn class="activation-code-block__form__button" width="160px" height="55px" :handler="applyVoucher">Применить</btn>
        </div>
    </div>
</template>
<script>
    import Btn from './../components/Button'
    import Alert from './../components/Alert'
    export default {
        components: {
            Btn,
            Alert
        },
        data() {
            return {
                form: null,
                successAlert: false,
            }
        },
        created() {
            this.form = Main.initForm([]);
        },
        computed: {
            errorMessage() {
                return this.form.errors.key === undefined ? null : this.form.errors.key;
            },
        },
        methods: {
            applyVoucher(after) {
                this.successAlert = false;
                let url = '/private/voucher';
                this.form.data.key = this.$refs.keyInput.value;
                Main.request(this.$http, this.$snack, 'post', url, this.form, function (response) {
                    this.successAlert = true;
                    after();
                }.bind(this), function (errors) {
                    after();
                }.bind(this));
            }
        },
    }
</script>
<style lang="scss" scoped>
    .activation-code-block {
        padding: 25px;
        box-sizing: border-box;
        width: 100%;
        background: #54565E;
        border-radius: 4px;
        font-family: 'Roboto', sans-serif;
    }

    .activation-code-block__heading {
        font-size: 24px;
        color: #fff;
        font-weight: bold;
    }

    .activation-code-block__subheading {
        padding-top: 15px;
        font-size: 15px;
        color: #fff;
        font-weight: bold;
    }

    .activation-code-block__form {
        display: flex;
        margin-top: 19px;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
    }

    .activation-code-block__form__input {
        display: block;
        text-indent: 20px;
        width: 449px;
        height: 55px;
        background: #fff;
        border: none;
        outline: none;
        border-radius: 4px;
        font-size: 15px;

        &::placeholder {
            font-size: 15px;
            font-weight: bold;
            color: rgba(84, 86, 94, 0.25);
        }

        @media screen and (max-width: 820px) {
            margin: 10px auto;
            width: 100%;
        }
    }

    .activation-code-block__form__button {
        width: 170px;
        height: 55px;
        background: #fff;
        font-size: 16px;
        font-weight: bold;
        color: #54565E;
        border: none;
        border-radius: 4px;
        cursor: pointer;

        @media screen and (max-width: 820px) {
            margin: 10px auto;
            width: 100%!important;
        }
    }

    .voucher__alert {
        margin-top: 19px;
    }
</style>
