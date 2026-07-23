<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import { storeToRefs } from 'pinia'
import { useChatStore } from '@/stores/chat'
import { useAuthStore } from '@/stores/auth'
import defaultAvatar from '@/assets/images/icons/photo-profile-default.svg'

const chatStore = useChatStore()
const authStore = useAuthStore()
const { contacts, messages, activeUser, loadingMessages, sendingMessage, totalUnreadCount, onlineUsers } =
  storeToRefs(chatStore)
const { user } = storeToRefs(authStore)

const isOpen = ref(false)
const newMessage = ref('')
const messagesContainer = ref(null)
const view = ref('contacts') // 'contacts' | 'room'

const badgeText = computed(() =>
  totalUnreadCount.value > 99 ? '99+' : totalUnreadCount.value
)

const isOnline = computed(() => {
  if (!activeUser.value) return false
  return onlineUsers.value.some((u) => String(u.id) === String(activeUser.value.id))
})

const formatTime = (dateString) =>
  new Date(dateString).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })

const scrollToBottom = () => {
  nextTick(() => {
    if (messagesContainer.value) {
      messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
    }
  })
}

watch(messages, () => scrollToBottom(), { deep: true })

watch(activeUser, (newUser) => {
  if (newUser) {
    view.value = 'room'
    chatStore.fetchMessages(newUser.id).then(() => scrollToBottom())
  }
})

const toggleWidget = () => {
  isOpen.value = !isOpen.value
  if (isOpen.value && contacts.value.length === 0) {
    chatStore.fetchContacts()
  }
}

const selectContact = (contact) => {
  chatStore.setActiveUser(contact)
}

const backToContacts = () => {
  view.value = 'contacts'
  chatStore.setActiveUser(null)
}

const handleSend = async () => {
  if (!newMessage.value.trim() || !activeUser.value) return
  try {
    await chatStore.sendMessage({
      receiver_id: activeUser.value.id,
      message: newMessage.value
    })
    newMessage.value = ''
    scrollToBottom()
  } catch (e) {
    console.error(e)
  }
}
</script>

<template>
  <!-- Only render for logged-in users -->
  <div v-if="user" class="fixed bottom-6 right-6 z-[999] flex flex-col items-end gap-3">
    <!-- Chat Panel -->
    <Transition
      enter-active-class="transition-all duration-300 ease-out"
      enter-from-class="opacity-0 translate-y-4 scale-95"
      enter-to-class="opacity-100 translate-y-0 scale-100"
      leave-active-class="transition-all duration-200 ease-in"
      leave-from-class="opacity-100 translate-y-0 scale-100"
      leave-to-class="opacity-0 translate-y-4 scale-95"
    >
      <div
        v-if="isOpen"
        class="w-[360px] h-[520px] bg-white dark:bg-surface-card rounded-2xl shadow-[0_20px_60px_rgba(0,0,0,0.18)] border border-custom-stroke dark:border-white/10 flex flex-col overflow-hidden"
      >
        <!-- ── CONTACT LIST VIEW ── -->
        <template v-if="view === 'contacts' || !activeUser">
          <div class="flex items-center justify-between px-5 py-4 border-b border-custom-stroke dark:border-white/10 shrink-0">
            <h2 class="font-medium text-base dark:text-white">Pesan</h2>
            <button
              class="size-7 flex items-center justify-center text-custom-grey hover:text-custom-black dark:hover:text-white transition-colors rounded-full hover:bg-gray-100 dark:hover:bg-white/10"
              @click="isOpen = false"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <div class="flex-1 overflow-y-auto">
            <div v-if="contacts.length === 0" class="flex flex-col items-center justify-center h-full text-center p-6 text-custom-grey">
              <svg xmlns="http://www.w3.org/2000/svg" class="size-12 opacity-30 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
              </svg>
              <p class="text-sm font-medium">Belum ada percakapan</p>
              <p class="text-xs mt-1">Mulai chat dari halaman produk</p>
            </div>

            <button
              v-for="contact in contacts"
              :key="contact.id"
              class="flex items-center gap-3 w-full px-5 py-3 hover:bg-gray-50 dark:hover:bg-white/5 transition-colors text-left border-b border-custom-stroke/50 dark:border-white/5 last:border-0"
              @click="selectContact(contact)"
            >
              <div class="relative size-10 rounded-full overflow-hidden bg-gray-100 dark:bg-white/10 shrink-0">
                <img :src="contact.profile_picture || defaultAvatar" class="size-full object-cover" alt="avatar" />
                <div
                  v-if="onlineUsers.some(u => String(u.id) === String(contact.id))"
                  class="absolute bottom-0 right-0 size-2.5 rounded-full bg-green-500 border-2 border-white dark:border-surface-card"
                ></div>
              </div>
              <div class="flex flex-col flex-1 min-w-0">
                <span class="text-sm font-medium dark:text-white truncate" :class="{ 'font-medium': contact.unread_count > 0 }">
                  {{ contact.name }}
                </span>
                <span class="text-xs text-custom-grey truncate" :class="{ 'text-custom-black dark:text-white font-medium': contact.unread_count > 0 }">
                  {{ contact.unread_count > 0 ? `${contact.unread_count} pesan baru` : 'Klik untuk chat' }}
                </span>
              </div>
              <div v-if="contact.unread_count > 0" class="size-5 bg-custom-red rounded-full flex items-center justify-center shrink-0">
                <span class="text-white text-[9px] font-medium">{{ contact.unread_count > 99 ? '99+' : contact.unread_count }}</span>
              </div>
            </button>
          </div>
        </template>

        <!-- ── CHAT ROOM VIEW ── -->
        <template v-else-if="activeUser">
          <!-- Room Header -->
          <div class="flex items-center gap-3 px-4 py-3 border-b border-custom-stroke dark:border-white/10 shrink-0 bg-white dark:bg-surface-card">
            <button
              class="p-1 text-custom-grey hover:text-custom-black dark:hover:text-white transition-colors"
              @click="backToContacts"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
              </svg>
            </button>
            <div class="relative size-9 rounded-full overflow-hidden bg-gray-100 shrink-0">
              <img :src="activeUser.profile_picture || defaultAvatar" class="size-full object-cover" alt="avatar" />
              <div v-if="isOnline" class="absolute bottom-0 right-0 size-2 rounded-full bg-green-500 border border-white"></div>
            </div>
            <div class="flex flex-col">
              <span class="text-sm font-medium dark:text-white">{{ activeUser.name }}</span>
              <span class="text-[10px]" :class="isOnline ? 'text-green-500 font-medium' : 'text-custom-grey'">
                {{ isOnline ? 'Online' : 'Offline' }}
              </span>
            </div>
            <button
              class="ml-auto size-7 flex items-center justify-center text-custom-grey hover:text-custom-black dark:hover:text-white transition-colors rounded-full hover:bg-gray-100 dark:hover:bg-white/10"
              @click="isOpen = false"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Messages -->
          <div ref="messagesContainer" class="flex-1 overflow-y-auto p-4 flex flex-col gap-3 bg-gray-50 dark:bg-surface">
            <div v-if="loadingMessages" class="flex justify-center py-6">
              <div class="size-6 border-2 border-custom-blue border-t-transparent rounded-full animate-spin"></div>
            </div>
            <div v-else-if="messages.length === 0" class="flex flex-col items-center justify-center flex-1 text-custom-grey text-center py-6">
              <p class="text-sm">Belum ada pesan.</p>
              <p class="text-xs mt-1">Mulai percakapan sekarang!</p>
            </div>

            <div
              v-for="msg in messages"
              :key="msg.id"
              class="flex w-full"
              :class="String(msg.sender_id) === String(user?.id) ? 'justify-end' : 'justify-start'"
            >
              <div
                class="max-w-[80%] px-4 py-2.5 rounded-2xl text-sm leading-relaxed break-words shadow-sm"
                :class="String(msg.sender_id) === String(user?.id)
                  ? 'bg-custom-blue text-white rounded-tr-none'
                  : 'bg-white dark:bg-surface-card border border-custom-stroke dark:border-white/10 text-custom-black dark:text-white rounded-tl-none'"
              >
                {{ msg.message }}
                <p
                  class="text-[10px] mt-0.5 text-right opacity-60"
                  :class="String(msg.sender_id) === String(user?.id) ? 'text-white' : 'text-custom-grey'"
                >
                  {{ formatTime(msg.created_at) }}
                </p>
              </div>
            </div>
          </div>

          <!-- Input -->
          <form class="flex gap-2 p-3 bg-white dark:bg-surface-card border-t border-custom-stroke dark:border-white/10 shrink-0" @submit.prevent="handleSend">
            <input
              v-model="newMessage"
              type="text"
              placeholder="Tulis pesan..."
              class="flex-1 bg-gray-50 dark:bg-surface border border-custom-stroke dark:border-white/10 rounded-full px-4 py-2 text-sm focus:outline-none focus:border-custom-blue transition-colors dark:text-white"
            />
            <button
              type="submit"
              :disabled="sendingMessage || !newMessage.trim()"
              class="size-9 bg-custom-blue rounded-full flex items-center justify-center shrink-0 hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <svg v-if="!sendingMessage" xmlns="http://www.w3.org/2000/svg" class="size-4 text-white -rotate-45" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
              </svg>
              <div v-else class="size-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
            </button>
          </form>
        </template>
      </div>
    </Transition>

    <!-- ── FLOATING TRIGGER BUTTON ── -->
    <button
      id="Floating-Chat-Trigger"
      class="relative size-14 bg-custom-blue hover:bg-blue-700 text-white rounded-full shadow-[0_8px_24px_rgba(13,92,215,0.4)] flex items-center justify-center transition-all duration-300 hover:scale-105 active:scale-95"
      :class="{ 'rotate-0': !isOpen, 'rotate-90': isOpen }"
      @click="toggleWidget"
    >
      <!-- Chat icon (default) -->
      <Transition
        enter-active-class="transition-all duration-200"
        enter-from-class="opacity-0 rotate-90 scale-50"
        enter-to-class="opacity-100 rotate-0 scale-100"
        leave-active-class="transition-all duration-150"
        leave-from-class="opacity-100 rotate-0 scale-100"
        leave-to-class="opacity-0 rotate-90 scale-50"
        mode="out-in"
      >
        <svg v-if="!isOpen" key="chat" xmlns="http://www.w3.org/2000/svg" class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
        </svg>
        <!-- Close icon -->
        <svg v-else key="close" xmlns="http://www.w3.org/2000/svg" class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </Transition>

      <!-- Unread badge on trigger -->
      <Transition
        enter-active-class="transition-all duration-300"
        enter-from-class="scale-0 opacity-0"
        enter-to-class="scale-100 opacity-100"
      >
        <div
          v-if="totalUnreadCount > 0 && !isOpen"
          class="absolute -top-1 -right-1 size-5 bg-custom-red rounded-full flex items-center justify-center border-2 border-white animate-pulse"
        >
          <span class="text-white text-[9px] font-medium leading-none">{{ badgeText }}</span>
        </div>
      </Transition>
    </button>
  </div>
</template>
