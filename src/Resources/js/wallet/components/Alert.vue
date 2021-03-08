<template>
    <div class="alert" :class="['alert_' + type]">
        <div class="alert__icon">
            <font-awesome-icon icon="check"></font-awesome-icon>
        </div>
        <span class="alert__text"><slot>{{text}}</slot></span>
    </div>
</template>
<script>
    export default {
        props: {
            text: String,
            type: String,
        },
    }
</script>
<style lang="scss" scoped>
    .alert {
        padding: 21px 0 21px 63px;
        border-radius: 4px;
        font-family: 'Roboto', sans-serif;
        position: relative;
    }

    .alert_success {
        background: rgba(101, 216, 120, 0.15);
        color: #65D878;

        .alert__icon {
            background: #65D878;
        }
    }

    .alert_warning {
        background: rgba(255, 197, 7, 0.15);
        color: #FFC507;

        .alert__icon {
            background: #FFC507;
        }
    }

    .alert_error {
        background: rgba(255, 146, 146, 0.15);
        color: #FF9292;

        .alert__icon {
            background: #FF9292;
        }
    }

    .alert__icon {
        width: 25px;
        height: 25px;
        position: absolute;
        left: 23px;
        top: 50%;
        transform: translateY(-50%);
        border-radius: 50%;
        color: #fff;

        svg {
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
        }
    }

    .alert__text {
        display: block;
        font-size: 15px;
        font-weight: 500;
    }
</style>
