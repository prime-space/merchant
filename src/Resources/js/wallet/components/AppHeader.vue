<template>
    <div class="header">
        <div class="header__logo header__block">
            <router-link to="/">
                <img src="/inc/wallet/logo.png" :alt="config.siteName">
            </router-link>

        </div>

        <nav class="header__navigation header__block">
            <!--<a href="/signOut" class="header__navigation__link">Выход</a>-->
            <router-link v-for="(link,i) in headerLinks"
                         :to="link.path"
                         :key="i"
                         tag="a"
                         class="header__navigation__link">
                {{link.text}}
            </router-link>
        </nav>

        <!--<header-menu
            :avatar="user.avatar" 
            :phone-number="user.phoneNumber"
            :amount="user.amount"
            :currency="user.currency"
        />-->

    </div>
</template>
<script>
    //import HeaderMenu from '@/components/HeaderMenu.vue';

    export default {
        name: 'AppHeader',
        components: {
            //HeaderMenu
        },
        data() {
            return {
                config: config,
                headerLinks: [
                    /*{
                        text:'Платежи',
                        path:'/'
                    },
                    {
                        text:'Переводы',
                        path:'/transfers'
                    },
                    {
                        text:'Пополнить кошелек',
                        path:'/voucher'
                    },
                    {
                        text:'Настройки',
                        path:'/settings'
                    },*/
                ],
                /*user:{
                    amount:'11500',
                    currency:'rub',
                    status:'прохожий',
                    avatar:'https://i.imgflip.com/sbwxx.jpg',
                    phoneNumber:'+7 964 522-28-56'
                },*/
            }
        }
    }

</script>
<style lang="scss" scoped>
    .header {
        margin: -5px auto 25px;
        width: 1110px;
        background: #F8F9FA;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .header__logo {
        margin-left: 10px;
        margin-top: 3px;
    }

    .header__navigation {
        margin-left: 100px;
    }

    .header__navigation__link {
        margin: 0 0 0 30px;
        font-family: 'Roboto';
        text-decoration: none;
        color: #979A9D;
        font-size: 14px;
        font-weight: bold;

        &::first-letter {
            text-transform: uppercase;
        }

        &:hover {
            color: #5B5F63;
        }
    }
</style>
