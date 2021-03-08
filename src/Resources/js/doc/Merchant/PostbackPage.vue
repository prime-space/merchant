<template>
    <div>
        <h1>Мерчант - Postback</h1>
        <p>
            Если у вас есть необходимость в детальном анализе данных по своим платежам, то мы можем
            отправлять вам Postback уведомления о них. Информация отсылается нашей системой
            на ваш сервер по двум событиям - инициализация платежа и подтверждение оплаты.
        </p>
        <p>Для того, что бы мы отправляли postback, должно быть выполнено 2 условия:</p>
        <ul>
            <li>
                В разделе <b>Магазины - Ваш магазин - Информация - Postback</b>
                установите флажок <b>Задействовать Postback</b> и укажите ссылку.
            </li>
            <li>Отправляйте параметр <b>sub_id</b> при инициализации платежа</li>
        </ul><br>

        <h4>Ссылка уведомлений</h4>
        <p>
            Пример:
            <br>
            &nbsp;&nbsp;&nbsp;
            https://your.domain/postback?sub_id={sub1}&status={status}&#38;currency=RUB&payout={sum}&profit={profit}&time={time}&method={method}&from={{config.domain}}
            <br>
            Плейсхолдеры:
        </p>
        <v-data-table
                :headers="tableParamHeaders"
                :items="tableParamItems"
                hide-actions
                class="elevation-1"
        >
            <template slot="items" slot-scope="props">
                <td>{{ props.item.name }}</td>
                <td>{{ props.item.desc }}</td>
            </template>
        </v-data-table><br>
    </div>
</template>

<script>
    export default {
        data() {
            return {
                config: config,
                tableParamHeaders: [
                    {text: 'Имя', sortable: false, value: 'name'},
                    {text: 'Описание', sortable: false, value: 'desc'},
                ],
                tableParamItems: [
                    {name: '{sub1}', desc: 'Переданный sub_id при инициализации платежа'},
                    {name: '{status}', desc: 'При событии инициализации платежа - lead, оплаты - sale'},
                    {name: '{sum}', desc: 'Сумма платежа'},
                    {name: '{profit}', desc: 'Профит(отличается от sum, если комиссию оплачивает магазин)'},
                    {name: '{time}', desc: 'Timestamp отправки уведомления'},
                    {name: '{method}', desc: 'Code платежного направления'},
                ],
            }
        }
    }
</script>
<style>
</style>
