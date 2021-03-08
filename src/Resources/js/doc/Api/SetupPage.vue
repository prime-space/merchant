<template>
    <div>
        <h1>Api - Настройка</h1>
        <p>
            Для того, что бы начать пользоваться API, вам необходимо настроить несколько параметров в разделе "Настройки".<br>
            Зайдите в раздел и перейдите на вкладку "Настройки API"
        </p>
        <h4>Описание настроек API</h4>
        <v-data-table
                :headers="tableSettingsHeaders"
                :items="tableSettingsItems"
                hide-actions
                class="elevation-1"
        >
            <template slot="items" slot-scope="props">
                <td>{{ props.item.name }}</td>
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
                tableSettingsHeaders: [
                    {text: 'Название', sortable: false, value: 'name'},
                    {text: 'Описание', sortable: false, value: 'desc'},
                ],
                tableSettingsItems: [
                    {name: 'IP-адреса', desc: 'IP адреса ваших серверов, с которых будут приходить запросы к нашему API'},
                    {name: 'Ключ', desc:
                        'Набор цифр и букв в нижнем регистре длиной в 64 символа. Служит для Bear авторизации при запросах. ' +
                        'После сохранения скрывается и вы не сможете никак его узнать. Если вы потеряли ' +
                        'значение, вы можете ввести новое.'
                    },
                    {name: 'Пароль', desc: 'Пароль от вашего аккаунта для подтверждения изменений'},
                    {name: 'Задействовать API', desc: 'Наше API будет принимать от вас запросы только когда этот флажок установлен'},
                ],
            }
        },
        components: {
        }
    }
</script>
<style>
</style>
