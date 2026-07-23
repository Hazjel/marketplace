<script setup>
import { ref, computed, onUnmounted } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { storeToRefs } from 'pinia'
import { axiosInstance } from '@/plugins/axios'
import { useToast } from 'vue-toastification'

const router = useRouter()
const toast = useToast()
const authStore = useAuthStore()
const { user } = storeToRefs(authStore)

const sending = ref(false)
const cooldown = ref(0)
let cooldownTimer = null

const isVerified = computed(() => !!user.value?.email_verified_at)

const startCooldown = () => {
  cooldown.value = 60
  cooldownTimer = setInterval(() => {
    cooldown.value--
    if (cooldown.value <= 0) clearInterval(cooldownTimer)
  }, 1000)
}

const resend = async () => {
  sending.value = true
  try {
    const res = await axiosInstance.post('/email/resend')
    toast.success(res.data?.message || 'Link verifikasi telah dikirim!')
    startCooldown()
  } catch (err) {
    toast.error(err.response?.data?.message || 'Gagal mengirim email verifikasi.')
  } finally {
    sending.value = false
  }
}

const checkStatus = async () => {
  await authStore.checkAuth()
  if (authStore.user?.email_verified_at) {
    toast.success('Email terverifikasi!')

    const isSellerBuild = import.meta.env.VITE_APP_TARGET === 'seller'
    const isSellerRole = authStore.user?.role === 'admin' || authStore.user?.role === 'store'

    if (isSellerRole && !isSellerBuild) {
      await authStore.initiateSso(import.meta.env.VITE_SELLER_APP_URL)
    } else if (!isSellerRole && isSellerBuild) {
      await authStore.initiateSso(import.meta.env.VITE_BUYER_APP_URL)
    } else if (authStore.user?.role === 'admin') {
      router.push({ name: 'admin.dashboard' })
    } else if (authStore.user?.role === 'store') {
      router.push({ name: 'user.dashboard', params: { username: authStore.user.username } })
    } else {
      router.push({ name: 'app.home' })
    }
  } else {
    toast.info('Belum terverifikasi. Cek inbox atau folder spam emailmu.')
  }
}

onUnmounted(() => {
  if (cooldownTimer) clearInterval(cooldownTimer)
})
</script>

<template>
  <div class="w-full max-w-md mx-auto flex flex-col gap-6 text-center py-12">
    <div class="size-20 mx-auto rounded-full bg-blue-50 dark:bg-blue-900/30 flex items-center justify-center">
      <svg class="size-10 text-custom-blue" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
      </svg>
    </div>

    <div class="flex flex-col gap-2">
      <h1 class="font-medium text-2xl text-custom-black dark:text-white">
        {{ isVerified ? 'Email Sudah Terverifikasi' : 'Verifikasi Emailmu' }}
      </h1>
      <p class="text-custom-grey dark:text-gray-400 text-sm leading-relaxed">
        <template v-if="isVerified">
          Akunmu sudah aktif sepenuhnya. Selamat berbelanja!
        </template>
        <template v-else>
          Kami sudah mengirim link verifikasi ke
          <span class="font-medium text-custom-black dark:text-white">{{ user?.email }}</span>.
          Klik link di email untuk mengaktifkan akunmu — cek juga folder spam.
        </template>
      </p>
    </div>

    <div v-if="!isVerified" class="flex flex-col gap-3">
      <button
        type="button"
        :disabled="sending || cooldown > 0"
        class="w-full h-12 flex items-center justify-center rounded-md bg-custom-blue text-white font-medium text-base hover:bg-primary-deep transition-all disabled:opacity-50 disabled:cursor-not-allowed"
        @click="resend"
      >
        <div v-if="sending" class="size-5 border-2 border-white border-t-transparent rounded-full animate-spin mr-2"></div>
        {{ cooldown > 0 ? `Kirim Ulang (${cooldown}s)` : 'Kirim Ulang Email Verifikasi' }}
      </button>
      <button
        type="button"
        class="w-full h-12 flex items-center justify-center rounded-md border border-gray-200 dark:border-white/10 font-medium text-sm text-custom-black dark:text-white hover:border-custom-blue transition-all"
        @click="checkStatus"
      >
        Saya Sudah Verifikasi
      </button>
    </div>

    <RouterLink
      :to="{ name: 'app.home' }"
      class="text-sm font-medium text-custom-grey dark:text-gray-400 hover:text-custom-blue transition-colors"
    >
      Lanjut belanja dulu →
    </RouterLink>
  </div>
</template>
