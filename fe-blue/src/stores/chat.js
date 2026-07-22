import { defineStore } from 'pinia'
import { logger } from '@/utils/logger'
import { axiosInstance } from '@/plugins/axios'
import { handleError } from '@/helpers/errorHelper'

export const useChatStore = defineStore('chat', {
  state: () => ({
    contacts: [],
    messages: [],
    activeUser: null,
    loadingContacts: false,
    loadingMessages: false,
    sendingMessage: false,
    error: null,
    onlineUsers: [], // Array of user IDs or objects
    _listeningChannel: null, // channel name yang sedang di-subscribe, cegah listener dobel
    _fetchMessagesSeq: 0 // cegah response fetch kontak lama nimpa kontak yang baru diklik
  }),

  getters: {
    totalUnreadCount: (state) => {
      return state.contacts.reduce((total, contact) => total + (contact.unread_count || 0), 0)
    }
  },

  actions: {
    async fetchContacts() {
      this.loadingContacts = true
      try {
        const response = await axiosInstance.get('/chat/contacts')
        this.contacts = response.data.data
      } catch (error) {
        this.error = handleError(error)
      } finally {
        this.loadingContacts = false
      }
    },

    async fetchMessages(userId) {
      this.loadingMessages = true
      this.messages = [] // Clear previous messages

      // User klik kontak A lalu cepat pindah ke B — kalau response A telat
      // datang setelah B, jangan sampai pesan A menimpa chat room B.
      const seq = ++this._fetchMessagesSeq

      try {
        const response = await axiosInstance.get(`/chat/${userId}`)

        if (seq !== this._fetchMessagesSeq) return

        this.messages = response.data.data

        // Reset unread count for this user locally
        const contact = this.contacts.find((c) => c.id === userId)
        if (contact) {
          contact.unread_count = 0
        }
      } catch (error) {
        if (seq !== this._fetchMessagesSeq) return
        this.error = handleError(error)
      } finally {
        if (seq === this._fetchMessagesSeq) {
          this.loadingMessages = false
        }
      }
    },

    async sendMessage(payload) {
      this.sendingMessage = true
      try {
        const response = await axiosInstance.post('/chat/send', payload)
        // Optimistically push or rely on response
        this.messages.push(response.data.data)
        return response.data
      } catch (error) {
        this.error = handleError(error)
        throw error
      } finally {
        this.sendingMessage = false
      }
    },

    pushMessage(message) {
      // Only push if the message belongs to the current active chat
      if (
        this.activeUser &&
        (message.sender_id === this.activeUser.id || message.receiver_id === this.activeUser.id)
      ) {
        // Check if message already exists to prevent duplicates from optimistic update + broadcast
        const exists = this.messages.some((m) => String(m.id) === String(message.id))
        if (!exists) {
          this.messages.push(message)
        }
      } else {
        // Update unread count for the sender
        const contact = this.contacts.find((c) => c.id === message.sender_id)
        if (contact) {
          contact.unread_count = (contact.unread_count || 0) + 1
        } else {
          // Fetch contacts again if new user
          this.fetchContacts()
        }

        // Dispatch global real-time notification toast
        // Listened by App.vue or any component via window event
        window.dispatchEvent(
          new CustomEvent('chat-message-received', {
            detail: {
              senderName: contact?.name || 'Seseorang',
              preview: message.message?.slice(0, 60) + (message.message?.length > 60 ? '…' : ''),
              senderId: message.sender_id
            }
          })
        )
      }
    },

    setActiveUser(user) {
      this.activeUser = user
    },

    async fetchUserById(userId) {
      try {
        const response = await axiosInstance.get(`/chat/user/${userId}`)
        return response.data.data
      } catch (error) {
        logger.error('Failed to fetch user info', error)
        return null
      }
    },

    initializeChatListener(userId) {
      const channel = `chat.${userId}`
      // Dipanggil dari beberapa komponen (App.vue, Navbar, Sidebar, BuyerSidebar)
      // tanpa saling tahu satu sama lain — tanpa guard ini tiap komponen pasang
      // listener baru, jadi notifikasi & unread count kelipatan per pesan masuk.
      if (this._listeningChannel === channel) return
      this._listeningChannel = channel

      import('@/plugins/echo').then(({ default: echo }) => {
        echo.private(channel).listen('MessageSent', (e) => {
          this.pushMessage(e.message)
        })
      })
    },

    cleanupChatListener(userId) {
      const channel = `chat.${userId}`
      if (this._listeningChannel !== channel) return
      this._listeningChannel = null

      import('@/plugins/echo').then(({ default: echo }) => {
        echo.leave(channel)
      })
    },

    joinPresenceChannel() {
      import('@/plugins/echo').then(({ default: echo }) => {
        echo
          .join('online')
          .here((users) => {
            this.onlineUsers = users
          })
          .joining((user) => {
            this.onlineUsers.push(user)
          })
          .leaving((user) => {
            this.onlineUsers = this.onlineUsers.filter((u) => u.id !== user.id)
          })
      })
    },

    leavePresenceChannel() {
      import('@/plugins/echo').then(({ default: echo }) => {
        echo.leave('online')
      })
    }
  }
})
