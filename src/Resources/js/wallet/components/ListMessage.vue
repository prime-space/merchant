<template>
    <div class="message">
        <div v-if="null !== icon" class="message__icon">
            <font-awesome-icon :icon="icon"></font-awesome-icon>
        </div>
        <span class="message__text"><slot>{{text}}</slot></span>
    </div>
</template>
<script>
    export default {
        props: {
            text: {
                type: String,
            },
            icon: {
                type: String,
                default: null
            },
        },
    }
</script>
<style lang="scss" scoped>
    .message{
        font-family: 'Roboto',sans-serif;
        background: #F8F9FA;
        padding: 26px 10px;
        position: relative;
    }
    .message__text{
        display: block;
        font-size: 15px;
        color: #979a9d;;
        text-align: center;
        font-weight: bold;
        @media screen and (max-width: 800px){
            padding-left: 46px;
        }
    }
    .message__icon{
        position: absolute;
        left:20px;
        top:50%;
        transform: translateY(-50%);
        width: 23px;
        height: 23px;
        background: #FF6E50;
        border-radius: 50%;
        color: #fff;
        svg{
            position: absolute;
            height: 15px;
            width: 15px;
            top:50%;
            left: 50%;
            transform: translate(-50%,-50%);
        }
    }
</style>
