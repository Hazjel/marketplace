<script setup>
import router from '@/router'
import { useAuthStore } from '@/stores/auth'
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
    // Login gagal, error sudah di-set oleh store
    if (error.value === 'Unauthorized') {
      error.value = {
        email: ['Email atau password salah']
      }
    }
    return // Stop eksekusi
  }

  if (rememberMe.value) {
    localStorage.setItem('remembered_email', form.value.email)
    localStorage.setItem('remembered_password', form.value.password)
  } else {
    localStorage.removeItem('remembered_email')
    localStorage.removeItem('remembered_password')
  }

  // Login berhasil, redirect berdasarkan role
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
  <form autocomplete="off"
    class="flex flex-col w-full max-w-[560px] h-full max-h-[772px] shrink-0 justify-center rounded-3xl gap-10 p-6 bg-white"
    @submit.prevent="handleSubmit">
    <img src="@/assets/images/logos/blukios_logo.png" class="h-12 mx-auto" alt="logo" />
    <div class="flex flex-col gap-[30px]">
      <div class="flex flex-col gap-3 text-center">
        <p class="font-bold text-2xl capitalize">Hey🙌🏻, Welcome Back!</p>
        <p class="font-medium text-custom-grey">Login to your account to continue!</p>
      </div>
      <div class="flex flex-col gap-4 w-full">
        <div class="flex flex-col gap-3">
          <p class="font-semibold text-custom-grey">Email Address</p>
          <div class="group/errorState flex flex-col gap-2" :class="{ invalid: error?.email }">
            <label class="group relative">
              <div class="input-icon">
                <img src="@/assets/images/icons/sms-grey.svg" class="flex size-6 shrink-0 dark:brightness-0 dark:invert"
                  alt="icon" />
              </div>
              <p class="input-placeholder">Enter Your Email</p>
              <input v-model="form.email" type="email" class="custom-input" placeholder="" autocomplete="off" />
            </label>
            <span v-if="error?.email" class="input-error">{{ error?.email?.join(', ') }}</span>
          </div>
        </div>
        <div class="flex flex-col gap-3">
          <p class="font-semibold text-custom-grey">Password</p>
          <div class="group/errorState flex flex-col gap-2" :class="{ invalid: error?.password }">
            <label class="group relative">
              <div class="input-icon">
                <img src="@/assets/images/icons/key-grey.svg" class="flex size-6 shrink-0 dark:brightness-0 dark:invert"
                  alt="icon" />
              </div>
              <p class="input-placeholder">Enter Your Password</p>
              <input id="passwordInput" v-model="form.password" :type="showPassword ? 'text' : 'password'"
                class="custom-input tracking-[0.3em] pr-12" placeholder="" autocomplete="new-password" />
              <button type="button"
                class="absolute right-4 top-1/2 -translate-y-1/2 p-2 hover:bg-gray-100 rounded-full transition"
                @click="showPassword = !showPassword">
                <img :src="showPassword
                    ? '/src/assets/images/icons/eye-blue.svg'
                    : '/src/assets/images/icons/eye-grey.svg'
                  " class="size-6" alt="toggle password" />
              </button>
            </label>
            <span v-if="error?.password" class="input-error">{{
              error?.password?.join(', ')
              }}</span>
          </div>
          <div class="flex items-center justify-between">
            <label class="group flex items-center gap-1 relative">
              <input v-model="rememberMe" type="checkbox" name="remember" autocomplete="off" class="-z-10 absolute" />
              <div class="flex size-6 overflow-hidden relative">
                <img src="@/assets/images/icons/checkbox-unchecked.svg"
                  class="size-full object-contain absolute group-has-checked:opacity-0 transition-300" alt="icon" />
                <img src="@/assets/images/icons/checkbox.svg"
                  class="size-full object-contain absolute opacity-0 group-has-checked:opacity-100 transition-300"
                  alt="icon" />
              </div>
              <span
                class="font-semibold text-custom-grey leading-none group-has-checked:text-custom-blue transition-300">Remember
                me</span>
            </label>
            <a href="#"
              class="font-semibold text-custom-grey hover:text-custom-blue hover:underline transition-300">Reset
              Password</a>
          </div>
        </div>
      </div>
    </div>
    <div class="flex flex-col gap-3">
      <div class="flex flex-col gap-3">
        <a :href="`${apiUrl}/auth/google/redirect`"
          class="flex items-center justify-center h-14 rounded-full py-4 px-6 gap-[10px] border border-custom-stroke bg-white hover:bg-gray-50 transition-300">
          <img src="@/assets/images/icons/google.svg" class="size-6" alt="google" onerror="
              this.style.display = 'none'
              this.nextElementSibling.style.display = 'block'
            " />
          <!-- Fallback if icon missing -->
          <span style="display: none" class="font-bold text-lg">G</span>
          <span class="font-semibold text-custom-black">Sign in with Google</span>
        </a>
        <button type="submit"
          class="flex items-center justify-center h-14 rounded-full py-4 px-6 gap-[10px] bg-custom-blue font-semibold capitalize text-white">
          Sign In
        </button>
      </div>
      <p class="font-medium text-custom-grey text-center">
        Don't have account?
        <RouterLink :to="{ name: 'auth.register' }"
          class="font-semibold text-custom-blue hover:underline transition-300">
          Create Account
        </RouterLink>
      </p>
    </div>
  </form>
</template>
