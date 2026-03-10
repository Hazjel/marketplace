<script setup>
import { ref, onMounted } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { axiosInstance } from '@/plugins/axios'

const route = useRoute()
const router = useRouter()

const form = ref({
  token: '',
  email: '',
  password: '',
  password_confirmation: ''
})

const loading = ref(false)
const error = ref(null)
const successMessage = ref(null)
const showPassword = ref(false)
const showConfirmPassword = ref(false)
const tokenInvalid = ref(false)

onMounted(() => {
  const token = Array.isArray(route.query.token) ? route.query.token[0] : route.query.token

  const email = Array.isArray(route.query.email) ? route.query.email[0] : route.query.email

  if (!token || !email) {
    tokenInvalid.value = true
    return
  }

  form.value.token = String(token)
  form.value.email = String(email)
})

const handleSubmit = async () => {
  loading.value = true
  error.value = null
  successMessage.value = null

  try {
    const response = await axiosInstance.post('/password/reset', {
      token: form.value.token,
      email: form.value.email,
      password: form.value.password,
      password_confirmation: form.value.password_confirmation
    })

    successMessage.value = response.data.message

    // Redirect ke login setelah 2.5 detik
    setTimeout(() => {
      router.push({ name: 'auth.login' })
    }, 2500)
  } catch (err) {
    const data = err.response?.data

    if (err.response?.status === 422) {
      if (data?.message) {
        // Cek apakah pesan berkaitan dengan token
        if (
          data.message.toLowerCase().includes('token') ||
          data.message.toLowerCase().includes('invalid') ||
          data.message.toLowerCase().includes('kadaluarsa')
        ) {
          error.value = { token: [data.message] }
        } else {
          error.value = { password: [data.message] }
        }
      }
    } else if (err.response?.status === 404) {
      error.value = { email: [data?.message || 'Email tidak ditemukan.'] }
    } else {
      error.value = { password: ['Terjadi kesalahan. Silakan coba lagi.'] }
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
        alt="Blue Marketplace"
      />
      <h1 class="font-bold text-2xl lg:text-3xl text-custom-black dark:text-white">
        Buat Password Baru 🔐
      </h1>
      <p class="text-custom-grey dark:text-gray-400 font-medium text-sm lg:text-base">
        Masukkan password baru untuk akun
        <span v-if="form.email" class="text-custom-blue dark:text-blue-400 font-semibold">{{
          form.email
        }}</span>
      </p>
    </div>

    <!-- Token Invalid State -->
    <div v-if="tokenInvalid" class="flex flex-col items-center gap-5 py-4">
      <div
        class="w-16 h-16 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center"
      >
        <svg class="w-8 h-8 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"
          />
        </svg>
      </div>
      <div class="text-center flex flex-col gap-2">
        <p class="font-bold text-custom-black dark:text-white text-lg">Link Tidak Valid</p>
        <p class="text-custom-grey dark:text-gray-400 text-sm leading-relaxed">
          Link reset password ini tidak valid atau sudah kadaluarsa.<br />
          Silakan minta link baru.
        </p>
      </div>
      <RouterLink
        :to="{ name: 'auth.forgot-password' }"
        class="w-full h-12 flex items-center justify-center rounded-full bg-custom-blue text-white font-bold text-base hover:bg-blue-700 hover:shadow-lg active:scale-95 transition-all duration-300"
      >
        Minta Link Baru
      </RouterLink>
    </div>

    <!-- Success State -->
    <Transition
      enter-active-class="transition-all duration-500 ease-out"
      enter-from-class="opacity-0 -translate-y-2"
      enter-to-class="opacity-100 translate-y-0"
    >
      <div v-if="successMessage" class="flex flex-col items-center gap-5 py-4">
        <div
          class="w-16 h-16 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center"
        >
          <svg class="w-8 h-8 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
            />
          </svg>
        </div>
        <div class="text-center flex flex-col gap-2">
          <p class="font-bold text-custom-black dark:text-white text-lg">
            Password Berhasil Diperbarui!
          </p>
          <p class="text-custom-grey dark:text-gray-400 text-sm leading-relaxed">
            {{ successMessage }}<br />
            Kamu akan dialihkan ke halaman login...
          </p>
        </div>
        <RouterLink
          :to="{ name: 'auth.login' }"
          class="w-full h-12 flex items-center justify-center rounded-full bg-custom-blue text-white font-bold text-base hover:bg-blue-700 hover:shadow-lg active:scale-95 transition-all duration-300"
        >
          Login Sekarang
        </RouterLink>
      </div>
    </Transition>

    <!-- Form -->
    <form
      v-if="!tokenInvalid && !successMessage"
      class="flex flex-col gap-5"
      autocomplete="off"
      @submit.prevent="handleSubmit"
    >
      <!-- Token error -->
      <Transition
        enter-active-class="transition-all duration-300 ease-out"
        enter-from-class="opacity-0 -translate-y-1"
        enter-to-class="opacity-100 translate-y-0"
      >
        <div
          v-if="error?.token"
          class="flex items-start gap-3 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-2xl"
        >
          <svg
            class="shrink-0 w-5 h-5 text-red-500 mt-0.5"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
            />
          </svg>
          <div class="flex flex-col gap-1">
            <p class="text-red-700 dark:text-red-400 text-sm font-medium">
              {{ error.token.join(', ') }}
            </p>
            <RouterLink
              :to="{ name: 'auth.forgot-password' }"
              class="text-red-600 text-xs font-semibold underline hover:text-red-800 transition-colors"
            >
              Minta link reset baru →
            </RouterLink>
          </div>
        </div>
      </Transition>

      <!-- New Password Field -->
      <div class="flex flex-col gap-2">
        <label class="font-semibold text-custom-black dark:text-white text-sm ml-1"
          >Password Baru</label
        >
        <div class="group relative transition-all duration-300">
          <div class="absolute inset-y-0 left-4 flex items-center pointer-events-none">
            <img
              src="@/assets/images/icons/key-grey.svg"
              class="size-5 custom-icon"
              alt="password icon"
            />
          </div>
          <input
            v-model="form.password"
            :type="showPassword ? 'text' : 'password'"
            class="w-full h-12 pl-12 pr-12 bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-white/10 rounded-full focus:bg-white dark:focus:bg-white/10 focus:border-custom-blue focus:ring-2 focus:ring-custom-blue/20 outline-none transition-all font-medium text-custom-black dark:text-white placeholder-gray-400 dark:placeholder-gray-500"
            placeholder="Minimal 8 karakter"
            autocomplete="new-password"
            :class="{ '!border-red-500 !bg-red-50 dark:!bg-red-900/20': error?.password }"
            :disabled="loading"
          />
          <button
            type="button"
            class="absolute inset-y-0 right-4 flex items-center p-1 hover:bg-gray-200 rounded-full transition-colors"
            @click="showPassword = !showPassword"
          >
            <img
              :src="
                showPassword
                  ? '/src/assets/images/icons/eye-blue.svg'
                  : '/src/assets/images/icons/eye-grey.svg'
              "
              class="size-5"
              alt="toggle visibility"
            />
          </button>
        </div>
        <span
          v-if="error?.password"
          class="text-red-500 dark:text-red-400 text-xs font-medium ml-2"
        >
          {{ error.password.join(', ') }}
        </span>
      </div>

      <!-- Confirm Password Field -->
      <div class="flex flex-col gap-2">
        <label class="font-semibold text-custom-black dark:text-white text-sm ml-1"
          >Konfirmasi Password</label
        >
        <div class="group relative transition-all duration-300">
          <div class="absolute inset-y-0 left-4 flex items-center pointer-events-none">
            <img
              src="@/assets/images/icons/key-grey.svg"
              class="size-5 custom-icon"
              alt="password icon"
            />
          </div>
          <input
            v-model="form.password_confirmation"
            :type="showConfirmPassword ? 'text' : 'password'"
            class="w-full h-12 pl-12 pr-12 bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-white/10 rounded-full focus:bg-white dark:focus:bg-white/10 focus:border-custom-blue focus:ring-2 focus:ring-custom-blue/20 outline-none transition-all font-medium text-custom-black dark:text-white placeholder-gray-400 dark:placeholder-gray-500"
            placeholder="Ulangi password baru"
            autocomplete="new-password"
            :class="{
              '!border-red-500 !bg-red-50': error?.password_confirmation,
              '!border-green-400':
                form.password_confirmation && form.password === form.password_confirmation
            }"
            :disabled="loading"
          />
          <button
            type="button"
            class="absolute inset-y-0 right-4 flex items-center p-1 hover:bg-gray-200 rounded-full transition-colors"
            @click="showConfirmPassword = !showConfirmPassword"
          >
            <img
              :src="
                showConfirmPassword
                  ? '/src/assets/images/icons/eye-blue.svg'
                  : '/src/assets/images/icons/eye-grey.svg'
              "
              class="size-5"
              alt="toggle visibility"
            />
          </button>
        </div>

        <!-- Password match indicator -->
        <Transition
          enter-active-class="transition-all duration-200"
          enter-from-class="opacity-0"
          enter-to-class="opacity-100"
        >
          <span
            v-if="form.password_confirmation"
            class="text-xs font-medium ml-2"
            :class="
              form.password === form.password_confirmation ? 'text-green-500' : 'text-red-500'
            "
          >
            {{
              form.password === form.password_confirmation
                ? '✓ Password cocok'
                : '✗ Password tidak cocok'
            }}
          </span>
        </Transition>
      </div>

      <!-- Submit Button -->
      <button
        type="submit"
        class="w-full h-12 flex items-center justify-center rounded-full bg-custom-blue text-white font-bold text-base hover:bg-blue-700 hover:shadow-lg active:scale-95 transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed mt-2"
        :disabled="
          loading ||
          !form.password ||
          !form.password_confirmation ||
          form.password !== form.password_confirmation
        "
      >
        <span v-if="loading" class="animate-spin mr-2">⏳</span>
        {{ loading ? 'Memperbarui...' : 'Perbarui Password' }}
      </button>
    </form>

    <!-- Back to Login -->
    <p
      v-if="!tokenInvalid && !successMessage"
      class="text-center text-custom-grey dark:text-gray-400 font-medium"
    >
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
