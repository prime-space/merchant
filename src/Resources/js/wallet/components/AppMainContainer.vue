<template>
    <div class="main-container">
        <slot></slot>
    </div>
</template>
<script>
    export default {}
</script>
<style lang="scss" scoped>
    .main-container {
        margin-left: 30px;
        width: 730px;
        padding: 20px;
        background: #fff;
        box-sizing: border-box;
        box-shadow: 0 3px 3px rgba(0, 0, 0, 0.03);
        border-radius: 8px;
    }

    @media screen and (max-width: 1170px) {
        .main-container {
            width: 100%;
            margin: 0 auto;
        }
    }
</style>
