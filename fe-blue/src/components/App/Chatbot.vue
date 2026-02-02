<script setup>
import { ref, nextTick } from 'vue'
import axios from 'axios' // Pakai axios plugin project kamu

const chatAxios = axios.create({
  baseURL: import.meta.env.VITE_AI_SERVICE_URL || 'http://localhost:8001'
})

const isOpen = ref(false)
const message = ref('')
const isLoading = ref(false)
const chatContainer = ref(null)

// Pesan awal
const chats = ref([
  {
    text: 'Halo! Aku Ri, asisten virtual disini~ Ada yang bisa dibantu soal produk di Bluee?',
    isBot: true
  }
])

const toggleChat = () => {
  isOpen.value = !isOpen.value
  scrollToBottom()
}

const scrollToBottom = () => {
  nextTick(() => {
    if (chatContainer.value) {
      chatContainer.value.scrollTop = chatContainer.value.scrollHeight
    }
  })
}

const sendMessage = async () => {
  const rawMsg = message.value
  const userMsg = rawMsg.trim() // Ambil nilai yang sudah di-trim

  // 2. VALIDASI KETAT: Jika kosong setelah di-trim, batalkan dan jangan kirim
  if (!userMsg) {
    message.value = '' // Kosongkan input field yang berisi spasi saja
    return
  }
  chats.value.push({ text: userMsg, isBot: false })
  message.value = ''
  isLoading.value = true
  scrollToBottom()

  try {
    // 2. Kirim ke Laravel (/api/chat)
    const response = await chatAxios.post(
      '/predict',
      {
        message: userMsg
      },
      {
        headers: {
          'Content-Type': 'application/json'
        }
      }
    )

    // 3. Tampilkan balasan Ri
    const reply = response.data.reply
    chats.value.push({ text: reply, isBot: true })
  } catch (error) {
    console.error(error)
    chats.value.push({ text: 'Duh, koneksi putus.. coba lagi ya~', isBot: true })
  } finally {
    isLoading.value = false
    scrollToBottom()
  }
}
</script>

<template>
  <div class="fixed z-[9999] bottom-4 right-4 w-auto flex flex-col items-end gap-3 font-sans">
    <transition
      enter-active-class="transition-all duration-400 ease-[cubic-bezier(0.16,1,0.3,1)]"
      leave-active-class="transition-all duration-400 ease-[cubic-bezier(0.16,1,0.3,1)]"
      enter-from-class="opacity-0 translate-y-5 scale-95"
      leave-to-class="opacity-0 translate-y-5 scale-95"
    >
      <div
        v-if="isOpen"
        class="w-[85vw] max-w-[350px] h-[60vh] md:h-[450px] bg-white rounded-2xl shadow-2xl flex flex-col overflow-hidden border border-gray-200"
      >
        <div class="bg-custom-blue p-4 flex justify-between items-center text-white shrink-0">
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
          <button class="hover:bg-white/20 p-1 rounded transition" @click="toggleChat">
            &times;
          </button>
        </div>

        <div ref="chatContainer" class="flex-1 p-4 overflow-y-auto flex flex-col gap-3 bg-gray-50">
          <div
            v-for="(chat, index) in chats"
            :key="index"
            class="max-w-[90%] px-4 py-3 rounded-2xl text-[15px] leading-relaxed whitespace-pre-wrap"
            :class="
              chat.isBot
                ? 'bg-white border border-gray-200 text-gray-700 rounded-tl-none'
                : 'bg-custom-blue text-white rounded-tr-none'
            "
            :style="chat.isBot ? 'align-self: flex-start;' : 'align-self: flex-end;'"
          >
            {{ chat.text }}
          </div>

          <div
            v-if="isLoading"
            class="bg-white border border-gray-200 self-start p-3 rounded-2xl rounded-tl-none w-fit"
          >
            <div class="flex gap-1">
              <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce"></span>
              <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce delay-75"></span>
              <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce delay-150"></span>
            </div>
          </div>
        </div>

        <div class="p-3 bg-white border-t flex gap-2 shrink-0">
          <input
            v-model="message"
            type="text"
            placeholder="Tanya rekomendasi makanan..."
            class="flex-1 bg-gray-100 rounded-full px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-custom-blue/50"
            :disabled="isLoading"
            @keyup.enter="sendMessage"
          />
          <button
            :disabled="isLoading"
            class="bg-custom-blue text-white w-10 h-10 rounded-full flex items-center justify-center hover:bg-blue-700 disabled:opacity-50 transition"
            @click="sendMessage"
          >
            ➤
          </button>
        </div>
      </div>
    </transition>

    <transition
      enter-active-class="transition-all duration-300 ease-[cubic-bezier(0.34,1.56,0.64,1)]"
      leave-active-class="transition-all duration-300 ease-[cubic-bezier(0.34,1.56,0.64,1)]"
      enter-from-class="opacity-0 scale-0"
      leave-to-class="opacity-0 scale-0"
    >
      <button
        v-if="!isOpen"
        class="w-14 h-14 bg-custom-blue rounded-full shadow-lg flex items-center justify-center hover:scale-110 transition-transform duration-300 text-white text-2xl absolute bottom-0 right-0"
        @click="toggleChat"
      >
        <span>💬</span>
      </button>
    </transition>
  </div>
</template>
