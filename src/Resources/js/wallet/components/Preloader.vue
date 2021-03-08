<template>
    <div class="preloader-folding-cube long-button__preloader" :style={width:preloaderSize,height:preloaderSize}>
        <div class="preloader-cube1 preloader-cube"></div>
        <div class="preloader-cube2 preloader-cube"></div>
        <div class="preloader-cube4 preloader-cube"></div>
        <div class="preloader-cube3 preloader-cube"></div>
    </div>
</template>
<script>
    export default {
        props: {
            size: {
                type: Number,
                default: 20
            },
        },
        computed: {
            preloaderSize() {
                return this.size + 'px';
            },
        },
    }
</script>
<style lang="scss" scoped>
    .preloader-folding-cube {
        -webkit-transform: rotateZ(45deg);
        transform: rotateZ(45deg);
        position: relative;
        margin: auto;
        top: 0;
        left: 0;
        bottom: 0;
        right: 0;
    }

    .preloader-folding-cube .preloader-cube {
        float: left;
        width: 50%;
        height: 50%;
        position: relative;
        -webkit-transform: scale(1.1);
        -ms-transform: scale(1.1);
        transform: scale(1.1);
    }
    .preloader-folding-cube .preloader-cube:before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: #d9d9d9;
        -webkit-animation: preloader-foldCubeAngle 2.4s infinite linear both;
        animation: preloader-foldCubeAngle 2.4s infinite linear both;
        -webkit-transform-origin: 100% 100%;
        -ms-transform-origin: 100% 100%;
        transform-origin: 100% 100%;
    }
    .preloader-folding-cube .preloader-cube2 {
        -webkit-transform: scale(1.1) rotateZ(90deg);
        transform: scale(1.1) rotateZ(90deg);
    }
    .preloader-folding-cube .preloader-cube3 {
        -webkit-transform: scale(1.1) rotateZ(180deg);
        transform: scale(1.1) rotateZ(180deg);
    }
    .preloader-folding-cube .preloader-cube4 {
        -webkit-transform: scale(1.1) rotateZ(270deg);
        transform: scale(1.1) rotateZ(270deg);
    }
    .preloader-folding-cube .preloader-cube2:before {
        -webkit-animation-delay: 0.3s;
        animation-delay: 0.3s;
    }
    .preloader-folding-cube .preloader-cube3:before {
        -webkit-animation-delay: 0.6s;
        animation-delay: 0.6s;
    }
    .preloader-folding-cube .preloader-cube4:before {
        -webkit-animation-delay: 0.9s;
        animation-delay: 0.9s;
    }
    @-webkit-keyframes preloader-foldCubeAngle {
        0%, 10% {
            -webkit-transform: perspective(140px) rotateX(-180deg);
            transform: perspective(140px) rotateX(-180deg);
            opacity: 0;
        } 25%, 75% {
              -webkit-transform: perspective(140px) rotateX(0deg);
              transform: perspective(140px) rotateX(0deg);
              opacity: 1;
          } 90%, 100% {
                -webkit-transform: perspective(140px) rotateY(180deg);
                transform: perspective(140px) rotateY(180deg);
                opacity: 0;
            }
    }

    @keyframes preloader-foldCubeAngle {
        0%, 10% {
            -webkit-transform: perspective(140px) rotateX(-180deg);
            transform: perspective(140px) rotateX(-180deg);
            opacity: 0;
        } 25%, 75% {
              -webkit-transform: perspective(140px) rotateX(0deg);
              transform: perspective(140px) rotateX(0deg);
              opacity: 1;
          } 90%, 100% {
                -webkit-transform: perspective(140px) rotateY(180deg);
                transform: perspective(140px) rotateY(180deg);
                opacity: 0;
            }
    }
</style>
