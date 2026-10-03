import '@/assets/style.css'

import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { createHead } from '@unhead/vue/client'
import Toast from 'vue-toastification'
import 'vue-toastification/dist/index.css'

import App from './App.vue'
import router from './router'
import { installErrorReporting, reportClientError } from './utils/errorReporter'

const app = createApp(App)
const head = createHead()

installErrorReporting(app)
// A page chunk that fails to load (e.g. stale after a deploy) never reaches
// Vue's errorHandler.
router.onError((error) => reportClientError(error, 'router'))

app.use(createPinia())
app.use(router)
app.use(head)
app.use(Toast, {
  transition: 'Vue-Toastification__bounce',
  maxToasts: 20,
  newestOnTop: true
})

app.mount('#app')
