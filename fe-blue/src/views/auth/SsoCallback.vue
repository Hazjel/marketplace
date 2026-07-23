<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { axiosInstance } from '@/plugins/axios'
import Cookies from 'js-cookie'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

const errorMessage = ref(null)

onMounted(async () => {
  const exchangeToken = route.query.xt

  // Bersihkan exchange token dari address bar/history secepat mungkin --
  // token ini sekali-pakai dan berumur pendek (30 detik), tapi tetap tidak
  // boleh nangkring di URL lebih lama dari perlu.
  window.history.replaceState({}, '', route.path)

  if (!exchangeToken) {
    router.push({ name: 'auth.login' })
    return
  }

  try {
    const response = await axiosInstance.post('/auth/sso/exchange', {
      exchange_token: exchangeToken
    })

    const user = response.data.data
    const token = user.token

    Cookies.set('token', token, {
      secure: window.location.protocol === 'https:',
      sameSite: 'Strict'
    })
    authStore.token = token
    authStore.user = user

    if (authStore.activeMode === 'store') {
      router.push({ name: 'user.dashboard', params: { username: user.username } })
    } else {
      router.push({ name: 'app.home' })
    }
  } catch (e) {
    errorMessage.value = 'Sesi SSO tidak valid atau sudah kedaluwarsa. Silakan login ulang.'
    setTimeout(() => router.push({ name: 'auth.login' }), 2000)
  }
})
</script>

<template>
  <div class="flex items-center justify-center min-h-screen">
    <p v-if="errorMessage" class="font-bold text-xl text-red-500">{{ errorMessage }}</p>
    <p v-else class="font-bold text-xl">Menyiapkan sesi...</p>
  </div>
</template>
