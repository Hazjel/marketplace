<script setup>
import { onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useCartStore } from '@/stores/cart'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

import Cookies from 'js-cookie'

onMounted(async () => {
  const token = route.query.token
  const username = route.query.username

  if (token && username) {
    // Set token in Cookies (required by axios instance)
    Cookies.set('token', token, {
      secure: window.location.protocol === 'https:',
      sameSite: 'Strict'
    })
    authStore.token = token

    try {
      // Fetch full user profile to populate pinia state correctly
      await authStore.checkAuth()

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
    } catch (e) {
      console.error('Auth Check Failed', e)
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
