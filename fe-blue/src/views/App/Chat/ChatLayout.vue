<script setup>
import ChatSidebar from '@/components/App/chat/ChatSidebar.vue'
import ChatRoom from '@/components/App/chat/ChatRoom.vue'
import echo from '@/plugins/echo'
import { onMounted, onUnmounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useChatStore } from '@/stores/chat'
import { storeToRefs } from 'pinia'

const authStore = useAuthStore()
const chatStore = useChatStore()
const { user } = storeToRefs(authStore)
const { contacts, activeUser } = storeToRefs(chatStore)
const { fetchContacts, setActiveUser, fetchUserById } = chatStore
const route = useRoute()

const handleInitialParams = async () => {
  const targetUserId = route.query.userId
  if (targetUserId) {
    // Check if in contacts
    const existingContact = contacts.value.find((c) => c.id == targetUserId) // Use == for type coercion if targetUserId is string
    if (existingContact) {
      setActiveUser(existingContact)
    } else {
      // Fetch user info
      const targetUser = await fetchUserById(targetUserId)
      if (targetUser) {
        setActiveUser(targetUser)
      }
    }
  }
}

onMounted(async () => {
  if (user.value) {
    await fetchContacts()
    await handleInitialParams()
    chatStore.joinPresenceChannel() // Join presence channel
  }

  // Add ESC key listener
  window.addEventListener('keydown', handleEscKey)
})

// Watch for user changes (e.g. late login)
watch(user, (newUser) => {
  // No local listener setup needed, Sidebar handles global listener
})

const handleEscKey = (e) => {
  if (e.key === 'Escape') {
    setActiveUser(null)
  }
}

onUnmounted(() => {
  if (user.value) {
    echo.leave(`chat.${user.value.id}`)
    chatStore.leavePresenceChannel()
  }
  window.removeEventListener('keydown', handleEscKey)
})
</script>

<template>
  <div class="flex flex-1 h-full w-full">
    <div
      class="flex flex-1 w-full border border-custom-stroke rounded-3xl overflow-hidden shadow-sm bg-white"
    >
      <!-- Sidebar Wrapper -->
      <div :class="['h-full', activeUser ? 'hidden md:flex' : 'w-full md:w-auto']">
        <ChatSidebar class="h-full" />
      </div>

      <!-- ChatRoom Wrapper -->
      <div :class="['h-full flex-1 min-w-0', !activeUser ? 'hidden md:flex' : 'flex']">
        <ChatRoom class="h-full" />
      </div>
    </div>
  </div>
</template>
