<script setup>
import { useChatStore } from '@/stores/chat';
import { useAuthStore } from '@/stores/auth';
import { storeToRefs } from 'pinia';
import { ref, watch, nextTick, computed } from 'vue';

import defaultAvatar from '@/assets/images/icons/photo-profile-default.svg';

const chatStore = useChatStore();
const authStore = useAuthStore();
const { messages, activeUser, loadingMessages, sendingMessage } = storeToRefs(chatStore);
const { user: currentUser } = storeToRefs(authStore);
const { fetchMessages, sendMessage } = chatStore;

const isOnline = computed(() => {
    if (!activeUser.value) return false;
    return chatStore.onlineUsers.some(u => String(u.id) === String(activeUser.value.id));
});

const statusText = computed(() => {
    if (isOnline.value) return 'Online';
    if (activeUser.value?.last_seen_at) {
         const date = new Date(activeUser.value.last_seen_at);
         return `Last seen ${date.toLocaleDateString()} ${date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`;
    }
    return 'Offline';
});

const newMessage = ref('');
const messagesContainer = ref(null);

// Format time helper
const formatTime = (dateString) => {
    return new Date(dateString).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
};

const scrollToBottom = () => {
    nextTick(() => {
        if (messagesContainer.value) {
            messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
        }
    });
};

watch(activeUser, (newUser) => {
    if (newUser) {
        fetchMessages(newUser.id).then(() => {
            scrollToBottom();
        });
    }
});

watch(messages, () => {
    scrollToBottom();
}, { deep: true });

const handleSend = async () => {
    if (!newMessage.value.trim() || !activeUser.value) return;

    try {
        await sendMessage({
            receiver_id: activeUser.value.id,
            message: newMessage.value
        });
        newMessage.value = '';
        scrollToBottom();
    } catch (error) {
        console.error(error);
    }
};
</script>

<template>
    <div v-if="activeUser" class="flex flex-col flex-1 h-full bg-custom-background relative">
        <!-- Chat Header -->
        <div class="flex items-center gap-4 p-6 bg-white border-b border-custom-stroke shrink-0 relative">
            <button @click="chatStore.setActiveUser(null)" class="md:hidden p-2 -ml-2 text-custom-grey">
                <img src="@/assets/images/icons/arrow-left-grey.svg" class="size-6" alt="back">
            </button>
            <div class="size-12 rounded-full overflow-hidden bg-gray-200">
                <img :src="activeUser.profile_picture || defaultAvatar" class="size-full object-cover" alt="avatar"
                    @error="$event.target.src = defaultAvatar">
            </div>

            <div>
                <h2 class="font-bold text-lg">{{ activeUser.name }}</h2>
                <div class="flex items-center gap-1.5">
                    <div v-if="isOnline" class="size-2 rounded-full bg-green-500"></div>
                    <p class="text-xs text-custom-grey">{{ statusText }}</p>
                </div>
            </div>
        </div>

        <!-- Messages Area -->
        <div ref="messagesContainer" class="flex-1 overflow-y-auto p-6 flex flex-col gap-4">
            <div v-if="loadingMessages && messages.length === 0" class="flex justify-center py-10">
                <div class="size-8 border-4 border-custom-blue border-t-transparent rounded-full animate-spin"></div>
            </div>

            <div v-else-if="!loadingMessages && messages.length === 0"
                class="flex flex-col items-center justify-center flex-1 text-center opacity-50">
                <p>No messages yet.</p>
                <p class="text-sm">Say hello directly to {{ activeUser.name }}!</p>
            </div>

            <TransitionGroup 
                tag="div" 
                class="flex flex-col gap-4 w-full p-1"
                enter-active-class="transition-all duration-300 ease-in-out"
                leave-active-class="transition-all duration-300 ease-in-out"
                enter-from-class="opacity-0 translate-y-5"
                leave-to-class="opacity-0 translate-y-5"
            >
                <div v-for="msg in messages" :key="msg.id"
                    :class="['w-full flex', String(msg.sender_id) === String(currentUser?.id) ? 'justify-end' : 'justify-start']">

                    <div
                        :class="['max-w-[70%] p-4 rounded-2xl text-sm leading-relaxed break-words shadow-sm transition-all duration-300',
                            String(msg.sender_id) === String(currentUser?.id) ? 'bg-custom-blue text-white rounded-tr-none' : 'bg-white border border-custom-stroke rounded-tl-none text-custom-black']">
                        {{ msg.message }}
                        <p
                            :class="['text-[10px] mt-1 text-right opacity-70', String(msg.sender_id) === String(currentUser?.id) ? 'text-white' : 'text-custom-grey']">
                            {{ formatTime(msg.created_at) }}
                        </p>
                    </div>
                </div>
            </TransitionGroup>
        </div>

        <!-- Input Area -->
        <div class="p-6 bg-white border-t border-custom-stroke shrink-0">
            <form @submit.prevent="handleSend" class="flex gap-4">
                <input v-model="newMessage" type="text" placeholder="Type your message..."
                    class="flex-1 bg-custom-background border border-custom-stroke rounded-full px-6 py-3 focus:outline-none focus:border-custom-blue transition-colors">
                <button type="submit" :disabled="sendingMessage || !newMessage.trim()"
                    class="bg-custom-blue text-white rounded-full size-12 flex items-center justify-center shrink-0 hover:shadow-lg hover:shadow-[#0D5CD7]/30 transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                    <img v-if="!sendingMessage" src="@/assets/images/icons/arrow-right-white.svg" class="size-6"
                        alt="send">
                    <div v-else class="size-5 border-2 border-white border-t-transparent rounded-full animate-spin">
                    </div>
                </button>
            </form>
        </div>
    </div>

    <!-- No Active Chat State -->
    <div v-else class="flex flex-col flex-1 h-full items-center justify-center bg-custom-background text-custom-grey">
        <div class="size-20 bg-custom-stroke rounded-full flex items-center justify-center mb-4">
            <img src="@/assets/images/icons/sms-grey.svg" class="size-10 opacity-50" alt="chat">
        </div>
        <h3 class="font-bold text-lg text-custom-black">Select a conversation</h3>
        <p>Pick a person from the left sidebar to start chatting</p>
    </div>
</template>
