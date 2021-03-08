<template>
    <div class="navigation-group">
        <!--<h4 class="navigation-group__heading">{{title}}</h4>
        <div class="navigation-group__separator"></div>-->
        <ul style="padding:0;list-style:none;">
            <li v-for="(item,i) in listItems" :key="i" class="navigation-group__item">
                <font-awesome-icon :icon="item.icon"></font-awesome-icon>
                <router-link class='navigation-group__item__link' :to="item.path">{{item.text}}</router-link>
            </li>
        </ul>
        <div class="navigation-group__separator"></div>
        <ul style="padding:0;list-style:none;">
            <li class="navigation-group__item navigation-group__item__gray">
                <font-awesome-icon icon="running"></font-awesome-icon>
                <a class="navigation-group__item__link" href="/signOut">Выход</a>
            </li>
        </ul>
    </div>
</template>
<script>
    export default {
        props: {
            listItems: Array
        }
    }
</script>
<style lang="scss" scoped>
    .navigation-group__heading {
        margin-top: 8px;
        margin-left: -6px;
        font-family: 'Roboto', sans-serif;
        font-size: 16px;
        font-weight: bold;
        color: #D3D5D6;

        &::first-letter {
            text-transform: uppercase;
        }
    }

    .navigation-group__separator {
        width: 100px;
        height: 2px;
        position: relative;
        top: -8px;
        background: #F3F4F4;
    }

    .navigation-group__item {
        font-family: 'Roboto', sans-serif;
        font-size: 15px;
        font-weight: bold;
        margin: 0 0 21.5px 0;
        padding: 0 0 0 26px;
        position: relative;

        svg {
            position: absolute;
            left: -10px;
            top: 50%;
            transform: translateY(-50%);
            color: #65D878;
            width: 23px;
            height: 18px;
        }

        .navigation-group__item__link {
            text-decoration: none;
            color: #95989B;
            display: block;
            width: 100%;
            height: 100%;

            &:hover {
                color: #5B5F63;
            }
        }
    }

    .navigation-group__item__gray {
        svg {
            color: #D3D5D6;
        }
    }
</style>
