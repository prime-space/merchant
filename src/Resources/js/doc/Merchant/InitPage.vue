<template>
    <div>
        <h1>Мерчант - Инициализация платежа</h1>
        <p>
            Для перенаправления пользователя на оплату вы должны создать HTML-форму с методом POST
            на адрес https://{{config.domain}}/pay
        </p>

        <h4>Обязательные параметры</h4>
        <v-data-table
                :headers="tableParamHeaders"
                :items="tableParamItems"
                hide-actions
                class="elevation-1"
        >
            <template slot="items" slot-scope="props">
                <td>{{ props.item.name }}</td>
                <td>{{ props.item.value }}</td>
                <td>{{ props.item.desc }}</td>
            </template>
        </v-data-table><br>

        <h4>Дополнительные параметры</h4>
        <v-data-table
                :headers="tableParamHeaders"
                :items="tableNNParamItems"
                hide-actions
                class="elevation-1"
        >
            <template slot="items" slot-scope="props">
                <td>{{ props.item.name }}</td>
                <td>{{ props.item.value }}</td>
                <td>{{ props.item.desc }}</td>
            </template>
        </v-data-table><br>

        <h4>Ключи платежных направлений</h4>
        <v-form ref="paymentMethodsForm" @submit.prevent="paymentMethodsFormSubmit">
            <v-layout row>
                <v-text-field
                        label="ID Магазина"
                        v-model="paymentMethodsFormShopId"
                        :rules="paymentMethodsFormShopIdRules"
                        required
                ></v-text-field>
                <v-btn type="submit">Посмотреть</v-btn>
            </v-layout>
        </v-form>
        <br>

        <h4>Подпись</h4>
        <p>Для создания подписи необходимо:</p>
        <ul>
            <li>отсортировать массив отправляемых параметров в алфавитном порядке по ключу;</li>
            <li>соединить их значения через двоеточие, добавить секретный ключ через двоеточие;</li>
            <li>получить хеш sha256 от этой строки;</li>
        </ul><br>

        <h4>Пример создания формы на языке php</h4>
        <prism language="php">&lt;?php
$secret = 'f6482bd9a166bf2s43ssc9fe60eb4774';

$data = [
    'shop' => 1,
    'payment' => 1,
    'amount' => 5,
    'description' => 'Оплата товара',
    'currency' => 3,
    /*'via' => 'qiwi',*/
];
ksort($data, SORT_STRING);
$sign = hash('sha256', implode(':',$data).':'.$secret);
?&gt;
&lt;form method="POST" action="https://{{config.domain}}/pay"&gt;
    &lt;input name="shop"        value="&lt;?=$data['shop']; ?&gt;"&gt;
    &lt;input name="payment"     value="&lt;?=$data['payment'] ?&gt;"&gt;
    &lt;input name="amount"      value="&lt;?=$data['amount'] ?&gt;"&gt;
    &lt;input name="description" value="&lt;?=$data['description'] ?&gt;"&gt;
    &lt;input name="currency"    value="&lt;?=$data['currency'] ?&gt;"&gt;
    &lt;!--&lt;input name="via"     value="&lt;?=$data['via'] ?&gt;"&gt;--&gt;
    &lt;input name="sign"        value="&lt;?=$sign ?&gt;"&gt;
    &lt;button&gt;Оплатить&lt;/button&gt;
&lt;/form&gt;</prism>
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
                tableParamItems: [
                    {name: 'shop', value: 'int', desc: 'Идентификатор вашего магазина'},
                    {name: 'payment', value: 'bigint', desc: 'ID платежа в вашей системе, не должен повторяться'},
                    {name: 'amount', value: 'decimal(12,2)', desc: 'Сумма'},
                    {name: 'description', value: 'string(128)', desc: 'Описание'},
                    {name: 'currency', value: 'int', desc: 'ID валюты (3 - рубли)'},
                    {name: 'sign', value: 'string', desc: 'Подпись платежа'},
                ],
                tableNNParamItems: [
                    {name: 'email', value: 'string', desc: 'Email покупателя. Если не указан, то мы, в некоторых случаях, добавляем пользователю шаг с его запросом '},
                    {name: 'via', value: 'string', desc: 'Ключ платежного направления. Узнать доступные ключи для вашего магазина вы можете в пункте ниже'},
                    {name: 'success', value: 'string', desc: 'Адрес перенаправления при успешной оплате (Если разрешено в настройках)'},
                    {name: 'fail', value: 'string', desc: 'Адрес перенаправления при ошибке оплаты (Если разрешено в настройках)'},
                    {name: 'uv_*', value: 'string(256)', desc: 'Пользовательские параметры'},
                    {name: 'sub_id', value: '/^[a-z0-9]{1,32}$/i', desc: 'Идентификатор платежа для postback'},
                ],
                paymentMethodsFormShopId: '',
                paymentMethodsFormShopIdRules: [
                    v => !!v || 'Обязательное поле',
                    v => /^\d+$/.test(v) || 'Только цифры'
                ],
            }
        },
        methods: {
            paymentMethodsFormSubmit(submitEvent) {
                if (this.$refs.paymentMethodsForm.validate()) {
                    window.open('/shopPaymentMethods/'+this.paymentMethodsFormShopId, '_blank');
                }
            },
        },
        components: {
            Prism
        }
    }
</script>
<style>
</style>
