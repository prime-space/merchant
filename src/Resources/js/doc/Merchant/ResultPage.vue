<template>
    <div>
        <h1>Мерчант - Уведомления о успешных платежах</h1>
        <p>
            После получения нами подтверждения оплаты вашего платежа мы высылаем вам уведомление
            в виде POST-запроса на указанный в настройках адрес.
        </p>
        <p>Для принятия уведомления вам необходимо проверить следующие параметры:</p>
        <ul>
            <li>ip-адреса серверов - всегда 109.120.152.109 или 145.239.84.249</li>
            <li>сумма платежа</li>
            <li>валюта</li>
            <li>подпись уведомления</li>
        </ul><br>
        <p>Для проверки подписи уведомления вам необходимо:</p>
        <ul>
            <li>Убрать параметр sign</li>
            <li>Отсортировать полученные массив параметров в алфавитном порядке по ключу</li>
            <li>Соединить значения параметров через двоеточие, добавить секретный ключ через двоеточие</li>
            <li>Получить хеш-код sha256 от этой строки</li>
        </ul><br>
        <p>
            <b>!ВАЖНО!</b> Если ваш сервер отвечает кодом 200, то система считает это принятием уведомления.
            В противном случае, система отправляет еще 4 уведомления через каждые 10 минут.
        </p>
        <p>На страницу передаются следующие параметры:</p>
        <ul>
            <li>systemPayment</li>
            <li>payment</li>
            <li>shop</li>
            <li>currency</li>
            <li>amount</li>
            <li>sign</li>
            <li>uv_*</li>
        </ul><br>

<h4>Пример проверки уведомления на языке php:</h4>
<prism language="php">&lt;?php
$secret = 'f6482bd9a166bf2s43ssc9fe60eb4774';
$amount = '150.30';
$currency = '3';

if (!in_array($_SERVER["REMOTE_ADDR"], ['109.120.152.109', '145.239.84.249'], true)) {
    exit();
}
if ($_POST["amount"] !== $amount) {
    exit();
}
if ($_POST["currency"] !== $currency) {
    exit();
}

$sign = $_POST['sign'];
unset($_POST['sign']);
ksort($_POST,SORT_STRING);
$signi = hash('sha256', implode(':', $_POST).':'.$secret);
if($signi !== $sign) {
    exit();
}

confirm();</prism>
    </div>
</template>
<script>
    import Prism from "vue-prism-component";
    export default {
        data() {
            return {
                config: config
            }
        },
        components: {
            Prism
        }
    }
</script>
<style>
</style>
