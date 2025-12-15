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
        error: null,
    }),

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
                // TODO: Update unread count or move contact to top
                console.log('New message from other user', message);
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
        }
    }
});
