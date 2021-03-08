import Router from 'vue-router'
import Operations from './views/Operations'
import Payout from './views/Payout'
import Voucher from './views/Voucher'

Vue.use(Router);

export default new Router({
    base: process.env.BASE_URL,
    routes: [
        {
            path: '/',
            component: Operations
        },
        {
            path: '/payout',
            component: Payout
        },
        {
            path: '/voucher',
            component: Voucher
        },
    ]
})
