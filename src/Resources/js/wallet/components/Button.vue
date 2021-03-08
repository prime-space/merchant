<template>
    <div :class="['button', classHover]" :style={width:width,height:height,background:buttonBackground} @click="action"
         ref="button">
        <font-awesome-icon v-if="icon && !loading" class="button__icon" :icon="icon"></font-awesome-icon>
        <preloader v-if="loading" :size="preloaderSize"/>
        <span v-if="!loading" class="button__text"><slot>{{text}}</slot></span>
    </div>
</template>
<script>
    import Preloader from './../components/Preloader'

    export default {
        components: {
            Preloader
        },
        props: {
            text: {
                type: String,
                default: 'value'
            },
            icon: {
                type: String,
            },
            width: {
                type: Number,
                default: '140px'
            },
            height: {
                type: Number,
                default: '40px'
            },
            handler: {
                type: Function,
                default: null,
            },
            hoveroff: Boolean
        },
        data() {
            return {
                loading: false
            }
        },
        computed: {
            preloaderSize() {
                return Math.round(this.$refs.button.clientHeight / 2);
            },
            buttonBackground() {
                return this.loading ? '#F5F6F7!important' : '';
            },
            classHover() {
                return this.hoveroff ? '' : 'button__hover';
            }
        },
        methods: {
            action() {
                if (!this.loading && this.handler !== null) {
                    this.loading = true;
                    this.handler(function () {
                        this.loading = false;
                    }.bind(this));
                }
            }
        },
    }
</script>
<style lang="scss" scoped>
    .button {
        margin-left: 5px;
        margin-right: 10px;
        height: 40px;
        background: #F5F6F7;
        color: #979A9D;
        border-radius: 4px;
        text-decoration: none;
        display: flex;
        justify-content: center;
        align-items: center;
        font-family: 'Roboto', sans-serif;
        font-size: 14px;
        font-weight: bold;
        text-transform: capitalize;
        cursor: pointer;
        transition: background-color .25s;
        border: none;

        &:focus {
            outline: 0;
        }

        .button__icon {
            width: 17px;
            height: 13px;
        }

        .button__icon, .button__text {
            margin: 0 5px;
        }
    }

    .button__hover:hover {
        background: #65D878;
        color: #fff;
    }
</style>
