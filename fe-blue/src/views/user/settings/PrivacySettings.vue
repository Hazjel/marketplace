<script setup>
import { ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { storeToRefs } from 'pinia'

const authStore = useAuthStore()
const { user } = storeToRefs(authStore)

const privacy = ref({
  profile_visible: true,
  show_online_status: true,
  show_purchase_history: false
})

const saving = ref(false)
const saved = ref(false)

const handleSave = async () => {
  saving.value = true
  await new Promise((r) => setTimeout(r, 800))
  saving.value = false
  saved.value = true
  setTimeout(() => (saved.value = false), 3000)
}
</script>

<template>
  <div class="flex flex-col gap-8 w-full">
    <!-- Page Header -->
    <div class="flex flex-col gap-1">
      <h1 class="font-bold text-2xl lg:text-3xl text-custom-black dark:text-white">Privasi</h1>
      <p class="text-custom-grey dark:text-gray-400 font-medium">Kontrol siapa yang dapat melihat informasi profilmu.</p>
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
        <p class="text-green-700 dark:text-green-400 font-medium text-sm">Pengaturan privasi berhasil disimpan!</p>
      </div>
    </Transition>

    <!-- Privacy Settings Card -->
    <div class="bg-white dark:bg-white/[0.02] rounded-2xl border border-gray-100 dark:border-white/10 shadow-sm overflow-hidden">
      <div class="px-6 py-4 border-b border-gray-100 dark:border-white/10">
        <h2 class="font-bold text-base text-custom-black dark:text-white flex items-center gap-2">
          <svg class="size-5 text-custom-blue" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
          </svg>
          Visibilitas Profil
        </h2>
      </div>
      <div class="divide-y divide-gray-50 dark:divide-white/5">
        <label class="flex items-center justify-between px-6 py-4 cursor-pointer hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition-colors">
          <div>
            <p class="font-semibold text-sm text-custom-black dark:text-white">Profil Publik</p>
            <p class="text-custom-grey dark:text-gray-500 text-xs mt-0.5">Izinkan pengguna lain melihat profil dan nama kamu</p>
          </div>
          <div class="relative">
            <input type="checkbox" v-model="privacy.profile_visible" class="sr-only peer" />
            <div class="w-11 h-6 bg-gray-200 dark:bg-white/10 peer-focus:ring-4 peer-focus:ring-custom-blue/20 rounded-full peer peer-checked:after:translate-x-full peer-checked:bg-custom-blue after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all after:shadow-sm"></div>
          </div>
        </label>
        <label class="flex items-center justify-between px-6 py-4 cursor-pointer hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition-colors">
          <div>
            <p class="font-semibold text-sm text-custom-black dark:text-white">Status Online</p>
            <p class="text-custom-grey dark:text-gray-500 text-xs mt-0.5">Tampilkan kapan terakhir kamu online</p>
          </div>
          <div class="relative">
            <input type="checkbox" v-model="privacy.show_online_status" class="sr-only peer" />
            <div class="w-11 h-6 bg-gray-200 dark:bg-white/10 peer-focus:ring-4 peer-focus:ring-custom-blue/20 rounded-full peer peer-checked:after:translate-x-full peer-checked:bg-custom-blue after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all after:shadow-sm"></div>
          </div>
        </label>
        <label class="flex items-center justify-between px-6 py-4 cursor-pointer hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition-colors">
          <div>
            <p class="font-semibold text-sm text-custom-black dark:text-white">Riwayat Pembelian</p>
            <p class="text-custom-grey dark:text-gray-500 text-xs mt-0.5">Izinkan penjual melihat riwayat belanja kamu</p>
          </div>
          <div class="relative">
            <input type="checkbox" v-model="privacy.show_purchase_history" class="sr-only peer" />
            <div class="w-11 h-6 bg-gray-200 dark:bg-white/10 peer-focus:ring-4 peer-focus:ring-custom-blue/20 rounded-full peer peer-checked:after:translate-x-full peer-checked:bg-custom-blue after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all after:shadow-sm"></div>
          </div>
        </label>
      </div>
    </div>

    <!-- Account Info Card -->
    <div class="bg-white dark:bg-white/[0.02] rounded-2xl border border-gray-100 dark:border-white/10 shadow-sm overflow-hidden">
      <div class="px-6 py-4 border-b border-gray-100 dark:border-white/10">
        <h2 class="font-bold text-base text-custom-black dark:text-white flex items-center gap-2">
          <svg class="size-5 text-custom-blue" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
          </svg>
          Data & Keamanan
        </h2>
      </div>
      <div class="px-6 py-5">
        <div class="flex flex-col gap-4">
          <div class="flex items-center justify-between">
            <div>
              <p class="font-semibold text-sm text-custom-black dark:text-white">Login via Google</p>
              <p class="text-custom-grey dark:text-gray-500 text-xs mt-0.5">
                {{ user?.email || 'Belum terhubung' }}
              </p>
            </div>
            <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-green-50 dark:bg-green-900/20 text-green-600 dark:text-green-400 text-xs font-bold rounded-full">
              <svg class="size-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
              </svg>
              Terhubung
            </span>
          </div>
          <div class="h-px bg-gray-100 dark:bg-white/10"></div>
          <div class="flex items-center justify-between">
            <div>
              <p class="font-semibold text-sm text-custom-black dark:text-white">Bergabung Sejak</p>
              <p class="text-custom-grey dark:text-gray-500 text-xs mt-0.5">
                {{ user?.created_at ? new Date(user.created_at).toLocaleDateString('id-ID', { year: 'numeric', month: 'long', day: 'numeric' }) : '-' }}
              </p>
            </div>
          </div>
        </div>
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
        Simpan Pengaturan
      </button>
    </div>
  </div>
</template>
