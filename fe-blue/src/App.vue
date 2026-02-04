<script setup>
import { RouterView, useRouter } from 'vue-router'
import { ref, onErrorCaptured, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useWishlistStore } from '@/stores/wishlist'
import { useThemeStore } from '@/stores/theme'

const error = ref(null)
const router = useRouter()
const authStore = useAuthStore()
const wishlistStore = useWishlistStore()

onErrorCaptured((err, instance, info) => {
  error.value = {
    message: err.message,
    stack: err.stack,
    info: info
  }
  return false // Prevent error from propagating further
})

const reloadApp = () => {
  error.value = null
  router.go(0)
}

onMounted(async () => {
  // Initialize theme system
  const themeStore = useThemeStore()
  themeStore.initListener()

  if (authStore.user) {
    await wishlistStore.fetchWishlist()
  }
})
</script>

<template>
  <div
    v-if="error"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-10 text-white"
  >
    <div class="bg-red-900 p-8 rounded-xl max-w-4xl w-full overflow-auto max-h-[90vh]">
      <h1 class="text-3xl font-bold mb-4">Application Error</h1>
      <pre class="bg-black/50 p-4 rounded text-sm mb-4 whitespace-pre-wrap">{{
        error.message
      }}</pre>
      <div class="mb-4">
        <p class="font-bold">Info:</p>
        <pre>{{ error.info }}</pre>
      </div>
      <div class="mb-4">
        <p class="font-bold">Stack:</p>
        <pre class="text-xs whitespace-pre-wrap">{{ error.stack }}</pre>
      </div>
      <button
        class="bg-white text-red-900 px-6 py-2 rounded font-bold hover:bg-gray-200"
        @click="reloadApp"
      >
        Reload Application
      </button>
    </div>
  </div>

  <RouterView />
</template>
