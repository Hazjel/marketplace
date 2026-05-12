<script setup>
import { ref, onMounted, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { storeToRefs } from 'pinia'

import { axiosInstance as axios } from '@/plugins/axios'

const authStore = useAuthStore()
const { user } = storeToRefs(authStore)
const { checkAuth } = authStore

const isLoading = ref(false)
const errors = ref({})
const successMessage = ref(null)

const fileInput = ref(null)
const previewImage = ref(null)
const selectedFile = ref(null)

const form = ref({
  name: '',
  phone_number: '',
  current_password: '',
  password: '',
  password_confirmation: ''
})

const populateForm = () => {
  if (user.value) {
    form.value.name = user.value.name
    // Populate phone number based on role
    if (user.value.role === 'buyer' && user.value.buyer) {
      form.value.phone_number = String(user.value.buyer?.phone_number || '')
    } else if (user.value.role === 'store' && user.value.store) {
      form.value.phone_number = String(user.value.store?.phone || '')
    }
  }
}

onMounted(async () => {
  // If user is missing, OR if user is buyer/store but missing relation data, forcing a refresh
  if (
    !user.value ||
    (user.value.role === 'buyer' && !user.value.buyer) ||
    (user.value.role === 'store' && !user.value.store)
  ) {
    await checkAuth()
  }
  populateForm()
})

watch(user, () => {
  populateForm()
})

const triggerFileInput = () => {
  fileInput.value.click()
}

const handleFileChange = (event) => {
  const file = event.target.files[0]
  if (file) {
    selectedFile.value = file
    previewImage.value = URL.createObjectURL(file)
  }
}

const handleSubmit = async () => {
  isLoading.value = true
  errors.value = {}
  successMessage.value = null

  try {
    const formData = new FormData()
    formData.append('_method', 'PUT') // Method spoofing for Laravel
    formData.append('name', form.value.name)

    if (form.value.phone_number) {
      formData.append('phone_number', form.value.phone_number)
    }

    if (selectedFile.value) {
      formData.append('profile_picture', selectedFile.value)
    }

    if (form.value.current_password) {
      formData.append('current_password', form.value.current_password)
    }

    if (form.value.password) {
      formData.append('password', form.value.password)
      formData.append('password_confirmation', form.value.password_confirmation)
    }

    // Use POST with _method: PUT for file upload support
    const response = await axios.post('/profile', formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    })

    successMessage.value = 'Profile updated successfully'

    // Refresh auth user data
    await checkAuth()

    // Reset password fields
    form.value.current_password = ''
    form.value.password = ''
    form.value.password_confirmation = ''

    // Clear file selection
    selectedFile.value = null
    // keep previewImage as it is now the current user image, or reset it?
    // Better to reset preview and show default user image from store (which is updated)
    previewImage.value = null
  } catch (error) {
    if (error.response?.status === 422) {
      errors.value = error.response.data.errors
    } else {
      console.error(error)
      errors.value = {
        general: [error.response?.data?.message || 'Something went wrong']
      }
    }
  } finally {
    isLoading.value = false
  }
}
</script>

<template>
  <div v-if="user" class="flex flex-col gap-8 w-full">
    <!-- Page Header -->
    <div class="flex flex-col gap-1">
      <h1 class="font-bold text-2xl lg:text-3xl text-custom-black dark:text-white">Profil Saya</h1>
      <p class="text-custom-grey dark:text-gray-400 font-medium">Kelola informasi profil dan keamanan akunmu.</p>
    </div>

    <!-- Profile Card -->
    <div class="flex flex-col w-full bg-white dark:bg-white/[0.02] rounded-2xl p-6 md:p-8 border border-gray-100 dark:border-white/10 shadow-sm gap-8">
      <!-- Avatar Section -->
      <div class="flex items-center gap-5">
        <div
          class="group relative flex size-[90px] lg:size-[100px] shrink-0 rounded-full bg-gray-50 dark:bg-white/5 overflow-hidden cursor-pointer border-2 border-gray-100 dark:border-white/10 hover:border-custom-blue transition-colors"
          @click="triggerFileInput"
        >
          <img
            :src="previewImage || user?.profile_picture"
            class="size-full object-cover transition-opacity duration-300 group-hover:opacity-75"
            alt="profile picture"
          />
          <!-- Hover Overlay -->
          <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-300 bg-black/30">
            <img src="@/assets/images/icons/camera-white.svg" class="size-7" alt="change" />
          </div>
        </div>

        <input ref="fileInput" type="file" accept="image/*" class="hidden" @change="handleFileChange" />

        <div class="flex flex-col gap-1.5">
          <p class="font-bold text-xl text-custom-black dark:text-white capitalize">{{ user?.name || '&nbsp;' }}</p>
          <p class="text-custom-grey dark:text-gray-400 text-sm">Klik foto untuk mengganti gambar profil.</p>
          <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-custom-blue/10 dark:bg-custom-blue/20 text-custom-blue text-xs font-bold rounded-full w-fit capitalize">
            {{ user?.role }}
          </span>
        </div>
      </div>

      <!-- Form -->
      <form class="flex flex-col gap-6 w-full max-w-[600px]" @submit.prevent="handleSubmit">
        <!-- Success Message -->
        <Transition
          enter-active-class="transition-all duration-300 ease-out"
          enter-from-class="opacity-0 -translate-y-1"
          enter-to-class="opacity-100 translate-y-0"
        >
          <div
            v-if="successMessage"
            class="flex items-center gap-3 p-4 rounded-2xl bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800"
          >
            <div class="shrink-0 w-8 h-8 rounded-full bg-green-100 dark:bg-green-900/40 flex items-center justify-center">
              <svg class="w-4 h-4 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
              </svg>
            </div>
            <p class="text-green-700 dark:text-green-400 font-medium text-sm">{{ successMessage }}</p>
          </div>
        </Transition>

        <!-- General Error -->
        <Transition
          enter-active-class="transition-all duration-300 ease-out"
          enter-from-class="opacity-0 -translate-y-1"
          enter-to-class="opacity-100 translate-y-0"
        >
          <div v-if="errors.general" class="flex items-center gap-3 p-4 rounded-2xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800">
            <div class="shrink-0 w-8 h-8 rounded-full bg-red-100 dark:bg-red-900/40 flex items-center justify-center">
              <svg class="w-4 h-4 text-red-600 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </div>
            <p class="text-red-700 dark:text-red-400 font-medium text-sm">{{ errors.general[0] }}</p>
          </div>
        </Transition>

        <!-- Name Field -->
        <div class="flex flex-col gap-2">
          <label class="font-semibold text-custom-black dark:text-white text-sm ml-1">Nama Lengkap</label>
          <div class="group relative transition-all duration-300">
            <div class="absolute inset-y-0 left-4 flex items-center pointer-events-none">
              <img src="@/assets/images/icons/profile-circle-grey.svg" class="size-5 custom-icon" alt="icon" />
            </div>
            <input
              v-model="form.name"
              type="text"
              autocomplete="name"
              class="w-full h-12 pl-12 pr-4 bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-white/10 rounded-full focus:bg-white dark:focus:bg-white/10 focus:border-custom-blue focus:ring-2 focus:ring-custom-blue/20 outline-none transition-all font-medium text-custom-black dark:text-white placeholder-gray-400 dark:placeholder-gray-500"
              placeholder="Masukkan nama lengkap"
              :class="{ '!border-red-500 !bg-red-50 dark:!bg-red-900/20': errors?.name }"
            />
          </div>
          <span v-if="errors.name" class="text-red-500 dark:text-red-400 text-xs font-medium ml-2">{{ errors.name[0] }}</span>
        </div>

        <!-- Phone Number -->
        <div class="flex flex-col gap-2">
          <label class="font-semibold text-custom-black dark:text-white text-sm ml-1">Nomor Telepon</label>
          <div class="group relative transition-all duration-300">
            <div class="absolute inset-y-0 left-4 flex items-center pointer-events-none">
              <img src="@/assets/images/icons/call-grey.svg" class="size-5 custom-icon" alt="icon" />
            </div>
            <input
              v-model="form.phone_number"
              type="tel"
              autocomplete="tel"
              class="w-full h-12 pl-12 pr-4 bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-white/10 rounded-full focus:bg-white dark:focus:bg-white/10 focus:border-custom-blue focus:ring-2 focus:ring-custom-blue/20 outline-none transition-all font-medium text-custom-black dark:text-white placeholder-gray-400 dark:placeholder-gray-500"
              placeholder="Masukkan nomor telepon"
              :class="{ '!border-red-500 !bg-red-50 dark:!bg-red-900/20': errors?.phone_number }"
              @input="form.phone_number = form.phone_number.replace(/[^0-9]/g, '').slice(0, 15)"
            />
          </div>
          <span v-if="errors.phone_number" class="text-red-500 dark:text-red-400 text-xs font-medium ml-2">{{ errors.phone_number[0] }}</span>
          <span
            v-else-if="form.phone_number && !form.phone_number.startsWith('08')"
            class="text-red-500 dark:text-red-400 text-xs font-medium ml-2"
          >Nomor telepon harus dimulai dengan 08</span>
        </div>

        <!-- Divider -->
        <div class="flex items-center gap-4 my-2">
          <div class="flex-1 h-px bg-gray-100 dark:bg-white/10"></div>
          <span class="text-xs font-semibold text-custom-grey dark:text-gray-500 uppercase tracking-wider">Ubah Password</span>
          <div class="flex-1 h-px bg-gray-100 dark:bg-white/10"></div>
        </div>

        <p class="text-custom-grey dark:text-gray-400 text-sm -mt-2">Kosongkan jika tidak ingin mengubah password.</p>

        <!-- Current Password -->
        <div class="flex flex-col gap-2">
          <label class="font-semibold text-custom-black dark:text-white text-sm ml-1">Password Saat Ini</label>
          <div class="group relative transition-all duration-300">
            <div class="absolute inset-y-0 left-4 flex items-center pointer-events-none">
              <img src="@/assets/images/icons/key-grey.svg" class="size-5 custom-icon" alt="icon" />
            </div>
            <input
              v-model="form.current_password"
              type="password"
              autocomplete="current-password"
              class="w-full h-12 pl-12 pr-4 bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-white/10 rounded-full focus:bg-white dark:focus:bg-white/10 focus:border-custom-blue focus:ring-2 focus:ring-custom-blue/20 outline-none transition-all font-medium text-custom-black dark:text-white placeholder-gray-400 dark:placeholder-gray-500"
              placeholder="Diperlukan untuk mengubah password"
              :class="{ '!border-red-500 !bg-red-50 dark:!bg-red-900/20': errors?.current_password }"
            />
          </div>
          <span v-if="errors.current_password" class="text-red-500 dark:text-red-400 text-xs font-medium ml-2">{{ errors.current_password[0] }}</span>
        </div>

        <!-- New Password -->
        <div class="flex flex-col gap-2">
          <label class="font-semibold text-custom-black dark:text-white text-sm ml-1">Password Baru</label>
          <div class="group relative transition-all duration-300">
            <div class="absolute inset-y-0 left-4 flex items-center pointer-events-none">
              <img src="@/assets/images/icons/key-grey.svg" class="size-5 custom-icon" alt="icon" />
            </div>
            <input
              v-model="form.password"
              type="password"
              autocomplete="new-password"
              class="w-full h-12 pl-12 pr-4 bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-white/10 rounded-full focus:bg-white dark:focus:bg-white/10 focus:border-custom-blue focus:ring-2 focus:ring-custom-blue/20 outline-none transition-all font-medium text-custom-black dark:text-white placeholder-gray-400 dark:placeholder-gray-500"
              placeholder="Minimal 8 karakter"
              :class="{ '!border-red-500 !bg-red-50 dark:!bg-red-900/20': errors?.password }"
            />
          </div>
          <span v-if="errors.password" class="text-red-500 dark:text-red-400 text-xs font-medium ml-2">{{ errors.password[0] }}</span>
        </div>

        <!-- Confirm New Password -->
        <div class="flex flex-col gap-2">
          <label class="font-semibold text-custom-black dark:text-white text-sm ml-1">Konfirmasi Password Baru</label>
          <div class="group relative transition-all duration-300">
            <div class="absolute inset-y-0 left-4 flex items-center pointer-events-none">
              <img src="@/assets/images/icons/key-grey.svg" class="size-5 custom-icon" alt="icon" />
            </div>
            <input
              v-model="form.password_confirmation"
              type="password"
              autocomplete="new-password"
              class="w-full h-12 pl-12 pr-4 bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-white/10 rounded-full focus:bg-white dark:focus:bg-white/10 focus:border-custom-blue focus:ring-2 focus:ring-custom-blue/20 outline-none transition-all font-medium text-custom-black dark:text-white placeholder-gray-400 dark:placeholder-gray-500"
              placeholder="Ulangi password baru"
            />
          </div>
        </div>

        <!-- Submit -->
        <div class="flex justify-end pt-4">
          <button
            type="submit"
            :disabled="isLoading"
            class="flex items-center justify-center h-12 px-8 rounded-full bg-custom-blue text-white font-bold text-base hover:bg-blue-700 hover:shadow-lg hover:shadow-custom-blue/20 active:scale-[0.98] transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <div
              v-if="isLoading"
              class="size-5 border-2 border-white border-t-transparent rounded-full animate-spin mr-2"
            ></div>
            Simpan Perubahan
          </button>
        </div>
      </form>
    </div>
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
