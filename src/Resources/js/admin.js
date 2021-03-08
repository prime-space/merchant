import './../sass/admin.scss';
import './../sass/vue-snack.css';

import 'babel-polyfill'
import VueRouter from 'vue-router'
import VueResource from 'vue-resource'
import VueSnackbar from 'vue-snack';
import Admin from './Admin.vue'
import Shop from './Shop.vue'
import Payment from './Payment.vue'
import Payout from './Payout.vue'
import Settings from './Settings.vue'
import Support from './Support.vue'
import Ticket from './Ticket'
import MultiLanguage from 'vue-multilanguage'
import store from './store'
import ConfirmDialog from './confirmDialog.vue'
import PaymentDetails from './PaymentDetails'
import ShopDetails from './ShopDetails'

Vue.use(VueSnackbar, {position: 'bottom-right', time: 6000});
Vue.use(VueRouter);
Vue.use(VueResource);
Vue.use(Vuetify);
Vue.use(MultiLanguage, {
    default: 'ru',
    ru: {
        menuDoc: 'Документация',

        blockedUserMessage: 'Аккаунт заблокирован, исходящие операции ограничены. Обратитесь в поддержку.',

        snackSuccessMessage: 'Успешно обновлено',
        snackHide: 'Скрыть',

        balance: 'Баланс',
        shopTitle: 'Магазин',
        shopTableHeadName: 'Название',
        shopTableHeadStatus: 'Статус',
        shopTableHeadDailyLimit: 'Дневной лимит (UTC)',
        shopTableToCheckingTooltip: 'Отправить на проверку',
        shopTableToEditTooltip: 'Редактировать',
        shopTableInfoTooltip: 'Информация',
        shopTableToEditPaymentMethodsTooltip: 'Выбрать методы оплаты',
        shopLimitTooltip: 'Дневной лимит рассчитывается по часовому поясу +00:00 и именно поэтому сумма может не совпадать со статистикой магазина.',
        shopLimitTooltipCounter: 'До сброса лимитов осталось:',
        shopPaymentMethodsFormFee: 'Комиссия',
        PaymentMethodsSelectLabel: 'Выберите методы оплаты',
        paymentShopSelectLabel: 'Магазин',
        paymentTableHeadID: '#',
        paymentTableHeadPayment: 'Ваш id',
        paymentTableHeadAmount: 'Сумма',
        paymentTableHeadMethod: 'Метод',
        paymentTableHeadCreatedTs: 'Дата',
        paymentStatusIconTooltipInfo: 'Детальная информация',
        paymentStatusIconTooltipError: 'Ошибка отправки уведомления',

        payoutTableHeadID: '#',
        payoutTableHeadInternalUsersId: 'Ваш ID',
        payoutTableHeadPaymentSystem: 'Направление',
        payoutTableHeadReceiver: 'Получатель',
        payoutTableHeadAmount: 'Выплачено/Всего',
        payoutTableHeadFee: 'Комиссия',
        payoutTableHeadParts: 'Частей (?)',
        payoutTableHeadPartsTooltip: 'Успешно/Обработано/Всего',
        payoutTableHeadStatus: 'Статус',
        payoutTableHeadCreated: 'Дата',
        payoutTableFilterButton: 'Поиск',
        payoutTableClearButton: 'Сброс',
        payoutFilterFormName: 'Фильтры',
        payoutFilterFormId: 'Id',
        payoutFilterFormInternalUsersId: 'Ваш Id',
        payoutFilterFormPayoutMethod: 'Направление',
        payoutFilterFormReceiver: 'Получатель',
        payoutFilterFormStatusId: 'Статус',
        payoutPayoutFormReceiver: 'Кошелек получателя',
        payoutPayoutFormMethod: 'Направление',
        payoutPayoutFormAccount: 'Счет',
        payoutPayoutFormAmount: 'Сумма',
        payoutPayoutFormInternalUsersId: 'Id',
        payoutPayoutFormTitle: 'Выплата',
        payoutPayoutFormPassword: 'Пароль',
        payoutPayoutFormRememberPassword: 'Запомнить пароль на 10 минут',
        payoutCreateNewPayout: 'Создать выплату',

        settingsPasswordChangeTab: 'Смена пароля',
        settingsTimezoneTab: 'Смена часового пояса',
        settingsApiTab: 'Настройки API',
        settingsGenerateApiSecret: 'Сгенерировать',
        settingsSave: 'Сохранить',
        settingsApiIpsLabel: 'API IP-адреса',
        settingsApiIpsHint: 'Нажмите Enter чтобы добавить',
        settingsApiSecretLabel: 'API Ключ (Скрывается)',
        settingsPasswordLabel: 'Пароль',
        settingsApiEnabledLabel: 'Задействовать API',
        settingsNewPasswordLabel: 'Новый пароль',

        supportTableHeadTheme: 'Тема',
        supportTableHeadDate: 'Дата последнего сообщения',
        supportNewTicket: 'Новый тикет',
        supportNewMessage: 'Новое сообщение',
        supportCreateTicket: 'Создать тикет',
        supportCreateButton: 'Создать',
        supportFormClose: 'Закрыть',
        supportThemeLabel: 'Тема',
        supportMessageLabel: 'Cообщение',

        ticketSendMessage: 'Отправить',
        ticketMessageLabel: 'Cообщение',

        paymentDetailsTabInfo: 'Информация',
        paymentDetailsTabNotifications: 'Уведомления',
        paymentDetailsTableHeadStatus: 'Статус',
        paymentDetailsTableHeadResult: 'Ответ',
        paymentDetailsTableHeadCode: 'HTTP код',
        paymentDetailsTableHeadCreated: 'Дата создания',
        paymentDetailsDataIteratorTitleId: '#',
        paymentDetailsDataIteratorTitleNotificationStatusId: 'Ваш id',
        paymentDetailsDataIteratorTitleAmount: 'Сумма',
        paymentDetailsDataIteratorTitleMethodName: 'Метод',
        paymentDetailsDataIteratorTitleCreatedDate: 'Дата создания',
        paymentDetailsDataIteratorTitleFee: 'Комиссия',
        paymentDetailsDataIteratorTitlePaysFee: 'Комиссию оплатил',
        paymentDetailsDataIteratorTitleCredit: 'Будет зачислено на баланс',
        paymentDetailsDataIteratorTitleDescription: 'Описание',
        paymentDetailsDataIteratorTitleSuccessDate: 'Дата оплаты',
        paymentDetailsDataIteratorTitleNotificationStatus: 'Статус уведомлений',
        paymentDetailsDataIteratorTitleEmail: 'Email',

        shopDetailsTabInfo: 'Информация',
        shopDetailsTabStatistics: 'Статистика',
        shopDetailsTabPaymentMethods: 'Методы оплаты',
        shopDetailsTabPostBack: 'PostBack',
        shopDetailsDataIteratorHeader: 'Магазин',
        shopDetailsDataIteratorHeadId: '#',
        shopDetailsDataIteratorHeadName: 'Имя',
        shopDetailsDataIteratorHeadUrl: 'URL-адрес',
        shopDetailsDataIteratorHeadDescription: 'Описание',
        shopDetailsDataIteratorHeadSuccessUrl: 'Адрес перенаправления при успешной оплате',
        shopDetailsDataIteratorHeadFailUrl: 'Адрес перенаправления при ошибке оплаты',
        shopDetailsDataIteratorHeadResultUrl: 'Адрес отправки уведомлений',
        shopDetailsDataIteratorHeadIsTestMode: 'Тестовый режим',
        shopDetailsDataIteratorHeadIsFeeByClient: 'Комиссию оплачивает покупатель',
        shopDetailsDataIteratorHeadIsAllowedToRedefineUrl: 'Разрешить переопределение URL',
        shopDetailsDataIteratorHeadStatus: 'Статус',
        shopDetailsDataIteratorHeadDailyLimit: 'Дневной лимит (UTC)',
        shopDetailsSave: 'Сохранить',

        shopPostBackDoc: 'Документация',
        shopPostBackUrl: 'Url',
        shopPostBackSave: 'Сохранить',
        shopPostBackEnabledLabel: 'Задействовать Postback',

        chartIntervalMonth1: 'Январь',
        chartIntervalMonth2: 'Февраль',
        chartIntervalMonth3: 'Март',
        chartIntervalMonth4: 'Апрель',
        chartIntervalMonth5: 'Май',
        chartIntervalMonth6: 'Июнь',
        chartIntervalMonth7: 'Июль',
        chartIntervalMonth8: 'Август',
        chartIntervalMonth9: 'Сентябрь',
        chartIntervalMonth10: 'Октябрь',
        chartIntervalMonth11: 'Ноябрь',
        chartIntervalMonth12: 'Декабрь',

        chartCurrencyRub: 'руб.',

        adminChartTitle: 'Статистика операций',

        paymentStatisticChartTrueLabel: 'По месяцам',
        paymentStatisticChartFalseLabel: 'По дням',
        paymentStatisticChartTotalLabel: 'Всего операций',
        paymentStatisticChartSuccessLabel: 'Оплаченные операции',
        paymentStatisticChartAmountLabel: 'Сумма продаж',
    },
});

Vue.component('confirm-dialog', ConfirmDialog);

const routes = [
    { name: 'main', path: '/', component: Admin },
    { name: 'shops', path: '/shops', component: Shop },
    { name: 'payments', path: '/payments', component: Payment },
    { name: 'payouts', path: '/payouts', component: Payout },
    { name: 'support', path: '/support', component: Support },
    { name: 'ticket', path: '/ticket/:id', component: Ticket },
    { name: 'settings', path: '/settings', component: Settings },
    { name: 'payment', path: '/payment/:id', component: PaymentDetails },
    { name: 'shop', path: '/shop/:id', component: ShopDetails },
];

const router = new VueRouter({
    routes
});

var _tz = timezoneJs.timezone;
_tz.loadingScheme = _tz.loadingSchemes.MANUAL_LOAD;
_tz.loadZoneDataFromObject(timezoneJsData);

const app = new Vue({
    data: {
        drawer: null,
        sections: {
            main: {id: 1, route: '', name: 'Главная', icon: 'home', isOnNavigationBar: true},
            shops: {id: 2, route: 'shops', name: 'Магазины', icon: 'store', isOnNavigationBar: true},
            payments: {id: 3, route: 'payments', name: 'Операции', icon: 'description', isOnNavigationBar: true},
            payouts: {id: 4, route: 'payouts', name: 'Выплаты', icon: 'monetization_on', isOnNavigationBar: true},
            support: {id: 5, route: 'support', name: 'Поддержка', icon: 'contact_support', isOnNavigationBar: true},
            settings: {id: 6, route: 'settings', name: 'Настройки', icon: 'settings', isOnNavigationBar: true},
            ticket: {id: 7, name: 'Сообщения', isOnNavigationBar: false},
            payment: {id: 8, name: 'Операция', isOnNavigationBar: false},
            shop: {id: 9, name: 'Магазин', isOnNavigationBar: false},
        },
    },
    mounted: function() {
        this.setSection()
    },
    methods: {
        signOut () {
            window.location.href='/signOut'
        },
        setSection () {
            let sectionName = this.sections[router.currentRoute.name].name;
            this.$store.commit('changeTitle', sectionName);
        },
        snackbarHide() {
            this.$store.commit('snackbarHide');
        },
        submitLkModeForm () {
            lkModeForm.submit();
        },
    },
    created() {
        this.snackbar = this.$store.state.snackbar.show;
    },
    computed: {
        snackbar: {
            get() {
                return this.$store.state.snackbar.show;
            },
            set(show) {
                this.$store.commit('snackbarSet', show);
            },
        },
        sectionName() {
            return store.state.pageTitle.title;
        },
        snackbarText() {
            return store.state.snackbar.text;
        },
        snackbarColor() {
            return store.state.snackbar.color;
        }
    },
    router,
    store,
}).$mount('#app');
