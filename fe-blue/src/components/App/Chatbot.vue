<<<<<<< HEAD
    <script setup>
    import { ref, nextTick } from 'vue';
    import axios from 'axios';
=======
<script setup>
import { ref, nextTick } from 'vue';
import axios from 'axios'; // Pakai axios plugin project kamu
>>>>>>> 43038e5e5bf8911b8039c34d6902b2db09ef1590

axios.defaults.baseURL = 'http://localhost:8001';

const isOpen = ref(false);
const message = ref('');
const isLoading = ref(false);
const chatContainer = ref(null);

<<<<<<< HEAD
    // Pesan awal
    const chats = ref([
        { text: 'Halo! Aku Ri, si asisten virtual~ Ada yang bisa dibantu soal makanan di Calorizz?', isBot: true }
    ]);
=======
// Pesan awal
const chats = ref([
    { text: 'Halo! Aku Ri, istri Mikhael~ Ada yang bisa dibantu soal makanan di Calorizz?', isBot: true }
]);
>>>>>>> 43038e5e5bf8911b8039c34d6902b2db09ef1590

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
    const rawMsg = message.value;
    const userMsg = rawMsg.trim(); // Ambil nilai yang sudah di-trim

    // 2. VALIDASI KETAT: Jika kosong setelah di-trim, batalkan dan jangan kirim
    if (!userMsg) {
        message.value = ''; // Kosongkan input field yang berisi spasi saja
        return;
    }
    chats.value.push({ text: userMsg, isBot: false });
    message.value = '';
    isLoading.value = true;
    scrollToBottom();

    try {
        // 2. Kirim ke Laravel (/api/chat)
        const response = await axios.post('/predict', {
            message: userMsg
        }, {
            headers: {
                'Content-Type': 'application/json'
            }
        });

        // 3. Tampilkan balasan Ri
        const reply = response.data.reply;
        chats.value.push({ text: reply, isBot: true });

<<<<<<< HEAD
        // Jika kosong setelah di-trim, batalkan dan jangan kirim
        if (!userMsg) {
            message.value = ''; // Mengosongkan input field yang berisi spasi saja
            return;
        }
        chats.value.push({ text: userMsg, isBot: false });
        message.value = '';
        isLoading.value = true;
        scrollToBottom();

        try {
            // Kirim ke Laravel (/api/chat)
            const response = await axios.post('/predict', {
                message: userMsg
            }, {
                headers: {
                    'Content-Type': 'application/json'
                }
            });

            // Tampilkan balasan Ri
            const reply = response.data.reply;
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
        <div class="fixed z-[9999] bottom-6 right-6 flex flex-col items-end gap-3 font-sans">

            <transition name="fade">
                <div v-if="isOpen" class="bg-white rounded-2xl shadow-2xl flex flex-col border border-gray-200 w-[400px] max-w-[90vw]
        h-[520px] max-h-[80vh]">
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

                    <div ref="chatContainer"
                        class="flex-1 p-4 overflow-y-auto overflow-x-hidden flex flex-col gap-3 bg-gray-50">

                        <!-- CHAT BUBBLE -->
                        <div v-for="(chat, index) in chats" :key="index" class="rounded-2xl flex"
                            :class="chat.isBot ? 'justify-start' : 'justify-end'">
                            <div class="text-sm break-words max-w-[50%] w-fit"
                                :class="chat.isBot ? 'border border-gray-200' : 'bg-custom-blue text-white'">
                                <p class="p-2">
                                    {{ chat.text }}
                                </p>
                            </div>
                        </div>

                        <!-- LOADING -->
                        <div v-if="isLoading"
                            class="bg-white border border-gray-200 self-start p-3 rounded-2xl rounded-tl-none w-fit">
                            <div class="flex gap-1">
                                <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce"></span>
                                <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce delay-75"></span>
                                <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce delay-150"></span>
=======
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
    <div class="fixed bottom-6 right-6 flex flex-col items-end gap-3 font-sans" style="max-width: 350px;">
        <transition name="fade">
            <div v-if="isOpen"
                class="w-[350px] h-[450px] bg-white rounded-2xl shadow-2xl flex flex-col overflow-hidden border border-gray-200">
                <div class="bg-custom-blue p-4 flex justify-between items-center text-white">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-white/20 rounded-full flex items-center justify-center">🤖</div>
                        <div>
                            <h3 class="font-bold text-sm">Ri (AI Assistant)</h3>
                            <div class="flex items-center gap-1">
                                <span class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></span>
                                <span class="text-xs opacity-80">Online</span>
>>>>>>> 43038e5e5bf8911b8039c34d6902b2db09ef1590
                            </div>
                        </div>

                    </div>
                    <button @click="toggleChat" class="hover:bg-white/20 p-1 rounded transition">&times;</button>
                </div>

<<<<<<< HEAD
                    <!-- Chat Box -->
                    <div class="p-3 bg-white border-t flex gap-2">
                        <input v-model="message" @keyup.enter="sendMessage" type="text"
                            placeholder="Tanya rekomendasi makanan..."
                            class="flex-1 bg-gray-100 rounded-full px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-custom-blue/50"
                            :disabled="isLoading">
                        <button @click="sendMessage" :disabled="isLoading"
                            class="bg-custom-blue text-white w-10 h-10 rounded-full flex items-center justify-center hover:bg-blue-700 disabled:opacity-50 transition">
                            ➤
                        </button>
=======
                <div ref="chatContainer" class="flex-1 p-4 overflow-y-auto flex flex-col gap-3 bg-gray-50">
                    <div v-for="(chat, index) in chats" :key="index"
                        class="max-w-[90%] px-4 py-3 rounded-2xl text-[15px] leading-relaxed whitespace-pre-wrap"
                        :class="chat.isBot ? 'bg-white border border-gray-200 text-gray-700 rounded-tl-none' : 'bg-custom-blue text-white rounded-tr-none'"
                        :style="chat.isBot ? 'align-self: flex-start;' : 'align-self: flex-end;'">
                        {{ chat.text }}
                    </div>

                    <div v-if="isLoading"
                        class="bg-white border border-gray-200 self-start p-3 rounded-2xl rounded-tl-none w-fit">
                        <div class="flex gap-1">
                            <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce"></span>
                            <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce delay-75"></span>
                            <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce delay-150"></span>
                        </div>
>>>>>>> 43038e5e5bf8911b8039c34d6902b2db09ef1590
                    </div>
                </div>

                <div class="p-3 bg-white border-t flex gap-2">
                    <input v-model="message" @keyup.enter="sendMessage" type="text"
                        placeholder="Tanya rekomendasi makanan..."
                        class="flex-1 bg-gray-100 rounded-full px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-custom-blue/50"
                        :disabled="isLoading">
                    <button @click="sendMessage" :disabled="isLoading"
                        class="bg-custom-blue text-white w-10 h-10 rounded-full flex items-center justify-center hover:bg-blue-700 disabled:opacity-50 transition">
                        ➤
                    </button>
                </div>
            </div>
        </transition>

        <transition name="scale">
            <button v-if="!isOpen" @click="toggleChat"
                class="w-14 h-14 bg-custom-blue rounded-full shadow-lg flex items-center justify-center hover:scale-110 transition-transform duration-300 text-white text-2xl absolute bottom-0 right-0">
                <span>💬</span>
            </button>
        </transition>
    </div>
</template>

<style scoped>
.fixed {
<<<<<<< HEAD
    position: fixed;
    bottom: 6px;
    right: 6px;
}

.overflow-wrap-break-word {
    /* Properti yang lebih modern. Memaksa kata yang terlalu panjang 
    (misalnya URL atau string tanpa spasi) untuk pecah saat mencapai batas 
    lebar container (max-w-[85%]).
    */
    overflow-wrap: break-word;
    word-wrap: break-word;
    /* Fallback untuk browser lama */
=======
    position: fixed !important;
    bottom: 24px !important;
    right: 24px !important;
    z-index: 99999 !important;
>>>>>>> 43038e5e5bf8911b8039c34d6902b2db09ef1590
}

/* Chat Window Transition */
.fade-enter-active,
.fade-leave-active {
    transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
    transform: translateY(20px) scale(0.95);
}

/* Button Transition */
.scale-enter-active,
.scale-leave-active {
    transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.scale-enter-from,
.scale-leave-to {
    opacity: 0;
    transform: scale(0);
}
</style>