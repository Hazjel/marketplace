<script setup>
import router from '@/router'
import { useAuthStore } from '@/stores/auth'
import { useCartStore } from '@/stores/cart'
import { storeToRefs } from 'pinia'
import { onMounted, ref } from 'vue'

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
    localStorage.setItem('remembered_password', form.value.password)
  } else {
    localStorage.removeItem('remembered_email')
    localStorage.removeItem('remembered_password')
  }

  // Sync cart from localStorage → server after login
  const cart = useCartStore()
  await cart.syncAfterLogin()

  // Redirect based on role
  if (response.role === 'admin') {
    router.push({ name: 'admin.dashboard' })
  } else {
    router.push({
      name: 'user.dashboard',
      params: { username: response.username }
    })
  }
}

onMounted(() => {
  authStore.error = null // Reset error state

  const savedEmail = localStorage.getItem('remembered_email')
  const savedPassword = localStorage.getItem('remembered_password')

  if (savedEmail && savedPassword) {
    form.value.email = savedEmail
    form.value.password = savedPassword
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
        class="h-8 lg:h-10 mx-auto mb-4"
        alt="Blukios"
      />
      <h1 class="font-bold text-2xl lg:text-3xl text-custom-black">Welcome Back! 👋</h1>
      <p class="text-custom-grey font-medium text-sm lg:text-base">
        Please enter your details to sign in.
      </p>
    </div>

    <!-- Inputs Section -->
    <div class="flex flex-col gap-5">
      <!-- Email Field -->
      <div class="flex flex-col gap-2">
        <label class="font-semibold text-custom-black dark:text-white text-sm ml-1"
          >Email Address</label
        >
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
            placeholder="Enter your email"
            autocomplete="email"
            :class="{ 'border-red-500! bg-red-50!': error?.email }"
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
            :class="{ 'border-red-500! bg-red-50!': error?.password }"
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
              class="peer appearance-none size-5 border-2 border-gray-300 rounded-md checked:bg-custom-blue checked:border-custom-blue transition-colors"
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
            >Remember me</span
          >
        </label>
        <RouterLink
          :to="{ name: 'auth.forgot-password' }"
          class="text-sm font-semibold text-custom-blue hover:text-blue-700 hover:underline transition-colors"
        >
          Reset Password?
        </RouterLink>
      </div>
    </div>

    <!-- Actions -->
    <div class="flex flex-col gap-4 mt-2">
      <!-- Primary Button -->
      <button
        type="submit"
        class="w-full h-12 flex items-center justify-center rounded-full bg-custom-blue text-white font-bold text-lg hover:bg-blue-700 hover:shadow-lg active:scale-95 transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed dark:shadow-custom-blue/10"
        :disabled="loading"
      >
        <span v-if="loading" class="animate-spin mr-2">⏳</span>
        {{ loading ? 'Signing In...' : 'Sign In' }}
      </button>

      <!-- Google Button -->
      <a
        :href="`${apiUrl}/auth/google/redirect`"
        class="w-full h-12 flex items-center justify-center rounded-full border border-gray-300 dark:border-white/10 bg-white dark:bg-white/5 text-custom-black dark:text-white font-semibold hover:bg-gray-50 dark:hover:bg-white/10 hover:border-gray-400 hover:shadow-sm transition-all duration-300"
      >
        <img src="@/assets/images/icons/google.svg" class="size-5 mr-3" alt="Google" />
        Sign in with Google
      </a>
    </div>

    <!-- Footer -->
    <p class="text-center text-custom-grey dark:text-gray-400 font-medium mt-4">
      Don't have an account?
      <RouterLink
        :to="{ name: 'auth.register' }"
        class="text-custom-blue font-bold hover:underline ml-1"
      >
        Create Account
      </RouterLink>
    </p>
  </form>
</template>

<style scoped>
/* Optional: Add custom refined sizing if Tailwind isn't enough */
.custom-icon {
  filter: grayscale(100%);
  opacity: 0.6;
}

.group:focus-within .custom-icon {
  filter: none;
  opacity: 1;
}
</style>
