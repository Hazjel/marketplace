<script setup>
import { ref, reactive, nextTick } from 'vue'
import { MessageCircle } from 'lucide-vue-next'

const AI_URL = import.meta.env.VITE_AI_SERVICE_URL || 'http://localhost:8001'

// ---------------------------------------------------------------------------
// STATE
// ---------------------------------------------------------------------------
const isOpen        = ref(false)
const message       = ref('')
const isStreaming   = ref(false)
const isWaiting     = ref(false)
const chatContainer = ref(null)
const sessionId     = ref(null)

const chats = ref([
  {
    text:      'Halo! Aku Ri, asisten virtual Blukios~ Ada yang bisa dibantu soal produk? 😊',
    isBot:     true,
    streaming: false,
    rating:    null,
    products:  []
  }
])

// ---------------------------------------------------------------------------
// HELPERS
// ---------------------------------------------------------------------------
const scrollToBottom = () =>
  nextTick(() => {
    if (chatContainer.value)
      chatContainer.value.scrollTop = chatContainer.value.scrollHeight
  })

const toggleChat = () => {
  isOpen.value = !isOpen.value
  scrollToBottom()
}

const formatPrice = (price) =>
  new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 })
    .format(price)

// Strip markdown symbols dari teks Ri — qwen3 kadang masih pakai ** dan ###
const stripMarkdown = (text) =>
  text
    .replace(/\*\*(.+?)\*\*/g, '$1')   // **bold** → bold
    .replace(/^#{1,3}\s+/gm, '')        // ### heading → plain
    .replace(/^---+$/gm, '')            // --- divider → hapus
    .trim()

// ---------------------------------------------------------------------------
// CORE: Streaming Chat via Fetch ReadableStream
// ---------------------------------------------------------------------------
const sendMessage = async () => {
  const userMsg = message.value.trim()
  if (!userMsg || isStreaming.value) return

  chats.value.push({ text: userMsg, isBot: false, streaming: false, rating: null, products: [] })
  message.value = ''
  scrollToBottom()

  // Buat botMsg sebagai reactive object — dijamin Vue track semua propertinya
  const botMsg = reactive({ text: '', isBot: true, streaming: true, rating: null, products: [] })
  chats.value.push(botMsg)
  isWaiting.value  = true
  isStreaming.value = true

  // Token queue — diisi oleh network loop, dikosongkan oleh RAF loop
  const tokenQueue = []
  let rafId = null

  const drainQueue = () => {
    if (tokenQueue.length > 0) {
      botMsg.text += tokenQueue.shift()
      scrollToBottom()
    }
    if (tokenQueue.length > 0) {
      rafId = requestAnimationFrame(drainQueue)
    } else {
      rafId = null
    }
  }

  try {
    const res = await fetch(`${AI_URL}/predict/stream`, {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ message: userMsg, session_id: sessionId.value })
    })

    if (!res.ok) throw new Error(`HTTP ${res.status}`)

    const reader  = res.body.getReader()
    const decoder = new TextDecoder()
    let   buffer  = ''
    let   isDone  = false

    while (!isDone) {
      const { done, value } = await reader.read()
      if (done) break

      buffer += decoder.decode(value, { stream: true })

      const lines = buffer.split('\n')
      buffer = lines.pop() ?? ''

      for (const line of lines) {
        if (!line.startsWith('data: ')) continue
        const rawJson = line.slice(6).trim()
        if (!rawJson) continue

        let event
        try {
          event = JSON.parse(rawJson)
        } catch (e) {
          console.warn('[Chatbot] Incomplete JSON chunk, skip:', rawJson.substring(0, 50))
          continue
        }

        if (event.session_id) sessionId.value = event.session_id

        if (event.token) {
          isWaiting.value = false
          tokenQueue.push(event.token)
          if (!rafId) rafId = requestAnimationFrame(drainQueue)
        }

        if (event.done) {
          isDone = true
          if (event.products && event.products.length > 0) {
            botMsg.products = event.products
            console.log('[Chatbot] Products received:', event.products.length, event.products.map(p => p.name))
          } else {
            console.log('[Chatbot] Done event - no products:', event)
          }
          break
        }
      }
    }
  } catch (err) {
    console.error('[Chatbot] Stream error:', err)
    botMsg.text = sessionId.value
      ? 'Duh, koneksi putus.. coba kirim ulang pertanyaan ya~ 🙏'
      : 'Duh, sesi bermasalah.. coba mulai percakapan baru ya~ 🙏'
  } finally {
    // Flush sisa queue sebelum selesai
    if (rafId) cancelAnimationFrame(rafId)
    if (tokenQueue.length > 0) botMsg.text += tokenQueue.join('')
    botMsg.streaming  = false
    isStreaming.value  = false
    isWaiting.value   = false
    scrollToBottom()
  }
}


// ---------------------------------------------------------------------------
// FEEDBACK
// ---------------------------------------------------------------------------
const sendFeedback = async (msg, rating) => {
  if (msg.rating !== null || !sessionId.value) return
  msg.rating = rating
  try {
    await fetch(`${AI_URL}/feedback`, {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ session_id: sessionId.value, rating })
    })
  } catch (err) {
    console.warn('[Chatbot] Feedback error:', err)
  }
}
</script>

<template>
  <div class="fixed z-[9999] bottom-6 right-24 w-auto flex flex-col items-end gap-3 font-sans">

    <!-- ── Chat Window ─────────────────────────────────────────────── -->
    <transition
      enter-active-class="transition-all duration-400 ease-[cubic-bezier(0.16,1,0.3,1)]"
      leave-active-class="transition-all duration-400 ease-[cubic-bezier(0.16,1,0.3,1)]"
      enter-from-class="opacity-0 translate-y-5 scale-95"
      leave-to-class="opacity-0 translate-y-5 scale-95"
    >
      <div
        v-if="isOpen"
        class="w-[85vw] max-w-[370px] h-[65vh] md:h-[520px] bg-white dark:bg-surface-card rounded-2xl shadow-2xl flex flex-col overflow-hidden border border-gray-200 dark:border-white/10"
      >
        <!-- Header -->
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
          <button class="hover:bg-white/20 p-1 rounded transition" @click="toggleChat">&times;</button>
        </div>

        <!-- Messages -->
        <div
          ref="chatContainer"
          class="flex-1 p-4 overflow-y-auto flex flex-col gap-3 bg-gray-50 dark:bg-surface"
        >
          <div
            v-for="(chat, index) in chats"
            :key="index"
            class="flex flex-col gap-1"
            :style="chat.isBot ? 'align-items: flex-start' : 'align-items: flex-end'"
          >
            <!-- Bubble (berisi teks + product cards jika ada) -->
            <div
              class="px-4 py-3 rounded-2xl text-[15px] leading-relaxed"
              :class="[
                chat.isBot
                  ? 'bg-white dark:bg-surface-card border border-gray-200 dark:border-white/10 text-gray-700 dark:text-gray-200 rounded-tl-none'
                  : 'bg-custom-blue text-white rounded-tr-none',
                (chat.isBot && chat.products && chat.products.length) ? 'w-[95%]' : 'max-w-[90%]'
              ]"
            >
              <!-- Teks respons (strip markdown dari qwen3) -->
              <span class="whitespace-pre-wrap">{{ stripMarkdown(chat.text) }}</span>
              <span
                v-if="chat.streaming && !isWaiting"
                class="inline-block w-[2px] h-[1em] bg-current ml-0.5 align-middle animate-pulse"
              ></span>

              <!-- ── Product Cards di dalam bubble ─────────────────── -->
              <div
                v-if="chat.isBot && !chat.streaming && chat.products && chat.products.length"
                class="mt-3 flex flex-col gap-1.5"
              >
                <!-- Separator -->
                <div class="flex items-center gap-1.5 mb-1">
                  <div class="h-px flex-1 bg-gray-100 dark:bg-white/10"></div>
                  <span class="text-[10px] text-gray-400 dark:text-gray-500 font-medium shrink-0">
                    {{ chat.products.length }} produk relevan
                  </span>
                  <div class="h-px flex-1 bg-gray-100 dark:bg-white/10"></div>
                </div>

                <!-- Vertical product list -->
                <a
                  v-for="p in chat.products"
                  :key="p.id"
                  :href="`/product/${p.slug || p.id}`"
                  target="_blank"
                  class="flex items-center gap-3 w-full bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-white/10 rounded-xl p-2.5 hover:shadow-md hover:border-custom-blue/50 dark:hover:border-custom-blue/40 hover:bg-blue-50/50 dark:hover:bg-blue-900/10 transition-all duration-200 no-underline group"
                >
                  <!-- Thumbnail: gambar asli atau fallback emoji -->
                  <div class="w-11 h-11 shrink-0 rounded-lg overflow-hidden bg-gradient-to-br from-blue-100 to-indigo-100 dark:from-blue-900/30 dark:to-indigo-900/30 flex items-center justify-center text-lg">
                    <img
                      v-if="p.thumbnail"
                      :src="p.thumbnail"
                      :alt="p.name"
                      class="w-full h-full object-cover"
                    />
                    <span v-else>📦</span>
                  </div>

                  <!-- Info -->
                  <div class="flex-1 min-w-0">
                    <p class="text-[12px] font-semibold text-gray-800 dark:text-gray-100 leading-tight truncate">
                      {{ p.name }}
                    </p>
                    <p class="text-[13px] font-bold text-custom-blue mt-0.5">
                      {{ formatPrice(p.price) }}
                    </p>
                    <div class="flex items-center gap-1.5 mt-0.5">
                      <p class="text-[10px] text-gray-400 dark:text-gray-500 truncate">
                        {{ p.store || 'Blukios' }}
                      </p>
                      <span
                        class="text-[9px] px-1.5 py-0.5 rounded-full shrink-0"
                        :class="p.condition === 'new'
                          ? 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400'
                          : 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400'"
                      >
                        {{ p.condition === 'new' ? 'Baru' : 'Bekas' }}
                      </span>
                    </div>
                  </div>

                  <!-- Arrow -->
                  <span class="text-gray-300 dark:text-gray-600 group-hover:text-custom-blue group-hover:translate-x-0.5 transition-all duration-200 text-sm shrink-0">→</span>
                </a>
              </div>
            </div>

            <!-- Feedback buttons -->
            <div
              v-if="chat.isBot && !chat.streaming && index > 0"
              class="flex items-center gap-1 px-1"
            >
              <span class="text-[10px] text-gray-400 dark:text-gray-500 mr-1">
                {{ chat.rating !== null ? 'Terima kasih!' : 'Berguna?' }}
              </span>
              <button
                v-if="chat.rating === null"
                class="text-sm hover:scale-125 transition-transform"
                title="Berguna"
                @click="sendFeedback(chat, 5)"
              >👍</button>
              <button
                v-if="chat.rating === null"
                class="text-sm hover:scale-125 transition-transform"
                title="Tidak berguna"
                @click="sendFeedback(chat, 1)"
              >👎</button>
              <span v-if="chat.rating !== null" class="text-sm">
                {{ chat.rating >= 3 ? '👍' : '👎' }}
              </span>
            </div>
          </div>

          <!-- Typing indicator -->
          <div
            v-if="isWaiting"
            class="bg-white dark:bg-surface-card border border-gray-200 dark:border-white/10 self-start p-3 rounded-2xl rounded-tl-none w-fit"
          >
            <div class="flex gap-1">
              <span class="w-2 h-2 bg-gray-400 dark:bg-gray-500 rounded-full animate-bounce"></span>
              <span class="w-2 h-2 bg-gray-400 dark:bg-gray-500 rounded-full animate-bounce delay-75"></span>
              <span class="w-2 h-2 bg-gray-400 dark:bg-gray-500 rounded-full animate-bounce delay-150"></span>
            </div>
          </div>
        </div>

        <!-- Input -->
        <div class="p-3 bg-white dark:bg-surface-card border-t border-gray-200 dark:border-white/10 flex gap-2 shrink-0">
          <input
            v-model="message"
            type="text"
            placeholder="Tanya produk Blukios..."
            class="flex-1 bg-gray-100 dark:bg-white/5 text-custom-black dark:text-white placeholder-gray-400 dark:placeholder-gray-500 rounded-full px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-custom-blue/50 dark:focus:ring-custom-blue/30"
            :disabled="isStreaming"
            @keyup.enter="sendMessage"
          />
          <button
            :disabled="isStreaming"
            class="bg-custom-blue text-white w-10 h-10 rounded-full flex items-center justify-center hover:bg-blue-700 disabled:opacity-50 transition"
            @click="sendMessage"
          >
            <span v-if="isStreaming" class="w-3 h-3 bg-white rounded-sm"></span>
            <span v-else>➤</span>
          </button>
        </div>
      </div>
    </transition>

    <!-- ── Toggle Button ───────────────────────────────────────────── -->
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
        <MessageCircle :size="26" :stroke-width="2.25" />
      </button>
    </transition>
  </div>
</template>
