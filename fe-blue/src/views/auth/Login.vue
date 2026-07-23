<script setup>
import router from '@/router'
import { useAuthStore } from '@/stores/auth'
import { useCartStore } from '@/stores/cart'
import { storeToRefs } from 'pinia'
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'

const authStore = useAuthStore()
const { loading, error } = storeToRefs(authStore)
const { login } = authStore

const rememberMe = ref(false)

const form = ref({
  email: null,
  password: null
})

const showPassword = ref(false)

const handleSubmit = async () => {
  const response = await login(form.value)

  // Cek apakah login berhasil (response ada dan memiliki data)
  if (!response) {
    if (error.value === 'Unauthorized') {
      error.value = {
        email: ['Email atau password salah']
      }
    }
    return
  }

  if (rememberMe.value) {
    localStorage.setItem('remembered_email', form.value.email)
  } else {
    localStorage.removeItem('remembered_email')
  }
  localStorage.removeItem('remembered_password') // hapus password lama jika ada

  // Sync cart from localStorage → server after login
  const cart = useCartStore()
  await cart.syncAfterLogin()

  const isSellerBuild = import.meta.env.VITE_APP_TARGET === 'seller'
  const isSellerRole = response.role === 'admin' || response.role === 'store'

  if (isSellerRole && !isSellerBuild) {
    // Dashboard toko/admin tidak ada di build buyer -- SSO ke seller app.
    await authStore.initiateSso(import.meta.env.VITE_SELLER_APP_URL)
  } else if (!isSellerRole && isSellerBuild) {
    // Marketplace tidak ada di build seller -- SSO balik ke buyer app.
    await authStore.initiateSso(import.meta.env.VITE_BUYER_APP_URL)
  } else if (response.role === 'admin') {
    router.push({ name: 'admin.dashboard' })
  } else {
    router.push({
      name: 'user.dashboard',
      params: { username: response.username }
    })
  }
}

const route = useRoute()
const justVerified = ref(false)

onMounted(() => {
  authStore.error = null // Reset error state
  justVerified.value = route.query.verified === '1'

  localStorage.removeItem('remembered_password') // bersihkan password lama (security fix)
  const savedEmail = localStorage.getItem('remembered_email')
  if (savedEmail) {
    form.value.email = savedEmail
    rememberMe.value = true
  }
})

const apiUrl = (import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000/api').replace(/\/$/, '')
</script>

<template>
  <form
    autocomplete="off"
    class="flex flex-col w-full gap-6 lg:gap-8"
    @submit.prevent="handleSubmit"
  >
    <!-- Header Section -->
    <div class="flex flex-col gap-2 text-center">
      <img
        src="@/assets/images/logos/blukios_logo.png"
        class="h-8 lg:h-10 mx-auto mb-4 dark:brightness-0 dark:invert"
        alt="Blukios"
      />
      <h1 class="font-bold text-2xl lg:text-3xl text-custom-black dark:text-white">
        Selamat Datang! 👋
      </h1>
      <p class="text-custom-grey dark:text-gray-400 font-medium text-sm lg:text-base">
        Masukkan detail akunmu untuk melanjutkan.
      </p>
    </div>

    <!-- Email verified banner -->
    <div
      v-if="justVerified"
      class="p-4 rounded-2xl bg-green-50 dark:bg-green-900/20 text-green-600 dark:text-green-400 font-medium text-sm border border-green-200 dark:border-green-800 text-center"
    >
      ✅ Email berhasil diverifikasi! Silakan masuk.
    </div>

    <!-- Inputs Section -->
    <div class="flex flex-col gap-5">
      <!-- Email Field -->
      <div class="flex flex-col gap-2">
        <label class="font-semibold text-custom-black dark:text-white text-sm ml-1">Alamat Email</label>
        <div class="group relative transition-all duration-300">
          <div class="absolute inset-y-0 left-4 flex items-center pointer-events-none">
            <img
              src="@/assets/images/icons/sms-grey.svg"
              class="size-5 transition-opacity duration-300 custom-icon"
              alt="email icon"
            />
          </div>
          <input
            v-model="form.email"
            type="email"
            class="w-full h-12 pl-12 pr-4 bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-white/10 rounded-full focus:bg-white dark:focus:bg-white/10 focus:border-custom-blue focus:ring-2 focus:ring-custom-blue/20 outline-none transition-all font-medium text-custom-black dark:text-white placeholder-gray-400 dark:placeholder-gray-500"
            placeholder="Masukkan email kamu"
            autocomplete="email"
            :class="{ '!border-red-500 !bg-red-50 dark:!bg-red-900/20': error?.email }"
          />
        </div>
        <span v-if="error?.email" class="text-red-500 text-xs font-medium ml-2">{{
          error?.email?.join(', ')
        }}</span>
      </div>

      <!-- Password Field -->
      <div class="flex flex-col gap-2">
        <label class="font-semibold text-custom-black dark:text-white text-sm ml-1">Password</label>
        <div class="group relative transition-all duration-300">
          <div class="absolute inset-y-0 left-4 flex items-center pointer-events-none">
            <img
              src="@/assets/images/icons/key-grey.svg"
              class="size-5 custom-icon"
              alt="password icon"
            />
          </div>
          <input
            id="passwordInput"
            v-model="form.password"
            :type="showPassword ? 'text' : 'password'"
            class="w-full h-12 pl-12 pr-12 bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-white/10 rounded-full focus:bg-white dark:focus:bg-white/10 focus:border-custom-blue focus:ring-2 focus:ring-custom-blue/20 outline-none transition-all font-medium text-custom-black dark:text-white placeholder-gray-400 dark:placeholder-gray-500"
            placeholder="••••••••"
            autocomplete="current-password"
            :class="{ '!border-red-500 !bg-red-50 dark:!bg-red-900/20': error?.password }"
          />
          <button
            type="button"
            class="absolute inset-y-0 right-4 flex items-center p-1 hover:bg-gray-200 dark:hover:bg-white/10 rounded-full transition-colors"
            @click="showPassword = !showPassword"
          >
            <svg
              class="size-5 transition-colors"
              :class="showPassword ? 'text-custom-blue' : 'text-gray-400'"
              fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"
            >
              <template v-if="showPassword">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
              </template>
              <template v-else>
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              </template>
            </svg>
          </button>
        </div>
        <span v-if="error?.password" class="text-red-500 text-xs font-medium ml-2">{{
          error?.password?.join(', ')
        }}</span>
      </div>

      <!-- Options: Remember Me & Forgot Password -->
      <div class="flex items-center justify-between px-1">
        <label class="flex items-center gap-2 cursor-pointer group">
          <div class="relative size-5">
            <input
              v-model="rememberMe"
              type="checkbox"
              class="peer appearance-none size-5 border-2 border-gray-300 dark:border-white/20 rounded-md checked:bg-custom-blue checked:border-custom-blue transition-colors"
            />
            <svg
              class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 size-3.5 text-white opacity-0 peer-checked:opacity-100 pointer-events-none transition-opacity"
              viewBox="0 0 14 14"
              fill="none"
              xmlns="http://www.w3.org/2000/svg"
            >
              <path
                d="M11.6666 3.5L5.24992 9.91667L2.33325 7"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
            </svg>
          </div>
          <span
            class="text-sm font-medium text-custom-grey dark:text-gray-400 group-hover:text-custom-blue transition-colors"
            >Ingat saya</span
          >
        </label>
        <RouterLink
          :to="{ name: 'auth.forgot-password' }"
          class="text-sm font-semibold text-custom-blue hover:text-blue-700 hover:underline transition-colors"
        >
          Lupa Password?
        </RouterLink>
      </div>
    </div>

    <!-- Actions -->
    <div class="flex flex-col gap-4 mt-2">
      <!-- Primary Button -->
      <button
        type="submit"
        class="w-full h-12 flex items-center justify-center rounded-full bg-custom-blue text-white font-bold text-base hover:bg-blue-700 hover:shadow-lg hover:shadow-custom-blue/20 active:scale-[0.98] transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed"
        :disabled="loading"
      >
        <span v-if="loading" class="animate-spin mr-2">⏳</span>
        {{ loading ? 'Memproses...' : 'Masuk' }}
      </button>

      <!-- Google Button -->
      <a
        :href="`${apiUrl}/auth/google/redirect`"
        class="w-full h-12 flex items-center justify-center rounded-full border border-gray-200 dark:border-white/10 bg-white dark:bg-white/5 text-custom-black dark:text-white font-semibold hover:bg-gray-50 dark:hover:bg-white/10 hover:border-gray-300 hover:shadow-sm transition-all duration-300"
      >
        <img src="@/assets/images/icons/google.svg" class="size-5 mr-3" alt="Google" />
        Masuk dengan Google
      </a>
    </div>

    <!-- Footer -->
    <p class="text-center text-custom-grey dark:text-gray-400 font-medium mt-2">
      Belum punya akun?
      <RouterLink
        :to="{ name: 'auth.register' }"
        class="text-custom-blue font-bold hover:underline ml-1"
      >
        Daftar Sekarang
      </RouterLink>
    </p>
  </form>
</template>

<style scoped>
.custom-icon {
  filter: grayscale(100%);
  opacity: 0.6;
}

.group:focus-within .custom-icon {
  filter: none;
  opacity: 1;
}
</style>
