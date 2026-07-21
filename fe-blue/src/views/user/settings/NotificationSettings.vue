<script setup>
import { ref, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { storeToRefs } from 'pinia'

const authStore = useAuthStore()
const { user } = storeToRefs(authStore)

const notifications = ref({
  order_updates: true,
  promotions: false,
  price_drops: true,
  new_messages: true,
  review_reminders: true,
  newsletter: false
})

const saving = ref(false)
const saved = ref(false)

const loadPrefs = () => {
  if (user.value?.notification_prefs) {
    notifications.value = { ...notifications.value, ...user.value.notification_prefs }
  }
}

onMounted(loadPrefs)

const handleSave = async () => {
  saving.value = true
  const ok = await authStore.updateSettings({ notification_prefs: notifications.value })
  saving.value = false

  if (ok) {
    saved.value = true
    setTimeout(() => (saved.value = false), 3000)
  }
}
</script>

<template>
  <div class="flex flex-col gap-8 w-full">
    <!-- Page Header -->
    <div class="flex flex-col gap-1">
      <h1 class="font-bold text-2xl lg:text-3xl text-custom-black dark:text-white">Notifikasi</h1>
      <p class="text-custom-grey dark:text-gray-400 font-medium">Atur preferensi notifikasi yang ingin kamu terima.</p>
    </div>

    <!-- Success Toast -->
    <Transition
      enter-active-class="transition-all duration-300 ease-out"
      enter-from-class="opacity-0 -translate-y-1"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition-all duration-300 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0 -translate-y-1"
    >
      <div
        v-if="saved"
        class="flex items-center gap-3 p-4 rounded-2xl bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800"
      >
        <div class="shrink-0 w-8 h-8 rounded-full bg-green-100 dark:bg-green-900/40 flex items-center justify-center">
          <svg class="w-4 h-4 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
        </div>
        <p class="text-green-700 dark:text-green-400 font-medium text-sm">Preferensi notifikasi berhasil disimpan!</p>
      </div>
    </Transition>

    <!-- Notification Cards -->
    <div class="flex flex-col gap-5">
      <!-- Transaksi -->
      <div class="bg-white dark:bg-white/[0.02] rounded-2xl border border-gray-100 dark:border-white/10 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-white/10">
          <h2 class="font-bold text-base text-custom-black dark:text-white flex items-center gap-2">
            <svg class="size-5 text-custom-blue" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
            </svg>
            Transaksi & Pesanan
          </h2>
        </div>
        <div class="divide-y divide-gray-50 dark:divide-white/5">
          <label class="flex items-center justify-between px-6 py-4 cursor-pointer hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition-colors">
            <div>
              <p class="font-semibold text-sm text-custom-black dark:text-white">Update Pesanan</p>
              <p class="text-custom-grey dark:text-gray-500 text-xs mt-0.5">Notifikasi status pengiriman dan pembayaran</p>
            </div>
            <div class="relative">
              <input type="checkbox" v-model="notifications.order_updates" class="sr-only peer" />
              <div class="w-11 h-6 bg-gray-200 dark:bg-white/10 peer-focus:ring-4 peer-focus:ring-custom-blue/20 rounded-full peer peer-checked:after:translate-x-full peer-checked:bg-custom-blue after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all after:shadow-sm"></div>
            </div>
          </label>
          <label class="flex items-center justify-between px-6 py-4 cursor-pointer hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition-colors">
            <div>
              <p class="font-semibold text-sm text-custom-black dark:text-white">Reminder Review</p>
              <p class="text-custom-grey dark:text-gray-500 text-xs mt-0.5">Pengingat untuk memberi ulasan produk yang sudah diterima</p>
            </div>
            <div class="relative">
              <input type="checkbox" v-model="notifications.review_reminders" class="sr-only peer" />
              <div class="w-11 h-6 bg-gray-200 dark:bg-white/10 peer-focus:ring-4 peer-focus:ring-custom-blue/20 rounded-full peer peer-checked:after:translate-x-full peer-checked:bg-custom-blue after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all after:shadow-sm"></div>
            </div>
          </label>
        </div>
      </div>

      <!-- Promosi -->
      <div class="bg-white dark:bg-white/[0.02] rounded-2xl border border-gray-100 dark:border-white/10 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-white/10">
          <h2 class="font-bold text-base text-custom-black dark:text-white flex items-center gap-2">
            <svg class="size-5 text-custom-blue" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" />
            </svg>
            Promosi & Marketing
          </h2>
        </div>
        <div class="divide-y divide-gray-50 dark:divide-white/5">
          <label class="flex items-center justify-between px-6 py-4 cursor-pointer hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition-colors">
            <div>
              <p class="font-semibold text-sm text-custom-black dark:text-white">Promo & Diskon</p>
              <p class="text-custom-grey dark:text-gray-500 text-xs mt-0.5">Info promo, flash sale, dan voucher eksklusif</p>
            </div>
            <div class="relative">
              <input type="checkbox" v-model="notifications.promotions" class="sr-only peer" />
              <div class="w-11 h-6 bg-gray-200 dark:bg-white/10 peer-focus:ring-4 peer-focus:ring-custom-blue/20 rounded-full peer peer-checked:after:translate-x-full peer-checked:bg-custom-blue after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all after:shadow-sm"></div>
            </div>
          </label>
          <label class="flex items-center justify-between px-6 py-4 cursor-pointer hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition-colors">
            <div>
              <p class="font-semibold text-sm text-custom-black dark:text-white">Penurunan Harga</p>
              <p class="text-custom-grey dark:text-gray-500 text-xs mt-0.5">Notifikasi saat harga produk di wishlist turun</p>
            </div>
            <div class="relative">
              <input type="checkbox" v-model="notifications.price_drops" class="sr-only peer" />
              <div class="w-11 h-6 bg-gray-200 dark:bg-white/10 peer-focus:ring-4 peer-focus:ring-custom-blue/20 rounded-full peer peer-checked:after:translate-x-full peer-checked:bg-custom-blue after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all after:shadow-sm"></div>
            </div>
          </label>
          <label class="flex items-center justify-between px-6 py-4 cursor-pointer hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition-colors">
            <div>
              <p class="font-semibold text-sm text-custom-black dark:text-white">Newsletter</p>
              <p class="text-custom-grey dark:text-gray-500 text-xs mt-0.5">Email mingguan dengan rekomendasi produk</p>
            </div>
            <div class="relative">
              <input type="checkbox" v-model="notifications.newsletter" class="sr-only peer" />
              <div class="w-11 h-6 bg-gray-200 dark:bg-white/10 peer-focus:ring-4 peer-focus:ring-custom-blue/20 rounded-full peer peer-checked:after:translate-x-full peer-checked:bg-custom-blue after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all after:shadow-sm"></div>
            </div>
          </label>
        </div>
      </div>

      <!-- Pesan -->
      <div class="bg-white dark:bg-white/[0.02] rounded-2xl border border-gray-100 dark:border-white/10 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-white/10">
          <h2 class="font-bold text-base text-custom-black dark:text-white flex items-center gap-2">
            <svg class="size-5 text-custom-blue" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
            </svg>
            Pesan & Chat
          </h2>
        </div>
        <label class="flex items-center justify-between px-6 py-4 cursor-pointer hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition-colors">
          <div>
            <p class="font-semibold text-sm text-custom-black dark:text-white">Pesan Baru</p>
            <p class="text-custom-grey dark:text-gray-500 text-xs mt-0.5">Notifikasi saat ada pesan baru dari penjual atau pembeli</p>
          </div>
          <div class="relative">
            <input type="checkbox" v-model="notifications.new_messages" class="sr-only peer" />
            <div class="w-11 h-6 bg-gray-200 dark:bg-white/10 peer-focus:ring-4 peer-focus:ring-custom-blue/20 rounded-full peer peer-checked:after:translate-x-full peer-checked:bg-custom-blue after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all after:shadow-sm"></div>
          </div>
        </label>
      </div>
    </div>

    <!-- Save Button -->
    <div class="flex justify-end">
      <button
        @click="handleSave"
        :disabled="saving"
        class="flex items-center justify-center h-12 px-8 rounded-full bg-custom-blue text-white font-bold text-base hover:bg-blue-700 hover:shadow-lg hover:shadow-custom-blue/20 active:scale-[0.98] transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed"
      >
        <div v-if="saving" class="size-5 border-2 border-white border-t-transparent rounded-full animate-spin mr-2"></div>
        Simpan Preferensi
      </button>
    </div>
  </div>
</template>
