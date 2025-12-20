import { defineStore } from "pinia";
import { axiosInstance } from "@/plugins/axios";
import { handleError } from "@/helpers/errorHelper";

export const useChatStore = defineStore("chat", {
    state: () => ({
        contacts: [],
        messages: [],
        activeUser: null,
        loadingContacts: false,
        loadingMessages: false,
        sendingMessage: false,
        sendingMessage: false,
        error: null,
        onlineUsers: [], // Array of user IDs or objects
    }),

    getters: {
        totalUnreadCount: (state) => {
            return state.contacts.reduce((total, contact) => total + (contact.unread_count || 0), 0);
        }
    },

    actions: {
        async fetchContacts() {
            this.loadingContacts = true;
            try {
                const response = await axiosInstance.get('/chat/contacts');
                this.contacts = response.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.loadingContacts = false;
            }
        },

        async fetchMessages(userId) {
            this.loadingMessages = true;
            this.messages = []; // Clear previous messages
            try {
                const response = await axiosInstance.get(`/chat/${userId}`);
                this.messages = response.data.data;

                // Reset unread count for this user locally
                const contact = this.contacts.find(c => c.id === userId);
                if (contact) {
                    contact.unread_count = 0;
                }
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.loadingMessages = false;
            }
        },

        async sendMessage(payload) {
            this.sendingMessage = true;
            try {
                const response = await axiosInstance.post('/chat/send', payload);
                // Optimistically push or rely on response
                this.messages.push(response.data.data);
                return response.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.sendingMessage = false;
            }
        },

        pushMessage(message) {
            // Only push if the message belongs to the current active chat
            if (this.activeUser && (message.sender_id === this.activeUser.id || message.receiver_id === this.activeUser.id)) {
                // Check if message already exists to prevent duplicates from optimistic update + broadcast
                const exists = this.messages.some(m => m.id === message.id);
                if (!exists) {
                    this.messages.push(message);
                }
            } else {
                // Update unread count for the sender
                const contact = this.contacts.find(c => c.id === message.sender_id);
                if (contact) {
                    contact.unread_count = (contact.unread_count || 0) + 1;
                } else {
                    // Fetch contacts again if new user?
                    this.fetchContacts();
                }
            }
        },

        setActiveUser(user) {
            this.activeUser = user;
        },

        async fetchUserById(userId) {
            try {
                const response = await axiosInstance.get(`/chat/user/${userId}`);
                return response.data.data;
            } catch (error) {
                console.error("Failed to fetch user info", error);
                return null;
            }
        },

        initializeChatListener(userId) {
            // Importing echo here or using global echo from plugins
            // Since we can't easily import 'echo' inside actions if not at top, assumes echo is available or imported.
            // We imported `axiosInstance` at top, we need to import `echo` at top of file.

            // Check if already listening to avoid duplicates? Echo handles this usually.
            // But good to track if initialized.

            import('@/plugins/echo').then(({ default: echo }) => {
                echo.private(`chat.${userId}`)
                    .listen('MessageSent', (e) => {
                        this.pushMessage(e.message);
                    });
            });
        },

        cleanupChatListener(userId) {
            import('@/plugins/echo').then(({ default: echo }) => {
                echo.leave(`chat.${userId}`);
            });
        },

        joinPresenceChannel() {
            import('@/plugins/echo').then(({ default: echo }) => {
                echo.join('online')
                    .here((users) => {
                        this.onlineUsers = users;
                    })
                    .joining((user) => {
                        this.onlineUsers.push(user);
                    })
                    .leaving((user) => {
                        this.onlineUsers = this.onlineUsers.filter(u => u.id !== user.id);
                    });
            });
        },

        leavePresenceChannel() {
            import('@/plugins/echo').then(({ default: echo }) => {
                echo.leave('online');
            });
        }
    }
});
