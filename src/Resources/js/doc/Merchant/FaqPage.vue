<template>
    <div>
        <h1>Мерчант - Частые вопросы</h1>
        <blockquote class="blockquote">
            При инициализации платежа я все время получаю ошибку "Неверный параметр sign"
        </blockquote>
        <p>
            Возьмите примеры из документации и попробуйте их без какой-либо примеси своего кода.
            Эти примеры проверены и являются 100% рабочими.<br>
            Если вы все равно видите эту ошибку, то это может означать только то, что при получении
            подписи вы используете неверный секрет. Установите новый секрет в настройках магазина.
        </p>
    </div>
</template>
<script>
    export default {
        data() {
            return {
                config: config,
            }
        },
        components: {
        }
    }
</script>
<style>
</style>
