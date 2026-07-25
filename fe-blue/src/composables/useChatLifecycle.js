import { onMounted, onUnmounted, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { useAuthStore } from '@/stores/auth'
import { useChatStore } from '@/stores/chat'

// Module-level (bukan per-instance) -- App.vue, Navbar.vue, Sidebar.vue, dan
// BuyerSidebar.vue semua memasang lifecycle chat ini karena tiap satu bisa
// mount duluan tergantung halaman/urutan render. Guard di sini mencegah
// fetchContacts() dan setup listener terpanggil berkali-kali (chatStore
// sendiri hanya menjaga agar listener realtime tidak dobel, bukan fetch-nya).
let activeSubscribers = 0
let subscribedUserId = null

export function useChatLifecycle() {
  const authStore = useAuthStore()
  const chatStore = useChatStore()
  const { user } = storeToRefs(authStore)

  const startFor = (userId) => {
    if (subscribedUserId === userId) return
    subscribedUserId = userId

    chatStore.fetchContacts()
    chatStore.initializeChatListener(userId)
    chatStore.joinPresenceChannel()
  }

  const stopFor = (userId) => {
    if (subscribedUserId !== userId) return
    subscribedUserId = null

    chatStore.cleanupChatListener(userId)
    chatStore.leavePresenceChannel()
  }

  onMounted(() => {
    activeSubscribers++
    if (user.value) startFor(user.value.id)
  })

  // Komponen ini bisa mount sebelum authStore.user terisi (mis. race dengan
  // checkAuth() di komponen lain) -- watch menangkap login yang terjadi
  // setelah mount, bukan cuma yang sudah ada saat mount.
  watch(user, (newUser) => {
    if (newUser) startFor(newUser.id)
  })

  onUnmounted(() => {
    activeSubscribers--
    if (activeSubscribers > 0) return
    if (subscribedUserId) stopFor(subscribedUserId)
  })
}
