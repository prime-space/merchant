import '../sass/index.scss';

import VueRouter from 'vue-router'
import VueResource from 'vue-resource'
import Index from './Index.vue'

Vue.use(VueRouter)
Vue.use(VueResource)
Vue.use(Vuetify)

const routes = [
    { path: '/', component: Index }
]

const router = new VueRouter({
    routes
})

const app = new Vue({
    methods: {
        signOut () {
            window.location.href='/signOut'
        }
    },
    router,
}).$mount('#app')
