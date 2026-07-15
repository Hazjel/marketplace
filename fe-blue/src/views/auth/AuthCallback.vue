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

      router.push({ name: 'app.home' })
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
    <p class="font-bold text-xl">Processing Login...</p>
  </div>
</template>
