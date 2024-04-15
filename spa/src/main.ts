import './assets/main.scss'
import { LocalStorage, Notify, Quasar } from 'quasar'

import { another, applyCssVars, request } from 'quasar-ui-danx'

import { createApp } from 'vue'
// eslint-disable-next-line import/extensions
import { colors } from '../tailwind.config'
import App from './App.vue'
import router from './router'

// Import styles
import '@quasar/extras/material-icons/material-icons.css'
import 'quasar/dist/quasar.sass'

// See vite.config.mts for the alias to quasar-ui-danx-styles
import 'quasar-ui-danx-styles'
// eslint-disable-next-line import/extensions
import '@/assets/main.scss'

// Inject all the CSS vars for our colors to override the Danx defaults
applyCssVars(colors, 'tw-')

request.configure({ baseUrl: import.meta.env.VITE_API_URL })

another()

const app = createApp(App)
app.use(Quasar, {
    plugins: { Notify, LocalStorage },
    config: {}
})

app.use(router)

app.mount('#app')
