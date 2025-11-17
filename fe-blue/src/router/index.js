import { createRouter, createWebHistory } from 'vue-router'
import App from '@/layouts/App.vue'
import Home from '@/views/App/Home.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      component: App,
      children: [
        {
          path: '',
          name: 'Home',
          component: Home
        }
      ]
    },
  ],
})

export default router
