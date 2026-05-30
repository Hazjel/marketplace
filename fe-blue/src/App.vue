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
const router = useRouter()
const authStore = useAuthStore()
const wishlistStore = useWishlistStore()
const chatStore = useChatStore()
const toast = useToast()

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

  <!-- Global floating chat widget — visible di semua halaman untuk user login -->
  <FloatingChatWidget />
</template>
