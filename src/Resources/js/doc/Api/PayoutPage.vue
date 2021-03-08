<template>
    <div>
        <h1>Api - Выплаты</h1>
        <h2>Создание выплаты</h2>
        <p>
            Название метода: payout
            <br>Метод запроса: POST
        </p>

        <h4>Обязательные параметры</h4>
        <v-data-table
                :headers="tableParamHeaders"
                :items="tablePayoutItems"
                hide-actions
                class="elevation-1"
        >
            <template slot="items" slot-scope="props">
                <td>{{ props.item.name }}</td>
                <td>{{ props.item.value }}</td>
                <td>{{ props.item.desc }}</td>
            </template>
        </v-data-table><br>

        <p>В случае успешного создания выплаты вам будет возвращен параметр <b>operationId</b>, являющийся идентификатором выплаты в нашей системе</p>
        <v-flex class="pt-0">
            <v-alert :value="true"
                     class="ma-0"
                     color="error"
            >
                <b>Внимание!</b> В результате таймаута запроса возможен такой исход, что выплата успешно создастся.
                Если при повторном запросе вы получаете ошибку дубликата id, вам нужно разрешить эту проблему вручную
            </v-alert>
        </v-flex><br>

        <h4>Пример запроса на языке php</h4>
        <prism language="php">&lt;?php
$userId = 1;
$token = 'rbs6bsa0ymw50knwe93qwkyflhq7k4ivj2shey3pnojr2kgu10sranvwocho6gay';

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "https://{{config.domain}}/api/$userId/payout",
    CURLOPT_POST => 1,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_HTTPHEADER => ["Authorization: Bearer $token"],
    CURLOPT_POSTFIELDS => [
        'id' => '112',
        'receiver' => '+79998887766',
        'method' => 'qiwi',
        'amount' => '99.99',
        'accountId' => '1000',
    ],
]);
$response = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if (false === $response) {
    //Timeout handler
    echo 'timeout';
} elseif (200 !== $code) {
    //Error handler
    echo 'error';
} else {
    $responseData = json_decode($response, true);
    //Success handler
    echo "OK. operationId: {$responseData['operationId']}";
}</prism><br>


        <h2>Статусы выплат</h2>
        <p>
            Название метода: payouts
            <br>Метод запроса: POST
        </p>

        <h4>Обязательные параметры</h4>
        <v-data-table
                :headers="tableParamHeaders"
                :items="tablePayoutsItems"
                hide-actions
                class="elevation-1"
        >
            <template slot="items" slot-scope="props">
                <td>{{ props.item.name }}</td>
                <td>{{ props.item.value }}</td>
                <td>{{ props.item.desc }}</td>
            </template>
        </v-data-table><br>

        <p>
            В случае успешного ответа вы получите массив operations содержащий в себе до 100 элементов с информацией о выплатах:
        </p>
        <v-data-table
                :headers="tableParamHeaders"
                :items="tablePayoutsResponseItems"
                hide-actions
                class="elevation-1"
        >
            <template slot="items" slot-scope="props">
                <td>{{ props.item.name }}</td>
                <td>{{ props.item.value }}</td>
                <td>{{ props.item.desc }}</td>
            </template>
        </v-data-table>
        <p>
            При выплатах больших сумм перевод разбивается на несколько транзакций. Не редки случаи, когда кошелек
            получателя блокируется или перестает принимать переводы после нескольких успешных частей.
            В этом случае статус переходит в 4(ошибка).
            Обязательно проверяйте параметр isPartially и transferredAmount.
        </p><br>

        <h4>Пример запроса на языке php</h4>
        <prism language="php">&lt;?php
$userId = 1;
$token = 'rbs6bsa0ymw50knwe93qwkyflhq7k4ivj2shey3pnojr2kgu10sranvwocho6gay';

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "https://{{config.domain}}/api/$userId/payouts",
    CURLOPT_POST => 1,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_HTTPHEADER => ["Authorization: Bearer $token"],
    CURLOPT_POSTFIELDS => [
        'fromOperationId' => '1',
    ],
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
                tablePayoutItems: [
                    {name: 'id', value: 'int', desc: 'Идентификатор выплаты в вашей системе. Не должен повторяться'},
                    {name: 'receiver', value: 'string', desc: 'Кошелек получателя'},
                    {name: 'method', value: 'string', desc: 'Метод (yandex, qiwi, webmoney_r)'},
                    {name: 'amount', value: 'decimal(12,2)', desc: 'Сумма, которая должна быть зачислена получателю'},
                    {name: 'accountId', value: 'int', desc: 'ID счета списания(можно посмотреть на странице с балансами)'},
                ],
                tablePayoutsItems: [
                    {name: 'fromOperationId', value: 'int', desc: 'Идентификатор выплаты в нашей системе, начиная с которого вы хотите узнать статусы'},
                ],
                tablePayoutsResponseItems: [
                    {name: 'id', value: 'int', desc: 'Идентификатор выплаты в нашей системе'},
                    {name: 'statusId', value: 'int', desc: '1 - На очереди, 2 - В процессе, 3 - Успешно, 4 - Ошибка'},
                    {name: 'isPartially', value: 'bool', desc: 'true, если выплата содержит в себе несколько частей. При этом в статусе 4(Ошибка) возможна ситуация, когда одна часть выплачена, а другая нет. Читайте информацию под таблицей'},
                    {name: 'transferredAmount', value: 'decimal', desc: 'Выплаченная сумма'},
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
