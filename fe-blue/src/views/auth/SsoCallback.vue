<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useCartStore } from '@/stores/cart'

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

  const user = await authStore.completeSsoExchange(exchangeToken)

  if (!user) {
    errorMessage.value = 'Sesi SSO tidak valid atau sudah kedaluwarsa. Silakan login ulang.'
    setTimeout(() => router.push({ name: 'auth.login' }), 2000)
    return
  }

  // Sama seperti alur login biasa (Login.vue) -- pindah domain lewat SSO
  // tidak boleh diam-diam menjatuhkan cart guest yang belum tersinkron.
  const cart = useCartStore()
  await cart.syncAfterLogin()

  if (authStore.activeMode === 'store') {
    router.push({ name: 'user.dashboard', params: { username: user.username } })
  } else {
    router.push({ name: 'app.home' })
  }
})
</script>

<template>
  <div class="flex items-center justify-center min-h-screen">
    <p v-if="errorMessage" class="font-medium text-xl text-red-500">{{ errorMessage }}</p>
    <p v-else class="font-medium text-xl">Menyiapkan sesi...</p>
  </div>
</template>
