<script setup>
import router from '@/router'
import { useAuthStore } from '@/stores/auth'
import { storeToRefs } from 'pinia'
import { ref, onMounted } from 'vue'
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
  formData.append('phone_number', form.value.phone_number)
  formData.append('password', form.value.password)
  formData.append('role', 'buyer')

  try {
    await register(formData)
    // Only redirect on success
    router.push({ name: 'app.home' })
  } catch (e) {
    console.error('Registration failed:', e)
  } finally {
    loading.value = false
  }
}
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
        <p class="text-xs text-custom-grey dark:text-gray-500 font-medium">Unggah Foto Profil</p>
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
        <label class="font-semibold text-custom-black dark:text-white text-sm ml-1">Nomor Telepon</label>
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
            <img
              :src="showPassword ? '/src/assets/images/icons/eye-blue.svg' : '/src/assets/images/icons/eye-grey.svg'"
              class="size-5" alt="toggle visibility" />
          </button>
        </div>
        <span v-if="error?.password" class="text-red-500 text-xs font-medium ml-2">{{ error?.password?.join(', ') }}</span>
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
