import Vuex from 'vuex'
import confirmer from './confirmer'

Vue.use(Vuex)

const pageTitle = {
    state: {
        title: '',
        snackbar: {
            show: true,
            text: '123',
        },
    },
    mutations: {
        changeTitle (state, payload) {
            state.title = payload;
        }
    },
}

const snackbar = {
    state: {
        show: false,
        text: '',
        color: 'info',
    },
    mutations: {
        snackbarShow (state, payload) {
            state.show = true;
            state.text = payload.text;
            state.color = payload.color;
        },
        snackbarHide (state, text) {
            state.show = false;
            state.text = '';
        },
        snackbarSet (state, show) {
            state.show = show;
        },
    },
}

export default new Vuex.Store({
    state: {},
    modules: {
        confirmer,
        pageTitle,
        snackbar,
    }
})
