import './sass/wallet.scss';

import store from './../store'
import router from './router'
import 'babel-polyfill'
import VueResource from 'vue-resource'
import { library } from '@fortawesome/fontawesome-svg-core'
import {
    faAngleDown,
    faExclamation,
    faTimes,
    faCheck,
    faPlusSquare,
    faMinusSquare,
    faHandHoldingUsd,
    faLifeRing,
    faBusinessTime,
    faRunning,
    faCoins,
    faExchangeAlt,
    faPiggyBank,
    faClock,
    faCaretUp,
    faCaretDown,
    faSync,
    faPlusCircle,
    faMinusCircle,
    faWallet,
    faStore,
    faClone,
    faArrowLeft,
    faCheckDouble,
    faSave,
    faGifts,
    faEdit,
    faLock
} from '@fortawesome/free-solid-svg-icons'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import AppHeader from './components/AppHeader.vue'
import AppHeaderMobile from './components/AppHeaderMobile.vue'
import UserCard from './components/UserCard.vue'
import AppFooter from './components/AppFooter.vue'
import AppMainContainer from './components/AppMainContainer'
import LeftMenu from './components/LeftMenu'

library.add(
    faAngleDown,
    faExclamation,
    faTimes,
    faCheck,
    faPlusSquare,
    faMinusSquare,
    faHandHoldingUsd,
    faLifeRing,
    faBusinessTime,
    faRunning,
    faCoins,
    faExchangeAlt,
    faPiggyBank,
    faClock,
    faCaretUp,
    faCaretDown,
    faSync,
    faPlusCircle,
    faMinusCircle,
    faWallet,
    faStore,
    faClone,
    faArrowLeft,
    faCheckDouble,
    faSave,
    faGifts,
    faEdit,
    faLock
);
import VueSnackbar from 'vue-snack';
import './../../sass/vue-snack.css';
Vue.use(VueSnackbar, {position: 'bottom-right', time: 6000});

Vue.component('font-awesome-icon', FontAwesomeIcon);
Vue.use(VueResource);

Vue.config.productionTip = false;

var _tz = timezoneJs.timezone;
_tz.loadingScheme = _tz.loadingSchemes.MANUAL_LOAD;
_tz.loadZoneDataFromObject(timezoneJsData);

new Vue({
    components:{
        AppHeader,
        AppHeaderMobile,
        AppFooter,
        UserCard,
        AppMainContainer,
        LeftMenu
    },
    router,
    store,
}).$mount('#app');
