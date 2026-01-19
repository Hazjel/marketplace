<script setup>
import { RouterLink, RouterView } from 'vue-router'
import { ref, onErrorCaptured } from 'vue'

const error = ref(null)

onErrorCaptured((err, instance, info) => {
  error.value = {
    message: err.message,
    stack: err.stack,
    info: info
  }
  return false // Prevent error from propagating further
})
import { onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useWishlistStore } from '@/stores/wishlist'

const authStore = useAuthStore()
const wishlistStore = useWishlistStore()

onMounted(async () => {
    if (authStore.user) {
         await wishlistStore.fetchWishlist()
    }
})
</script>

<template>
  <div v-if="error" class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/80 p-10 text-white">
    <div class="bg-red-900 p-8 rounded-xl max-w-4xl w-full overflow-auto max-h-[90vh]">
      <h1 class="text-3xl font-bold mb-4">Application Error</h1>
      <pre class="bg-black/50 p-4 rounded text-sm mb-4 whitespace-pre-wrap">{{ error.message }}</pre>
      <div class="mb-4">
        <p class="font-bold">Info:</p>
        <pre>{{ error.info }}</pre>
      </div>
      <div class="mb-4">
        <p class="font-bold">Stack:</p>
        <pre class="text-xs whitespace-pre-wrap">{{ error.stack }}</pre>
      </div>
      <button @click="error = null; $router.go(0)" class="bg-white text-red-900 px-6 py-2 rounded font-bold hover:bg-gray-200">
        Reload Application
      </button>
    </div>
  </div>

  <RouterView />
</template>
