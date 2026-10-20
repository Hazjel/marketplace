<script setup>
import { onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useCartStore } from '@/stores/cart'
import { axiosInstance } from '@/plugins/axios'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

import Cookies from 'js-cookie'

onMounted(async () => {
  const code = route.query.code

  if (typeof code === 'string' && code) {
    try {
      const response = await axiosInstance.post('/auth/google/exchange', { code }, {
        withCredentials: true
      })
      const user = response.data.data
      const token = user.token
      if (typeof token !== 'string' || !token) {
        router.push({ name: 'auth.login' })
        return
      }

      Cookies.set('token', token, {
        secure: window.location.protocol === 'https:',
        sameSite: 'Strict'
      })
      authStore.token = token
      authStore.user = user
      authStore.error = null
      authStore.success = response.data.message

      // Nomor HP opsional (konsisten dengan register biasa) — jangan paksa
      // lengkapi profil; nomor penerima diminta saat buat alamat/checkout
      const cartStore = useCartStore()
      await cartStore.syncAfterLogin()

      const isSellerBuild = import.meta.env.VITE_APP_TARGET === 'seller'
      const isSellerRole = authStore.user?.role === 'admin' || authStore.user?.role === 'store'

      if (isSellerRole && !isSellerBuild) {
        // Role toko/admin tapi lagi di build buyer -- dashboard toko
        // tidak ada di sini, SSO ke seller app.
        await authStore.initiateSso(import.meta.env.VITE_SELLER_APP_URL)
      } else if (!isSellerRole && isSellerBuild) {
        // Role buyer tapi lagi di build seller -- marketplace tidak ada
        // di sini, SSO balik ke buyer app.
        await authStore.initiateSso(import.meta.env.VITE_BUYER_APP_URL)
      } else if (authStore.user?.role === 'admin') {
        router.push({ name: 'admin.dashboard' })
      } else if (authStore.user?.role === 'store') {
        router.push({ name: 'user.dashboard', params: { username: authStore.user.username } })
      } else {
        router.push({ name: 'app.home' })
      }
    } catch {
      router.push({ name: 'auth.login' })
    }
  } else {
    // Failed, go back to login
    router.push({ name: 'auth.login' })
  }
})
</script>

<template>
  <div class="flex items-center justify-center min-h-screen">
    <p class="font-medium text-xl">Processing Login...</p>
  </div>
</template>
