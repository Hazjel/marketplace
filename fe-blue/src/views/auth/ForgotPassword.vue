<script setup>
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import { axiosInstance } from '@/plugins/axios'

const form = ref({
  email: ''
})

const loading = ref(false)
const error = ref(null)
const successMessage = ref(null)

const handleSubmit = async () => {
  loading.value = true
  error.value = null
  successMessage.value = null

  try {
    const response = await axiosInstance.post('/password/forgot', {
      email: form.value.email
    })

    successMessage.value = response.data.message
    form.value.email = ''
  } catch (err) {
    const data = err.response?.data

    if (err.response?.status === 422 && data?.message) {
      error.value = { email: [data.message] }
    } else if (err.response?.status === 429) {
      error.value = { email: ['Terlalu banyak percobaan. Silakan tunggu sebentar.'] }
    } else {
      error.value = { email: ['Terjadi kesalahan. Silakan coba lagi.'] }
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="flex flex-col w-full gap-6 lg:gap-8">
    <!-- Header Section -->
    <div class="flex flex-col gap-2 text-center">
      <img
        src="@/assets/images/logos/blukios_logo.png"
        class="h-8 lg:h-10 mx-auto mb-4 dark:brightness-0 dark:invert"
        alt="Blukios"
      />
      <h1 class="font-bold text-2xl lg:text-3xl text-custom-black dark:text-white">
        Lupa Password? 🔑
      </h1>
      <p class="text-custom-grey dark:text-gray-400 font-medium text-sm lg:text-base">
        Masukkan email akunmu dan kami akan mengirimkan link untuk reset password.
      </p>
    </div>

    <!-- Success State -->
    <Transition
      enter-active-class="transition-all duration-500 ease-out"
      enter-from-class="opacity-0 -translate-y-2"
      enter-to-class="opacity-100 translate-y-0"
    >
      <div
        v-if="successMessage"
        class="flex items-start gap-3 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-2xl"
      >
        <div
          class="shrink-0 w-8 h-8 rounded-full bg-green-100 dark:bg-green-900/40 flex items-center justify-center mt-0.5"
        >
          <svg class="w-4 h-4 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M5 13l4 4L19 7"
            />
          </svg>
        </div>
        <div class="flex flex-col gap-1">
          <p class="font-semibold text-green-800 dark:text-green-300 text-sm">Email Terkirim!</p>
          <p class="text-green-700 dark:text-green-400 text-sm leading-relaxed">
            {{ successMessage }}
          </p>
          <p class="text-green-600 dark:text-green-500 text-xs mt-1">
            Tidak menerima email?
            <button
              type="button"
              class="font-semibold underline hover:text-green-800 dark:hover:text-green-300 transition-colors"
              :disabled="loading"
              @click="handleSubmit"
            >
              Kirim ulang
            </button>
          </p>
        </div>
      </div>
    </Transition>

    <!-- Form -->
    <form
      v-if="!successMessage"
      class="flex flex-col gap-5"
      autocomplete="off"
      @submit.prevent="handleSubmit"
    >
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
            placeholder="Masukkan email akunmu"
            autocomplete="email"
            :class="{ '!border-red-500 !bg-red-50 dark:!bg-red-900/20': error?.email }"
            :disabled="loading"
          />
        </div>
        <span v-if="error?.email" class="text-red-500 dark:text-red-400 text-xs font-medium ml-2">
          {{ error.email.join(', ') }}
        </span>
      </div>

      <!-- Submit Button -->
      <button
        type="submit"
        class="w-full h-12 flex items-center justify-center rounded-full bg-custom-blue text-white font-bold text-base hover:bg-blue-700 hover:shadow-lg hover:shadow-custom-blue/20 active:scale-[0.98] transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed mt-2"
        :disabled="loading || !form.email"
      >
        <span v-if="loading" class="animate-spin mr-2">⏳</span>
        {{ loading ? 'Mengirim...' : 'Kirim Link Reset Password' }}
      </button>
    </form>

    <!-- Resend Button (shown after success) -->
    <div v-if="successMessage" class="flex flex-col gap-3 mt-2">
      <RouterLink
        :to="{ name: 'auth.login' }"
        class="w-full h-12 flex items-center justify-center rounded-full bg-custom-blue text-white font-bold text-base hover:bg-blue-700 hover:shadow-lg hover:shadow-custom-blue/20 active:scale-[0.98] transition-all duration-300"
      >
        Kembali ke Login
      </RouterLink>
    </div>

    <!-- Back to Login -->
    <p v-if="!successMessage" class="text-center text-custom-grey dark:text-gray-400 font-medium">
      Ingat password?
      <RouterLink
        :to="{ name: 'auth.login' }"
        class="text-custom-blue font-bold hover:underline ml-1"
      >
        Masuk Sekarang
      </RouterLink>
    </p>
  </div>
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
