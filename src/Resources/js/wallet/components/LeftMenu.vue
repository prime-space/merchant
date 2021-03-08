<template>
    <div>
        <div v-if="mobile === false" class="container">
            <navigation :list-items="listItems"></navigation>
        </div>
        <div v-if="mobile">
            <div class="overlay" @click="close"></div>
            <div class="left-menu__container" @click="close">
                <navigation :list-items="listItems"></navigation>
            </div>
        </div>
    </div>
</template>
<script>
    import Navigation from './Navigation.vue'

    export default {
        props: {
            mobile: {
                type: Boolean,
                default: false,
            },
        },
        components: {
            Navigation,
        },
        data() {
            return {
                listItems: [
                    {
                        icon: 'business-time',
                        text: 'Операции',
                        path: '/'
                    },
                    {
                        icon: 'minus-square',
                        text: 'Вывод',
                        path: '/payout'
                    },
                    {
                        icon: 'hand-holding-usd',
                        text: 'Ваучер',
                        path: '/voucher'
                    },
                ],
                /*listItems:[
                    {
                        icon:'plus-square',
                        text:'Пополнить баланс',
                        path:'#'
                    },
                    {
                        icon:'minus-square',
                        text:'Вывести средства',
                        path:'#'
                    },
                    {
                        icon:'hand-holding-usd',
                        text:'Запросить деньги',
                        path:'#'
                    },
                    {
                        icon:'life-ring',
                        text:'Поддержка',
                        path:'#'
                    }
                ],
                listItems2:[
                    {
                        icon:'business-time',
                        text:'Мои операции',
                        path:'/operations'
                    },
                    {
                        icon:'piggy-bank',
                        text:'Перевести деньги',
                        path:'/transfers'
                    },
                    {
                        icon:'coins',
                        text:'Как собирать деньги',
                        path:'/voucher'
                    },
                    {
                        icon:'exchange-alt',
                        text:'Настройки',
                        path:'/settings'
                    }
                ]*/
            }
        },
        methods: {
            close() {
                this.$parent.toggleLeftMenu();
            }
        }
    }
</script>
<style lang="scss" scoped>
    .container {
        margin-top: 35px;
        width: 350px;
        padding: 20px 20px 20px 35px;
        box-sizing: border-box;
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 3px 3px rgba(0, 0, 0, .03);
        align-self: flex-end;
    }

    .first {
        margin-top: -12px;
    }

    .second {
        margin-top: 37px;
    }

    .left-menu__container {
        position: fixed;
        background: #fff;
        padding: 20px;
        height: 100%;
        left: 0;
        top: 0;
        z-index: 4;
    }

    .overlay {
        position: fixed;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.45);
        top: 0;
        left: 0;
        z-index: 2;
        overflow: hidden;
    }
</style>
