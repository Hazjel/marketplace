<script setup>
import { ref, nextTick } from 'vue';
import axios from '@/plugins/axios'; // Pakai axios plugin project kamu

const isOpen = ref(false);
const message = ref('');
const isLoading = ref(false);
const chatContainer = ref(null);

// Pesan awal
const chats = ref([
    { text: 'Halo! Aku Ri, istri Mikhael~ Ada yang bisa dibantu soal makanan di Calorizz?', isBot: true }
]);

const toggleChat = () => {
    isOpen.value = !isOpen.value;
    scrollToBottom();
};

const scrollToBottom = () => {
    nextTick(() => {
        if (chatContainer.value) {
            chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
        }
    });
};

const sendMessage = async () => {
    if (!message.value.trim()) return;

    // 1. Tampilkan pesan user
    const userMsg = message.value;
    chats.value.push({ text: userMsg, isBot: false });
    message.value = '';
    isLoading.value = true;
    scrollToBottom();

    try {
        // 2. Kirim ke Laravel (/api/chat)
        const response = await axios.post('/chat', {
            message: userMsg
        });

        // 3. Tampilkan balasan Ri
        const reply = response.data.data.reply;
        chats.value.push({ text: reply, isBot: true });

    } catch (error) {
        console.error(error);
        chats.value.push({ text: 'Duh, koneksi putus.. coba lagi ya~', isBot: true });
    } finally {
        isLoading.value = false;
        scrollToBottom();
    }
};
</script>

<template>
    <div class="fixed bottom-6 right-6 z-[9999] flex flex-col items-end gap-3 font-sans">
        
        <transition name="fade">
            <div v-if="isOpen" class="w-[350px] h-[450px] bg-white rounded-2xl shadow-2xl flex flex-col overflow-hidden border border-gray-200">
                <div class="bg-custom-blue p-4 flex justify-between items-center text-white">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-white/20 rounded-full flex items-center justify-center">🤖</div>
                        <div>
                            <h3 class="font-bold text-sm">Ri (AI Assistant)</h3>
                            <div class="flex items-center gap-1">
                                <span class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></span>
                                <span class="text-xs opacity-80">Online</span>
                            </div>
                        </div>
                    </div>
                    <button @click="toggleChat" class="hover:bg-white/20 p-1 rounded transition">&times;</button>
                </div>

                <div ref="chatContainer" class="flex-1 p-4 overflow-y-auto flex flex-col gap-3 bg-gray-50">
                    <div v-for="(chat, index) in chats" :key="index" 
                        class="max-w-[85%] p-3 rounded-2xl text-sm leading-relaxed whitespace-pre-wrap"
                        :class="chat.isBot ? 'bg-white border border-gray-200 self-start text-gray-700 rounded-tl-none' : 'bg-custom-blue text-white self-end rounded-tr-none'">
                        {{ chat.text }}
                    </div>
                    
                    <div v-if="isLoading" class="bg-white border border-gray-200 self-start p-3 rounded-2xl rounded-tl-none w-fit">
                        <div class="flex gap-1">
                            <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce"></span>
                            <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce delay-75"></span>
                            <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce delay-150"></span>
                        </div>
                    </div>
                </div>

                <div class="p-3 bg-white border-t flex gap-2">
                    <input 
                        v-model="message" 
                        @keyup.enter="sendMessage" 
                        type="text" 
                        placeholder="Tanya rekomendasi makanan..." 
                        class="flex-1 bg-gray-100 rounded-full px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-custom-blue/50"
                        :disabled="isLoading"
                    >
                    <button @click="sendMessage" :disabled="isLoading" class="bg-custom-blue text-white w-10 h-10 rounded-full flex items-center justify-center hover:bg-blue-700 disabled:opacity-50 transition">
                        ➤
                    </button>
                </div>
            </div>
        </transition>

        <button @click="toggleChat" class="w-14 h-14 bg-custom-blue rounded-full shadow-lg flex items-center justify-center hover:scale-110 transition-transform duration-300 text-white text-2xl">
            <span v-if="!isOpen">💬</span>
            <span v-else>&times;</span>
        </button>
    </div>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.3s, transform 0.3s; }
.fade-enter-from, .fade-leave-to { opacity: 0; transform: translateY(20px); }
</style>