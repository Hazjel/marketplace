<script setup>
import { ref, computed } from 'vue'

import { axiosInstance as axios } from '@/plugins/axios'
import { useAuthStore } from '@/stores/auth'
import Cookies from 'js-cookie'
import { debounce } from 'lodash'

const authStore = useAuthStore()

const isLoading = ref(false)
const errors = ref({})
const successMessage = ref(null)

// Address State
const addressSearch = ref('')
const addressOptions = ref([])
const showAddressOptions = ref(false)
const loadingAddress = ref(false)

const form = ref({
  name: '',
  phone: '',
  city: '',
  address: '',
  postal_code: ''
})

const googleMapsUrl = computed(() => {
  if (!form.value.city) return ''
  const query = encodeURIComponent(`${form.value.address} ${form.value.city}`)
  return `https://maps.google.com/maps?q=${query}&t=&z=13&ie=UTF8&iwloc=&output=embed`
})

import axiosRaw from 'axios'

// ... other imports

const handleAddressInput = debounce(async (search) => {
  if (!search.trim()) {
    showAddressOptions.value = false
    form.value.city = ''
    form.value.postal_code = ''
    return
  }

  loadingAddress.value = true
  try {
    // Use clean axios instance to bypass baseURL and hit Vite proxy
    const response = await axiosRaw.get(
      `/tariff/api/v1/destination/search?keyword=${encodeURIComponent(search)}`,
      {
        headers: {
          'x-api-key': import.meta.env.VITE_RAJAONGKIR_API_KEY
        }
      }
    )
    addressOptions.value = response.data.data
    showAddressOptions.value = true
  } catch (err) {
    console.error(err)
    addressOptions.value = []
  } finally {
    loadingAddress.value = false
  }
}, 500)

const handleAddressSelect = (selected) => {
  form.value.city = selected.city_name
  form.value.postal_code = selected.zip_code
  // Pre-fill address text area with the label (District, City, Province)
  form.value.address = selected.label
  addressSearch.value = selected.label
  showAddressOptions.value = false
}

const handleSubmit = async () => {
  isLoading.value = true
  errors.value = {}
  successMessage.value = null

  try {
    const payload = {
      name: form.value.name,
      phone: form.value.phone,
      city: form.value.city,
      address: form.value.address,
      postal_code: form.value.postal_code
    }

    const response = await axios.post('/register-store', payload)

    successMessage.value = 'Store created successfully! Redirecting...'

    // Update Auth Store directly from response
    const newUser = response.data.data.user
    Cookies.set('token', newUser.token)
    authStore.user = newUser

    setTimeout(() => {
      // Force reload to ensure all states (sidebar etc) are fresh
      window.location.href = '/admin/dashboard'
    }, 1000)
  } catch (error) {
    if (error.response?.status === 422) {
      errors.value = error.response.data.errors
    } else if (error.response?.status === 400) {
      errors.value = {
        general: [error.response.data.message]
      }
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
  <div class="w-full min-h-screen lg:grid lg:grid-cols-2">
    <!-- Left Side: Form -->
    <div class="flex flex-col justify-center px-6 py-12 lg:px-16 xl:px-24 bg-white dark:bg-gray-900 overflow-y-auto">
      <div class="w-full max-w-xl mx-auto">
        <form autocomplete="off" class="flex flex-col gap-8" @submit.prevent="handleSubmit">
          <!-- Header -->
          <div class="flex flex-col gap-3">
            <img
              src="@/assets/images/logos/blukios_logo.png"
              class="h-8 lg:h-10 w-fit mb-2 dark:brightness-0 dark:invert"
              alt="Blukios"
            />
            <h1 class="font-bold text-3xl lg:text-4xl text-custom-black dark:text-white">
              Buka Toko Sekarang! 🏪
            </h1>
            <p class="text-custom-grey dark:text-gray-400 text-base lg:text-lg">Lengkapi detail tokomu untuk mulai berjualan.</p>
          </div>

          <!-- Global Error -->
          <Transition
            enter-active-class="transition-all duration-300 ease-out"
            enter-from-class="opacity-0 -translate-y-1"
            enter-to-class="opacity-100 translate-y-0"
          >
            <div v-if="errors.general" class="p-4 rounded-2xl bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 font-medium border border-red-200 dark:border-red-800">
              {{ errors.general[0] }}
            </div>
          </Transition>
          <Transition
            enter-active-class="transition-all duration-300 ease-out"
            enter-from-class="opacity-0 -translate-y-1"
            enter-to-class="opacity-100 translate-y-0"
          >
            <div v-if="successMessage" class="p-4 rounded-2xl bg-green-50 dark:bg-green-900/20 text-green-600 dark:text-green-400 font-medium border border-green-200 dark:border-green-800">
              {{ successMessage }}
            </div>
          </Transition>

          <div class="flex flex-col gap-5">
            <!-- Store Name -->
            <div class="flex flex-col gap-2">
              <label class="font-semibold text-custom-black dark:text-white text-sm ml-1">Nama Toko</label>
              <div class="group relative transition-all duration-300">
                <div class="absolute inset-y-0 left-4 flex items-center pointer-events-none">
                  <img src="@/assets/images/icons/shop-grey.svg" class="size-5 custom-icon" alt="icon" />
                </div>
                <input
                  v-model="form.name"
                  type="text"
                  class="w-full h-12 pl-12 pr-4 bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-white/10 rounded-full focus:bg-white dark:focus:bg-white/10 focus:border-custom-blue focus:ring-2 focus:ring-custom-blue/20 outline-none transition-all font-medium text-custom-black dark:text-white placeholder-gray-400 dark:placeholder-gray-500"
                  placeholder="Nama toko kamu"
                  required
                  :class="{ '!border-red-500 !bg-red-50 dark:!bg-red-900/20': errors?.name }"
                />
              </div>
              <span v-if="errors.name" class="text-red-500 dark:text-red-400 text-xs font-medium ml-2">{{ errors.name[0] }}</span>
            </div>

            <!-- Phone -->
            <div class="flex flex-col gap-2">
              <label class="font-semibold text-custom-black dark:text-white text-sm ml-1">Nomor Telepon Toko</label>
              <div class="group relative transition-all duration-300">
                <div class="absolute inset-y-0 left-4 flex items-center pointer-events-none">
                  <img src="@/assets/images/icons/call-grey.svg" class="size-5 custom-icon" alt="icon" />
                </div>
                <input
                  v-model="form.phone"
                  type="tel"
                  class="w-full h-12 pl-12 pr-4 bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-white/10 rounded-full focus:bg-white dark:focus:bg-white/10 focus:border-custom-blue focus:ring-2 focus:ring-custom-blue/20 outline-none transition-all font-medium text-custom-black dark:text-white placeholder-gray-400 dark:placeholder-gray-500"
                  placeholder="Contoh: 0812345678"
                  :class="{ '!border-red-500 !bg-red-50 dark:!bg-red-900/20': errors?.phone }"
                  @input="form.phone = form.phone.replace(/[^0-9]/g, '').slice(0, 15)"
                />
              </div>
              <span v-if="errors.phone" class="text-red-500 dark:text-red-400 text-xs font-medium ml-2">{{ errors.phone[0] }}</span>
              <span v-else-if="form.phone && !form.phone.startsWith('08')" class="text-red-500 dark:text-red-400 text-xs font-medium ml-2">
                Nomor telepon harus dimulai dengan 08
              </span>
            </div>

            <!-- Address Search (RajaOngkir) -->
            <div class="flex flex-col gap-2 relative">
              <label class="font-semibold text-custom-black dark:text-white text-sm ml-1">Cari Lokasi (Kecamatan/Kota)</label>
              <div class="group relative transition-all duration-300">
                <div class="absolute inset-y-0 left-4 flex items-center pointer-events-none">
                  <img src="@/assets/images/icons/global-search-grey.svg" class="size-5 custom-icon" alt="icon" />
                </div>
                <input
                  v-model="addressSearch"
                  type="text"
                  class="w-full h-12 pl-12 pr-4 bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-white/10 rounded-full focus:bg-white dark:focus:bg-white/10 focus:border-custom-blue focus:ring-2 focus:ring-custom-blue/20 outline-none transition-all font-medium text-custom-black dark:text-white placeholder-gray-400 dark:placeholder-gray-500"
                  placeholder="Ketik nama kecamatan atau kota..."
                  @input="handleAddressInput(addressSearch)"
                />
                <div v-if="loadingAddress" class="absolute inset-y-0 right-4 flex items-center">
                  <div class="size-5 border-2 border-custom-blue border-t-transparent rounded-full animate-spin"></div>
                </div>
              </div>

              <!-- Dropdown Results -->
              <Transition
                enter-active-class="transition-all duration-200 ease-out"
                enter-from-class="opacity-0 -translate-y-1"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition-all duration-150 ease-in"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 -translate-y-1"
              >
                <ul
                  v-if="showAddressOptions"
                  class="absolute top-full mt-2 w-full bg-white dark:bg-gray-800 border border-gray-100 dark:border-white/10 rounded-2xl shadow-xl max-h-[300px] overflow-y-auto z-50"
                >
                  <li v-if="loadingAddress" class="p-4 text-center text-custom-grey dark:text-gray-400 font-medium">
                    Mencari...
                  </li>
                  <li
                    v-for="option in addressOptions"
                    v-else
                    :key="option.id"
                    class="p-4 hover:bg-gray-50 dark:hover:bg-white/5 cursor-pointer text-base font-medium transition-colors border-b border-gray-50 dark:border-white/5 last:border-0 leading-relaxed text-custom-black dark:text-white"
                    @click="handleAddressSelect(option)"
                  >
                    {{ option.label }}
                  </li>
                  <li
                    v-if="!loadingAddress && addressOptions.length === 0 && addressSearch"
                    class="p-4 text-center text-custom-grey dark:text-gray-400 font-medium"
                  >
                    Tidak ada hasil ditemukan
                  </li>
                </ul>
              </Transition>
            </div>

            <!-- City & Postal Code (Grid) -->
            <div class="grid grid-cols-2 gap-4">
              <div class="flex flex-col gap-2">
                <label class="font-semibold text-custom-black dark:text-white text-sm ml-1">Kota</label>
                <div class="group relative transition-all duration-300">
                  <div class="absolute inset-y-0 left-4 flex items-center pointer-events-none">
                    <img src="@/assets/images/icons/location-grey.svg" class="size-5 custom-icon" alt="icon" />
                  </div>
                  <input
                    v-model="form.city"
                    type="text"
                    class="w-full h-12 pl-12 pr-4 bg-gray-100 dark:bg-white/5 border border-gray-200 dark:border-white/10 rounded-full text-custom-black dark:text-white font-medium placeholder-gray-400 dark:placeholder-gray-500 cursor-not-allowed"
                    placeholder="Otomatis terisi"
                    readonly
                    :class="{ '!border-red-500': errors?.city }"
                  />
                </div>
                <span v-if="errors.city" class="text-red-500 dark:text-red-400 text-xs font-medium ml-2">{{ errors.city[0] }}</span>
              </div>
              <div class="flex flex-col gap-2">
                <label class="font-semibold text-custom-black dark:text-white text-sm ml-1">Kode Pos</label>
                <div class="group relative transition-all duration-300">
                  <div class="absolute inset-y-0 left-4 flex items-center pointer-events-none">
                    <img src="@/assets/images/icons/box-grey.svg" class="size-5 custom-icon" alt="icon" />
                  </div>
                  <input
                    v-model="form.postal_code"
                    type="text"
                    class="w-full h-12 pl-12 pr-4 bg-gray-100 dark:bg-white/5 border border-gray-200 dark:border-white/10 rounded-full text-custom-black dark:text-white font-medium placeholder-gray-400 dark:placeholder-gray-500 cursor-not-allowed"
                    placeholder="Otomatis terisi"
                    readonly
                    :class="{ '!border-red-500': errors?.postal_code }"
                  />
                </div>
                <span v-if="errors.postal_code" class="text-red-500 dark:text-red-400 text-xs font-medium ml-2">{{ errors.postal_code[0] }}</span>
              </div>
            </div>

            <!-- Full Address -->
            <div class="flex flex-col gap-2">
              <label class="font-semibold text-custom-black dark:text-white text-sm ml-1">Detail Alamat Lengkap</label>
              <div class="group relative transition-all duration-300">
                <div class="absolute top-4 left-4 flex items-start pointer-events-none">
                  <img src="@/assets/images/icons/note-grey.svg" class="size-5 custom-icon" alt="icon" />
                </div>
                <textarea
                  v-model="form.address"
                  class="w-full min-h-[100px] pl-12 pr-4 py-3.5 bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-white/10 rounded-2xl focus:bg-white dark:focus:bg-white/10 focus:border-custom-blue focus:ring-2 focus:ring-custom-blue/20 outline-none transition-all font-medium text-custom-black dark:text-white placeholder-gray-400 dark:placeholder-gray-500 resize-none"
                  placeholder="Nama jalan, nomor bangunan, dll."
                  :class="{ '!border-red-500 !bg-red-50 dark:!bg-red-900/20': errors?.address }"
                ></textarea>
              </div>
              <span v-if="errors.address" class="text-red-500 dark:text-red-400 text-xs font-medium ml-2">{{ errors.address[0] }}</span>
            </div>

            <!-- Mobile/Tablet Map Preview -->
            <div v-if="form.city" class="flex flex-col gap-2 lg:hidden">
              <label class="font-semibold text-custom-black dark:text-white text-sm ml-1">Preview Lokasi Toko</label>
              <div class="w-full h-[200px] rounded-2xl overflow-hidden border border-gray-200 dark:border-white/10">
                <iframe
                  width="100%"
                  height="100%"
                  frameborder="0"
                  scrolling="no"
                  marginheight="0"
                  marginwidth="0"
                  :src="googleMapsUrl"
                >
                </iframe>
              </div>
            </div>
          </div>

          <!-- Actions -->
          <div class="flex flex-col gap-4 mt-2">
            <button
              type="submit"
              :disabled="isLoading"
              class="w-full h-12 flex items-center justify-center rounded-full bg-custom-blue text-white font-bold text-base hover:bg-blue-700 hover:shadow-lg hover:shadow-custom-blue/20 active:scale-[0.98] transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <span v-if="isLoading" class="animate-spin mr-2">⏳</span>
              {{ isLoading ? 'Memproses...' : 'Buka Toko Sekarang' }}
            </button>
            <RouterLink
              :to="{ name: 'app.home' }"
              class="text-center font-medium text-custom-grey dark:text-gray-400 hover:text-custom-blue transition-colors"
            >
              Batal
            </RouterLink>
          </div>
        </form>
      </div>
    </div>

    <!-- Right Side: Sticky Map / Illustration -->
    <div class="hidden lg:block sticky top-0 h-screen bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-gray-800 dark:to-gray-900">
      <div v-if="form.city" class="w-full h-full relative">
        <iframe
          width="100%"
          height="100%"
          frameborder="0"
          scrolling="no"
          marginheight="0"
          marginwidth="0"
          :src="googleMapsUrl"
          class="absolute inset-0 w-full h-full object-cover"
        >
        </iframe>
        <!-- Floating Info Card -->
        <div
          class="absolute top-8 left-8 z-10 bg-white/95 dark:bg-gray-800/95 backdrop-blur-md p-5 rounded-2xl shadow-[0_8px_30px_rgb(0,0,0,0.08)] border border-white/20 dark:border-white/10 flex flex-col gap-4 min-w-[260px] max-w-[320px]"
        >
          <div class="flex items-center gap-3">
            <div
              class="w-10 h-10 rounded-full bg-blue-50 dark:bg-blue-900/30 flex items-center justify-center shrink-0"
            >
              <img src="@/assets/images/icons/location-grey.svg" class="w-5 h-5" alt="city" />
            </div>
            <div class="flex flex-col">
              <p class="text-[11px] font-bold text-custom-grey dark:text-gray-400 uppercase tracking-wider mb-0.5">
                Lokasi Kota
              </p>
              <p class="font-bold text-custom-black dark:text-white text-lg leading-tight">{{ form.city }}</p>
            </div>
          </div>
          <div class="h-px w-full bg-gray-100 dark:bg-white/10"></div>
          <div class="flex items-center gap-3">
            <div
              class="w-10 h-10 rounded-full bg-blue-50 dark:bg-blue-900/30 flex items-center justify-center shrink-0"
            >
              <img src="@/assets/images/icons/box-grey.svg" class="w-5 h-5" alt="zip" />
            </div>
            <div class="flex flex-col">
              <p class="text-[11px] font-bold text-custom-grey dark:text-gray-400 uppercase tracking-wider mb-0.5">
                Kode Pos
              </p>
              <p class="font-bold text-custom-black dark:text-white text-lg leading-tight">
                {{ form.postal_code }}
              </p>
            </div>
          </div>
        </div>
      </div>
      <div
        v-else
        class="flex flex-col items-center justify-center h-full text-custom-grey gap-6 p-10 text-center"
      >
        <div class="w-20 h-20 rounded-full bg-white dark:bg-gray-700 flex items-center justify-center shadow-sm">
          <img
            src="@/assets/images/icons/location-grey.svg"
            class="w-10 h-10 opacity-50"
            alt="location"
          />
        </div>
        <div>
          <h3 class="font-bold text-xl text-custom-black dark:text-white mb-2">Temukan Lokasi Tokomu</h3>
          <p class="font-medium text-gray-400 dark:text-gray-500 max-w-xs mx-auto">
            Masukkan kota atau kecamatan di kolom pencarian untuk melihat lokasi tokomu di peta.
          </p>
        </div>
      </div>
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
