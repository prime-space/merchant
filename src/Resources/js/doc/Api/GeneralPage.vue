<template>
    <div>
        <h1>Api - Общая информация</h1>
        <p>Все запросы принимаются на адрес https://{{config.domain}}/api/{userId}/{method}</p>
        <p>Ваш <b>User ID</b> вы можете увидеть в левом верхнем углу личного кабинета</p>
        <p>Тип авторизации - Bear. Заголовок 'Authorization' вашего запроса должен иметь значение 'Bearer {apiKey}'</p>
        <p>Формат ответа - JSON</p>
        <br>

        <h4>Общие ошибки</h4>
        <v-data-table
                :headers="tableErrorHeaders"
                :items="tableErrorItems"
                hide-actions
                class="elevation-1"
        >
            <template slot="items" slot-scope="props">
                <td>{{ props.item.code }}</td>
                <td>{{ props.item.response }}</td>
                <td v-html="props.item.desc"></td>
            </template>
        </v-data-table>
    </div>
</template>
<script>
    export default {
        data() {
            return {
                config: config,
                tableErrorHeaders: [
                    {text: 'Код', sortable: false, value: 'code'},
                    {text: 'Ответ', sortable: false, value: 'response'},
                    {text: 'Описание', sortable: false, value: 'desc'},
                ],
                tableErrorItems: [
                    {code: '401', response: '[]', desc: 'Не авторизован. Пользователь не найден'},
                    {code: '423', response: '["Blocked"]', desc: 'Вы заблокированы, обратитесь в поддержку'},
                    {code: '423', response: '[]', desc: 'Api не задействовано'},
                    {code: '403', response: '[]', desc: 'IP не разрешен'},
                    {code: '401', response: '[]', desc: 'Не авторизован. Неверный токен'},
                    {code: '400', response: 'json', desc: 'Ошибка входящих данных. Список ошибок будет перечисленн массивом в формате parameterName => errorMessage'},
                    {code: '500', response: 'mixed', desc: 'Ошибка сервера, повторите запрос позже'},
                ],
            }
        },
        components: {
        }
    }
</script>
<style>
</style>
