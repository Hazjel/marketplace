<script setup>
import ChatSidebar from '@/components/App/chat/ChatSidebar.vue'
import ChatRoom from '@/components/App/chat/ChatRoom.vue'
import { onMounted, onUnmounted } from 'vue'
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
  }

  // Add ESC key listener
  window.addEventListener('keydown', handleEscKey)
})

const handleEscKey = (e) => {
  if (e.key === 'Escape') {
    setActiveUser(null)
  }
}

onUnmounted(() => {
  // Channel chat.{id} & presence "online" dipegang App.vue/Navbar untuk
  // seumur sesi login (notifikasi global) — ChatLayout tidak pernah join
  // keduanya, jadi tidak boleh leave di sini juga (dulu salah leave channel
  // orang lain, notifikasi chat global mati begitu halaman Chat ditinggalkan).
  window.removeEventListener('keydown', handleEscKey)
})
</script>

<template>
  <div class="flex flex-1 h-full w-full">
    <div
      class="flex flex-1 w-full border border-custom-stroke dark:border-white/10 rounded-3xl overflow-hidden shadow-sm bg-white dark:bg-surface-card">
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
