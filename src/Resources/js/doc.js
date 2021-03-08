import '../sass/doc.scss';

import VueRouter from 'vue-router'
import VueResource from 'vue-resource'
import "prismjs";
import 'prismjs/components/prism-markup-templating';
import "prismjs/components/prism-php";
import 'prismjs/themes/prism-coy.css'
import DescPage from './doc/DescPage'
import MerchantSetupPage from './doc/Merchant/SetupPage'
import MerchantInitPage from './doc/Merchant/InitPage'
import MerchantReturnPage from './doc/Merchant/ReturnPage'
import MerchantResultPage from './doc/Merchant/ResultPage'
import MerchantPostbackPage from './doc/Merchant/PostbackPage'
import MerchantFaqPage from './doc/Merchant/FaqPage'
import ApiGeneralPage from './doc/Api/GeneralPage'
import ApiSetupPage from './doc/Api/SetupPage'
import ApiPayoutPage from './doc/Api/PayoutPage'
import ApiAccountPage from './doc/Api/AccountPage'

Vue.use(VueRouter)
Vue.use(VueResource)
Vue.use(Vuetify)

const routes = [
    { name: 'desc', path: '/', component: DescPage },

    { name: 'merchantSetup', path: '/merchant/setup', component: MerchantSetupPage },
    { name: 'merchantInit', path: '/merchant/initialization', component: MerchantInitPage },
    { name: 'merchantReturn', path: '/merchant/return', component: MerchantReturnPage },
    { name: 'merchantResult', path: '/merchant/result', component: MerchantResultPage },
    { name: 'PostbackPage', path: '/merchant/postback', component: MerchantPostbackPage },
    { name: 'merchantFaq', path: '/merchant/faq', component: MerchantFaqPage },

    { name: 'apiGeneral', path: '/api/general', component: ApiGeneralPage },
    { name: 'apiSetup', path: '/api/setup', component: ApiSetupPage },
    { name: 'apiPayout', path: '/api/payout', component: ApiPayoutPage },
    { name: 'apiAccount', path: '/api/account', component: ApiAccountPage },
]

const router = new VueRouter({
    routes
})

new Vue({
    data: {
        drawer: null,
        sections: {
            desc: {route: '', name: 'Описание'},
            merchant: {name: 'Мерчант', active: true, subsections: {
                setup: {route: 'merchant/setup', name: 'Настройка магазина'},
                init: {route: 'merchant/initialization', name: 'Инициализация платежа'},
                return: {route: 'merchant/return', name: 'Страницы возврата'},
                result: {route: 'merchant/result', name: 'Уведомления'},
                postback: {route: 'merchant/postback', name: 'Postback'},
                faq: {route: 'merchant/faq', name: 'Частые вопросы'},
            }},
            api: {name: 'API', active: true, subsections: {
                    general: {route: 'api/general', name: 'Общая информация'},
                    setup: {route: 'api/setup', name: 'Настройка'},
                    payout: {route: 'api/payout', name: 'Выплаты'},
                    account: {route: 'api/account', name: 'Счета'},
            }},
        }
    },
    router,
}).$mount('#app')
