<template>
    <div>
        <div class="header-mobile">
            <div class="header-mobile__left">
                <div class="burger" @click="toggleLeftMenu">
                    <span class="burger__line burger__line_top"></span>
                    <span class="burger__line burger__line_mid"></span>
                    <span class="burger__line burger__line_bot"></span>
                </div>
                <div class="header__logo header__block">
                    <router-link to="/">
                        <img src="/inc/wallet/logo.png" :alt="config.siteName">
                    </router-link>
                </div>
            </div>
            <!--<div class="header-mobile__right">
                <div class="user-avatar" @click="toggleMenu"></div>
                <header-big-menu v-if="showMenu"></header-big-menu>
            </div>-->
        </div>
        <left-menu v-show="menuVisible" :mobile="true"></left-menu>
    </div>
</template>
<script>
    //import HeaderBigMenu from '@/components/HeaderBigMenu.vue'
    import LeftMenu from './LeftMenu.vue'

    export default {
        components: {
            //HeaderBigMenu,
            LeftMenu,
        },
        data() {
            return {
                config: config,
                showMenu: false,
                menuVisible: false
            }
        },
        methods: {
            // toggleMenu() {
            //     this.showMenu = !this.showMenu;
            // },
            toggleLeftMenu() {
                this.menuVisible = !this.menuVisible;
            }
        }
    }
</script>
<style lang="scss" scoped>
    .header-mobile {
        padding: 10px 20px 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .header-mobile__left, .header-mobile__right {
        display: flex;
        justify-content: space-between;
        align-items: center;
        position: relative;
    }
    .burger {
        width: 25px;
        height: 25px;
        position: relative;
        cursor: pointer;
    }
    .burger__line {
        display: block;
        width: 100%;
        height: 3px;
        background: #979A9D;
        position: absolute;
    }
    .burger__line_top {
        top: 0;
    }
    .burger__line_mid {
        top: 50%;
        transform: translateY(-50%);
    }
    .burger__line_bot {
        bottom: 0;
    }
    .header__logo {
        margin-left: 30px;

        img {
            width: 140px;

        }
    }
    .user-avatar {
        width: 40px;
        height: 40px;
        background: url('https://i.imgflip.com/sbwxx.jpg');
        border-radius: 50%;
        background-size: cover;
    }
</style>
