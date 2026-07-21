<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useCartStore } from '@/stores/cart'

const router = useRouter()
const authStore = useAuthStore()

const showConfirm = ref(false)
const confirmText = ref('')
const deleting = ref(false)
const error = ref(null)

const canDelete = ref(false)

const checkConfirmText = () => {
  canDelete.value = confirmText.value === 'HAPUS AKUN'
}

const handleDelete = async () => {
  if (!canDelete.value) return

  deleting.value = true
  error.value = null

  const result = await authStore.deleteAccount()

  if (!result.success) {
    error.value = result.message
    deleting.value = false
    return
  }

  const cartStore = useCartStore()
  cartStore.onLogout()
  await authStore.logout()
  router.push({ name: 'auth.login' })
}
</script>

<template>
  <div class="flex flex-col gap-8 w-full max-w-2xl">
    <!-- Page Header -->
    <div class="flex flex-col gap-1">
      <h1 class="font-bold text-2xl lg:text-3xl text-custom-black dark:text-white">Hapus Akun</h1>
      <p class="text-custom-grey dark:text-gray-400 font-medium">Tindakan ini bersifat permanen dan tidak dapat dibatalkan.</p>
    </div>

    <!-- Warning Card -->
    <div class="bg-red-50 dark:bg-red-900/10 rounded-2xl border border-red-200 dark:border-red-900/30 p-6">
      <div class="flex items-start gap-4">
        <div class="shrink-0 w-12 h-12 rounded-xl bg-red-100 dark:bg-red-900/30 flex items-center justify-center">
          <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
        </div>
        <div class="flex-1">
          <h3 class="font-bold text-red-800 dark:text-red-300 text-base mb-2">Perhatian!</h3>
          <ul class="text-red-700 dark:text-red-400/80 text-sm space-y-2">
            <li class="flex items-start gap-2">
              <svg class="size-4 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
              Semua data profil, riwayat transaksi, dan wishlist akan dihapus secara permanen
            </li>
            <li class="flex items-start gap-2">
              <svg class="size-4 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
              Jika kamu memiliki toko, semua produk dan data toko akan ikut terhapus
            </li>
            <li class="flex items-start gap-2">
              <svg class="size-4 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
              Saldo dompet yang tersisa tidak dapat dikembalikan
            </li>
            <li class="flex items-start gap-2">
              <svg class="size-4 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
              Email ini tidak bisa digunakan untuk mendaftar ulang
            </li>
          </ul>
        </div>
      </div>
    </div>

    <!-- Error -->
    <Transition
      enter-active-class="transition-all duration-300 ease-out"
      enter-from-class="opacity-0 -translate-y-1"
      enter-to-class="opacity-100 translate-y-0"
    >
      <div v-if="error" class="flex items-center gap-3 p-4 rounded-2xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800">
        <div class="shrink-0 w-8 h-8 rounded-full bg-red-100 dark:bg-red-900/40 flex items-center justify-center">
          <svg class="w-4 h-4 text-red-600 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </div>
        <p class="text-red-700 dark:text-red-400 font-medium text-sm">{{ error }}</p>
      </div>
    </Transition>

    <!-- Confirmation Section -->
    <div v-if="!showConfirm" class="flex justify-start">
      <button
        @click="showConfirm = true"
        class="flex items-center gap-2 h-12 px-6 rounded-full bg-red-50 dark:bg-red-900/10 border border-red-200 dark:border-red-900/30 text-red-600 dark:text-red-400 font-bold text-sm hover:bg-red-100 dark:hover:bg-red-900/20 hover:border-red-300 dark:hover:border-red-900/40 active:scale-[0.98] transition-all duration-300"
      >
        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
        </svg>
        Saya Ingin Menghapus Akun
      </button>
    </div>

    <!-- Confirmation Input -->
    <Transition
      enter-active-class="transition-all duration-500 ease-out"
      enter-from-class="opacity-0 translate-y-4 scale-95"
      enter-to-class="opacity-100 translate-y-0 scale-100"
    >
      <div v-if="showConfirm" class="bg-white dark:bg-white/[0.02] rounded-2xl border border-red-200 dark:border-red-900/30 p-6 flex flex-col gap-5">
        <div class="flex flex-col gap-2">
          <label class="font-semibold text-custom-black dark:text-white text-sm">
            Ketik <span class="text-red-600 dark:text-red-400 font-bold">HAPUS AKUN</span> untuk konfirmasi
          </label>
          <input
            v-model="confirmText"
            @input="checkConfirmText"
            type="text"
            placeholder="HAPUS AKUN"
            class="w-full h-12 px-4 bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-white/10 rounded-xl focus:border-red-500 focus:ring-2 focus:ring-red-500/20 outline-none transition-all font-mono text-custom-black dark:text-white placeholder-gray-400 dark:placeholder-gray-500 tracking-wider"
          />
        </div>

        <div class="flex items-center gap-3">
          <button
            @click="showConfirm = false; confirmText = ''; canDelete = false"
            class="flex-1 h-12 rounded-xl bg-gray-100 dark:bg-white/5 border border-gray-200 dark:border-white/10 text-custom-black dark:text-white font-bold text-sm hover:bg-gray-200 dark:hover:bg-white/10 transition-all"
          >
            Batalkan
          </button>
          <button
            @click="handleDelete"
            :disabled="!canDelete || deleting"
            class="flex-1 flex items-center justify-center h-12 rounded-xl bg-red-600 text-white font-bold text-sm hover:bg-red-700 hover:shadow-lg hover:shadow-red-600/20 active:scale-[0.98] transition-all duration-300 disabled:opacity-30 disabled:cursor-not-allowed disabled:hover:bg-red-600 disabled:hover:shadow-none disabled:active:scale-100"
          >
            <div v-if="deleting" class="size-5 border-2 border-white border-t-transparent rounded-full animate-spin mr-2"></div>
            Hapus Permanen
          </button>
        </div>
      </div>
    </Transition>
  </div>
</template>
