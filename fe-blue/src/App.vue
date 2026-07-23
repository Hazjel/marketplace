<script setup>
import { RouterView, useRouter } from 'vue-router'
import { ref, onErrorCaptured, onMounted, onUnmounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useWishlistStore } from '@/stores/wishlist'
import { useThemeStore } from '@/stores/theme'
import { useChatStore } from '@/stores/chat'
import { useToast } from 'vue-toastification'
import FloatingChatWidget from '@/components/App/chat/FloatingChatWidget.vue'

const error = ref(null)
// TEMPORARY DEBUG — paksa true buat lihat detail error di production,
// REVERT ke import.meta.env.DEV setelah selesai debug.
const isDev = true
const router = useRouter()
const authStore = useAuthStore()
const wishlistStore = useWishlistStore()
const chatStore = useChatStore()
const toast = useToast()

onErrorCaptured((err, instance, info) => {
  console.error('[onErrorCaptured]', err, info) // TEMPORARY DEBUG
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

const goHome = () => {
  error.value = null
  window.location.href = '/'
}

const handleApiError = (event) => {
  if (event.detail && event.detail.message) {
    toast.error(event.detail.message)
  }
}

/**
 * Global real-time chat notification handler.
 * Fired by chat.js store when a message arrives from a non-active conversation.
 */
const handleChatNotification = (event) => {
  const { senderName, preview } = event.detail
  toast.info(`💬 ${senderName}: ${preview}`, {
    timeout: 5000,
    closeOnClick: true,
    pauseOnHover: true
  })
}

onMounted(async () => {
  // Catch global API errors from axios interceptor
  window.addEventListener('api-error', handleApiError)
  window.addEventListener('chat-message-received', handleChatNotification)

  // Initialize theme system
  const themeStore = useThemeStore()
  themeStore.initListener()

  if (authStore.user) {
    await wishlistStore.fetchWishlist()

    // Initialize real-time chat globally — notif muncul di semua halaman
    chatStore.fetchContacts()
    chatStore.initializeChatListener(authStore.user.id)
    chatStore.joinPresenceChannel()
  }
})

onUnmounted(() => {
  window.removeEventListener('api-error', handleApiError)
  window.removeEventListener('chat-message-received', handleChatNotification)

  if (authStore.user) {
    chatStore.cleanupChatListener(authStore.user.id)
    chatStore.leavePresenceChannel()
  }
})
</script>

<template>
  <div
    v-if="error"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-6 text-gray-900 dark:text-white"
  >
    <div class="bg-white dark:bg-surface-card p-8 rounded-2xl max-w-md w-full text-center shadow-xl">
      <div class="mx-auto mb-4 flex size-14 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30">
        <svg class="size-7 text-red-600 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
        </svg>
      </div>
      <h1 class="text-lg font-bold mb-2">Ups, Terjadi Kesalahan</h1>
      <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
        Halaman ini mengalami masalah. Silakan muat ulang, atau kembali ke beranda.
      </p>

      <details v-if="isDev" class="mb-4 text-left">
        <summary class="cursor-pointer text-xs font-semibold text-gray-500">Detail teknis (dev only)</summary>
        <pre class="bg-gray-100 dark:bg-black/50 p-3 rounded text-xs mt-2 whitespace-pre-wrap max-h-60 overflow-auto">{{ error.message }}
{{ error.info }}
{{ error.stack }}</pre>
      </details>

      <div class="flex gap-3 justify-center">
        <button
          class="px-5 py-2.5 rounded-xl border border-gray-300 dark:border-white/10 font-semibold text-sm hover:bg-gray-50 dark:hover:bg-white/5 transition-colors"
          @click="goHome"
        >
          Ke Beranda
        </button>
        <button
          class="px-5 py-2.5 rounded-xl bg-custom-blue text-white font-semibold text-sm hover:shadow-lg hover:shadow-[#0D5CD7]/30 transition-all"
          @click="reloadApp"
        >
          Muat Ulang
        </button>
      </div>
    </div>
  </div>

  <RouterView />

  <!-- Global floating chat widget — visible di semua halaman untuk user login -->
  <FloatingChatWidget />
</template>
