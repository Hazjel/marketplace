<script setup>
import router from '@/router'
import { useAuthStore } from '@/stores/auth'
import { storeToRefs } from 'pinia'
import { ref, computed, onMounted } from 'vue'
import PlaceHolder from '@/assets/images/icons/gallery-grey.svg'

const authStore = useAuthStore()
const { error } = storeToRefs(authStore)
const { register } = authStore

const form = ref({
  profile_picture: null,
  profile_picture_url: PlaceHolder,
  name: null,
  email: null,
  phone_number: null,
  password: null,
  role: 'buyer' // Default to buyer
})

const showPassword = ref(false)
const loading = ref(false) // Added loading state

// Cerminan aturan validasi backend (RegisterStoreRequest): min 8 karakter,
// wajib ada huruf besar & angka. Ditampilkan real-time biar user gak perlu
// coba-coba submit dulu buat tau syarat mana yang belum terpenuhi.
const passwordRules = computed(() => {
  const value = form.value.password || ''
  return [
    { label: 'Minimal 8 karakter', met: value.length >= 8 },
    { label: 'Mengandung huruf besar (A-Z)', met: /[A-Z]/.test(value) },
    { label: 'Mengandung angka (0-9)', met: /[0-9]/.test(value) }
  ]
})

const handleImageChange = (e) => {
  const file = e.target.files[0]
  if (file) {
    form.value.profile_picture = file
    form.value.profile_picture_url = URL.createObjectURL(file)
  }
}

onMounted(() => {
  authStore.error = null // Reset error state
})

const handleSubmit = async () => {
  loading.value = true
  const formData = new FormData()

  if (form.value.profile_picture) {
    formData.append('profile_picture', form.value.profile_picture)
  }
  formData.append('name', form.value.name)
  formData.append('email', form.value.email)
  if (form.value.phone_number) {
    formData.append('phone_number', form.value.phone_number)
  }
  formData.append('password', form.value.password)
  formData.append('role', 'buyer')

  try {
    const data = await register(formData)
    // Arahkan ke halaman verifikasi kalau email belum terverifikasi
    if (data && !data.email_verified_at) {
      router.push({ name: 'auth.verify-email' })
    } else if (import.meta.env.VITE_APP_TARGET === 'seller') {
      // Marketplace tidak ada di build seller -- akun baru selalu
      // role buyer, SSO balik ke buyer app.
      await authStore.initiateSso(import.meta.env.VITE_BUYER_APP_URL)
    } else {
      router.push({ name: 'app.home' })
    }
  } catch (e) {
    console.error('Registration failed:', e)
  } finally {
    loading.value = false
  }
}

const apiUrl = (import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000/api').replace(/\/$/, '')
</script>

<template>
  <form autocomplete="off" class="flex flex-col w-full gap-6 lg:gap-8" @submit.prevent="handleSubmit">
    <!-- Header -->
    <div class="flex flex-col gap-2 text-center">
      <img src="@/assets/images/logos/blukios_logo.png" class="h-8 lg:h-10 mx-auto mb-4 dark:brightness-0 dark:invert" alt="Blukios" />
      <h1 class="font-bold text-2xl lg:text-3xl text-custom-black dark:text-white">Buat Akun Baru 🚀</h1>
      <p class="text-custom-grey dark:text-gray-400 font-medium text-sm lg:text-base">Bergabung dan mulai perjalananmu!</p>
    </div>

    <!-- Inputs -->
    <div class="flex flex-col gap-5">

      <!-- Profile Picture Upload -->
      <div class="flex flex-col items-center gap-3">
        <label class="relative group cursor-pointer">
          <div
            class="size-20 lg:size-24 rounded-full overflow-hidden border-2 border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-white/5 group-hover:border-custom-blue transition-colors relative">
            <img :src="form.profile_picture_url" class="size-full object-cover" alt="Profile Preview" />
            <!-- Overlay for hover -->
            <div
              class="absolute inset-0 bg-black/30 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
              <img src="@/assets/images/icons/send-square-grey.svg" class="size-6 brightness-0 invert" alt="upload" />
            </div>
          </div>
          <input type="file" accept="image/*" class="hidden" @change="handleImageChange" />
          <div class="absolute bottom-0 right-0 bg-white dark:bg-gray-800 rounded-full p-1.5 shadow-md border border-gray-100 dark:border-white/10">
            <svg class="size-4 text-custom-blue" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
          </div>
        </label>
        <p class="text-xs text-custom-grey dark:text-gray-500 font-medium">Unggah Foto Profil <span class="text-gray-400">(Opsional)</span></p>
      </div>

      <!-- Name Field -->
      <div class="flex flex-col gap-2">
        <label class="font-semibold text-custom-black dark:text-white text-sm ml-1">Nama Lengkap</label>
        <div class="group relative transition-all duration-300">
          <div class="absolute inset-y-0 left-4 flex items-center pointer-events-none">
            <img src="@/assets/images/icons/profile-circle-grey.svg" class="size-5 custom-icon" alt="icon" />
          </div>
          <input
            v-model="form.name" type="text"
            class="w-full h-12 pl-12 pr-4 bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-white/10 rounded-full focus:bg-white dark:focus:bg-white/10 focus:border-custom-blue focus:ring-2 focus:ring-custom-blue/20 outline-none transition-all font-medium text-custom-black dark:text-white placeholder-gray-400 dark:placeholder-gray-500"
            placeholder="Contoh: Budi Santoso" autocomplete="name"
            :class="{ '!border-red-500 !bg-red-50 dark:!bg-red-900/20': error?.name }" />
        </div>
        <span v-if="error?.name" class="text-red-500 text-xs font-medium ml-2">{{ error?.name?.join(', ') }}</span>
      </div>

      <!-- Email Field -->
      <div class="flex flex-col gap-2">
        <label class="font-semibold text-custom-black dark:text-white text-sm ml-1">Alamat Email</label>
        <div class="group relative transition-all duration-300">
          <div class="absolute inset-y-0 left-4 flex items-center pointer-events-none">
            <img src="@/assets/images/icons/sms-grey.svg" class="size-5 custom-icon" alt="icon" />
          </div>
          <input
            v-model="form.email" type="email"
            class="w-full h-12 pl-12 pr-4 bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-white/10 rounded-full focus:bg-white dark:focus:bg-white/10 focus:border-custom-blue focus:ring-2 focus:ring-custom-blue/20 outline-none transition-all font-medium text-custom-black dark:text-white placeholder-gray-400 dark:placeholder-gray-500"
            placeholder="Contoh: budi@email.com" autocomplete="email"
            :class="{ '!border-red-500 !bg-red-50 dark:!bg-red-900/20': error?.email }" />
        </div>
        <span v-if="error?.email" class="text-red-500 text-xs font-medium ml-2">{{ error?.email?.join(', ') }}</span>
      </div>

      <!-- Phone Field -->
      <div class="flex flex-col gap-2">
        <label class="font-semibold text-custom-black dark:text-white text-sm ml-1">
          Nomor Telepon <span class="font-normal text-custom-grey dark:text-gray-400">(opsional)</span>
        </label>
        <div class="group relative transition-all duration-300">
          <div class="absolute inset-y-0 left-4 flex items-center pointer-events-none">
            <img src="@/assets/images/icons/call-grey.svg" class="size-5 custom-icon" alt="icon" />
          </div>
          <input
            v-model="form.phone_number" type="tel"
            class="w-full h-12 pl-12 pr-4 bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-white/10 rounded-full focus:bg-white dark:focus:bg-white/10 focus:border-custom-blue focus:ring-2 focus:ring-custom-blue/20 outline-none transition-all font-medium text-custom-black dark:text-white placeholder-gray-400 dark:placeholder-gray-500"
            placeholder="Contoh: 0812..." autocomplete="tel"
            :class="{ '!border-red-500 !bg-red-50 dark:!bg-red-900/20': error?.phone_number }"
            @input="form.phone_number = form.phone_number.replace(/[^0-9]/g, '').slice(0, 15)" />
        </div>
        <span v-if="error?.phone_number" class="text-red-500 text-xs font-medium ml-2">{{ error?.phone_number?.join(', ') }}</span>
      </div>

      <!-- Password Field -->
      <div class="flex flex-col gap-2">
        <label class="font-semibold text-custom-black dark:text-white text-sm ml-1">Password</label>
        <div class="group relative transition-all duration-300">
          <div class="absolute inset-y-0 left-4 flex items-center pointer-events-none">
            <img src="@/assets/images/icons/key-grey.svg" class="size-5 custom-icon" alt="icon" />
          </div>
          <input
            v-model="form.password" :type="showPassword ? 'text' : 'password'"
            class="w-full h-12 pl-12 pr-12 bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-white/10 rounded-full focus:bg-white dark:focus:bg-white/10 focus:border-custom-blue focus:ring-2 focus:ring-custom-blue/20 outline-none transition-all font-medium text-custom-black dark:text-white placeholder-gray-400 dark:placeholder-gray-500"
            placeholder="Minimal 8 karakter" autocomplete="new-password"
            :class="{ '!border-red-500 !bg-red-50 dark:!bg-red-900/20': error?.password }" />
          <button
            type="button"
            class="absolute inset-y-0 right-4 flex items-center p-1 hover:bg-gray-200 dark:hover:bg-white/10 rounded-full transition-colors"
            @click="showPassword = !showPassword">
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
        <span v-if="error?.password" class="text-red-500 text-xs font-medium ml-2">{{ error?.password?.join(', ') }}</span>
        <ul class="flex flex-col gap-1 ml-2 mt-1">
          <li
            v-for="rule in passwordRules" :key="rule.label"
            class="flex items-center gap-2 text-xs font-medium transition-colors"
            :class="rule.met ? 'text-green-600 dark:text-green-400' : 'text-red-500 dark:text-red-400'">
            <span
              class="size-1.5 rounded-full shrink-0 transition-colors"
              :class="rule.met ? 'bg-green-500' : 'bg-red-500'"></span>
            {{ rule.label }}
          </li>
        </ul>
      </div>
    </div>

    <!-- Actions -->
    <div class="flex flex-col gap-4 mt-2">
      <button
        type="submit"
        class="w-full h-12 flex items-center justify-center rounded-full bg-custom-blue text-white font-bold text-base hover:bg-blue-700 hover:shadow-lg hover:shadow-custom-blue/20 active:scale-[0.98] transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed"
        :disabled="loading">
        <span v-if="loading" class="animate-spin mr-2">⏳</span>
        {{ loading ? 'Memproses...' : 'Daftar Sekarang' }}
      </button>

      <!-- Divider -->
      <div class="flex items-center gap-4">
        <div class="h-px flex-1 bg-gray-200 dark:bg-white/10"></div>
        <span class="text-sm text-custom-grey dark:text-gray-400 font-medium">atau</span>
        <div class="h-px flex-1 bg-gray-200 dark:bg-white/10"></div>
      </div>

      <!-- Google Button -->
      <a
        :href="`${apiUrl}/auth/google/redirect`"
        class="w-full h-12 flex items-center justify-center rounded-full border border-gray-200 dark:border-white/10 bg-white dark:bg-white/5 text-custom-black dark:text-white font-semibold hover:bg-gray-50 dark:hover:bg-white/10 hover:border-gray-300 hover:shadow-sm transition-all duration-300">
        <img src="@/assets/images/icons/google.svg" class="size-5 mr-3" alt="Google" />
        Daftar dengan Google
      </a>

      <p class="text-center text-custom-grey dark:text-gray-400 font-medium">
        Sudah punya akun?
        <RouterLink :to="{ name: 'auth.login' }" class="text-custom-blue font-bold hover:underline ml-1">
          Masuk
        </RouterLink>
      </p>
    </div>
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
