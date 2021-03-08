<template>
    <div>
        <h1>Api - Счета</h1>
        <h2>Получение списка счетов</h2>
        <p>
            Название метода: accounts
            <br>Метод запроса: POST
        </p>

        <h4>В случае успешного ответа вы получите массив счетов, содержащий следующие параметры:</h4>
        <v-data-table
                :headers="tableParamHeaders"
                :items="tableAccountResponseItems"
                hide-actions
                class="elevation-1"
        >
            <template slot="items" slot-scope="props">
                <td>{{ props.item.name }}</td>
                <td>{{ props.item.value }}</td>
                <td>{{ props.item.desc }}</td>
            </template>
        </v-data-table><br>

        <h4>Пример запроса на языке php</h4>
        <prism language="php">&lt;?php
$userId = 1;
$token = 'rbs6bsa0ymw50knwe93qwkyflhq7k4ivj2shey3pnojr2kgu10sranvwocho6gay';

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "https://{{config.domain}}/api/$userId/accounts",
    CURLOPT_POST => 1,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_HTTPHEADER => ["Authorization: Bearer $token"],
]);
$response = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if (200 !== $code) {
    //Error handler
    echo 'error';
} else {
    $responseData = json_decode($response, true);
    //Success handler
    var_dump($responseData);
}</prism>
    </div>
</template>
<script>
    import Prism from "vue-prism-component";
    export default {
        data() {
            return {
                config: config,
                tableParamHeaders: [
                    {text: 'Имя', sortable: false, value: 'name'},
                    {text: 'Значение', sortable: false, value: 'value'},
                    {text: 'Описание', sortable: false, value: 'desc'},
                ],
                tableAccountResponseItems: [
                    {name: 'id', value: 'int', desc: 'ID счета'},
                    {name: 'balance', value: 'decimal(12,2)', desc: 'Баланс'},
                    {name: 'currencyId', value: 'int', desc: 'ID валюты'},
                    {name: 'currencySign', value: 'string', desc: 'Знак валюты'},
                ],
            }
        },
        components: {
            Prism
        }
    }
</script>
<style>
</style>
