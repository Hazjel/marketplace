<script setup>
import { onMounted, ref, reactive } from 'vue'
import { RouterLink, useRouter, useRoute } from 'vue-router'
import { axiosInstance } from '@/plugins/axios'
import { useToast } from 'vue-toastification'
import { debounce } from 'lodash'

const router = useRouter()
const route = useRoute()
const toast = useToast()

const isEdit = route.params.id ? true : false
const loading = ref(false)
const submitting = ref(false)

const form = reactive({
  label: '',
  recipient_name: '',
  phone: '',
  address: '',
  city: '',
  city_id: '',
  postal_code: '',
  is_primary: false
})

// City Search Logic
const citySearch = ref('')
const cityOptions = ref([])
const showCityOptions = ref(false)
const loadingCities = ref(false)

const handleCityInput = debounce(async (search) => {
  if (!search.trim()) {
    showCityOptions.value = false
    return
  }
  loadingCities.value = true
  try {
    // Using the same endpoint as checkout (proxy in vite config or direct call if configured)
    // Assuming we have a proxy or direct route. Since checkout used /tariff/api... let's try to verify if we need a backend endpoint wrapper.
    // For consistent CORS handling, it's safer to use our own backend if available, but Checkout used direct proxy.
    // We will use the same proxy path: /tariff/api/v1/destination/search

    const response = await fetch(
      `/tariff/api/v1/destination/search?keyword=${encodeURIComponent(search)}`,
      {
        headers: {
          'x-api-key': import.meta.env.VITE_RAJAONGKIR_API_KEY
        }
      }
    )
    const data = await response.json()
    cityOptions.value = data.data
    showCityOptions.value = true
  } catch (err) {
    console.error(err)
  } finally {
    loadingCities.value = false
  }
}, 500)

const selectCity = (city) => {
  form.city = city.label
  form.city_id = String(city.id)
  form.postal_code = city.zip_code // Auto-fill postal code
  citySearch.value = city.label
  showCityOptions.value = false
}

const fetchAddress = async () => {
  if (!isEdit) return
  loading.value = true
  try {
    const response = await axiosInstance.get(`/address/${route.params.id}`)
    const data = response.data.data
    Object.assign(form, {
      label: data.label,
      recipient_name: data.recipient_name,
      phone: data.phone,
      address: data.address,
      city: data.city,
      city_id: data.city_id,
      postal_code: data.postal_code,
      is_primary: !!data.is_primary
    })
    citySearch.value = data.city
  } catch {
    toast.error('Failed to load address')
    router.push({ name: 'user.settings.address' })
  } finally {
    loading.value = false
  }
}

const submit = async () => {
  if (!form.city_id) {
    toast.error('Please select a city from the list')
    return
  }
  submitting.value = true
  try {
    if (isEdit) {
      await axiosInstance.put(`/address/${route.params.id}`, form)
      toast.success('Address updated successfully')
    } else {
      await axiosInstance.post('/address', form)
      toast.success('Address created successfully')
    }
    router.push({ name: 'user.settings.address' })
  } catch (error) {
    toast.error(error.response?.data?.message || 'Failed to save address')
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  fetchAddress()
})
</script>

<template>
  <div class="flex flex-col gap-6 max-w-2xl mx-auto">
    <!-- Header -->
    <div class="flex items-center gap-4">
      <RouterLink
        :to="{ name: 'user.settings.address' }"
        class="size-10 rounded-full bg-gray-50 dark:bg-white/5 border border-gray-100 dark:border-white/10 flex items-center justify-center hover:bg-gray-100 dark:hover:bg-white/10 hover:border-gray-200 dark:hover:border-white/20 transition-all"
      >
        <svg class="size-5 text-custom-black dark:text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
      </RouterLink>
      <div class="flex flex-col gap-0.5">
        <h1 class="font-bold text-2xl text-custom-black dark:text-white">{{ isEdit ? 'Edit Alamat' : 'Tambah Alamat Baru' }}</h1>
        <p class="text-custom-grey dark:text-gray-400 text-sm font-medium">{{ isEdit ? 'Perbarui informasi alamat pengirimanmu.' : 'Isi detail alamat baru untuk pengiriman.' }}</p>
      </div>
    </div>

    <!-- Form Card -->
    <form
      class="flex flex-col gap-6 bg-white dark:bg-white/[0.02] p-6 md:p-8 rounded-2xl border border-gray-100 dark:border-white/10 shadow-sm"
      @submit.prevent="submit"
    >
      <!-- Label -->
      <div class="flex flex-col gap-2">
        <label class="font-semibold text-custom-black dark:text-white text-sm ml-1">Label Alamat</label>
        <div class="group relative transition-all duration-300">
          <div class="absolute inset-y-0 left-4 flex items-center pointer-events-none">
            <svg class="size-5 text-gray-400 group-focus-within:text-custom-blue transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
            </svg>
          </div>
          <input
            v-model="form.label"
            type="text"
            required
            placeholder="Contoh: Rumah, Kantor"
            class="w-full h-12 pl-12 pr-4 bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-white/10 rounded-full focus:bg-white dark:focus:bg-white/10 focus:border-custom-blue focus:ring-2 focus:ring-custom-blue/20 outline-none transition-all font-medium text-custom-black dark:text-white placeholder-gray-400 dark:placeholder-gray-500"
          />
        </div>
      </div>

      <!-- Recipient & Phone Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        <!-- Recipient -->
        <div class="flex flex-col gap-2">
          <label class="font-semibold text-custom-black dark:text-white text-sm ml-1">Nama Penerima</label>
          <div class="group relative transition-all duration-300">
            <div class="absolute inset-y-0 left-4 flex items-center pointer-events-none">
              <svg class="size-5 text-gray-400 group-focus-within:text-custom-blue transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
              </svg>
            </div>
            <input
              v-model="form.recipient_name"
              type="text"
              required
              placeholder="Nama lengkap penerima"
              class="w-full h-12 pl-12 pr-4 bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-white/10 rounded-full focus:bg-white dark:focus:bg-white/10 focus:border-custom-blue focus:ring-2 focus:ring-custom-blue/20 outline-none transition-all font-medium text-custom-black dark:text-white placeholder-gray-400 dark:placeholder-gray-500"
            />
          </div>
        </div>
        <!-- Phone -->
        <div class="flex flex-col gap-2">
          <label class="font-semibold text-custom-black dark:text-white text-sm ml-1">Nomor Telepon</label>
          <div class="group relative transition-all duration-300">
            <div class="absolute inset-y-0 left-4 flex items-center pointer-events-none">
              <svg class="size-5 text-gray-400 group-focus-within:text-custom-blue transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
              </svg>
            </div>
            <input
              v-model="form.phone"
              type="tel"
              required
              placeholder="08..."
              class="w-full h-12 pl-12 pr-4 bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-white/10 rounded-full focus:bg-white dark:focus:bg-white/10 focus:border-custom-blue focus:ring-2 focus:ring-custom-blue/20 outline-none transition-all font-medium text-custom-black dark:text-white placeholder-gray-400 dark:placeholder-gray-500"
            />
          </div>
        </div>
      </div>

      <!-- City Search -->
      <div class="flex flex-col gap-2 relative">
        <label class="font-semibold text-custom-black dark:text-white text-sm ml-1">Kota / Kecamatan</label>
        <div class="group relative transition-all duration-300">
          <div class="absolute inset-y-0 left-4 flex items-center pointer-events-none">
            <svg class="size-5 text-gray-400 group-focus-within:text-custom-blue transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>
          <input
            v-model="citySearch"
            type="text"
            required
            placeholder="Cari kota atau kecamatan..."
            class="w-full h-12 pl-12 pr-12 bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-white/10 rounded-full focus:bg-white dark:focus:bg-white/10 focus:border-custom-blue focus:ring-2 focus:ring-custom-blue/20 outline-none transition-all font-medium text-custom-black dark:text-white placeholder-gray-400 dark:placeholder-gray-500"
            @input="handleCityInput(citySearch)"
          />
          <div v-if="loadingCities" class="absolute inset-y-0 right-4 flex items-center">
            <div class="size-5 border-2 border-custom-blue border-t-transparent rounded-full animate-spin"></div>
          </div>
        </div>

        <!-- Dropdown -->
        <Transition
          enter-active-class="transition-all duration-200 ease-out"
          enter-from-class="opacity-0 -translate-y-1"
          enter-to-class="opacity-100 translate-y-0"
          leave-active-class="transition-all duration-150 ease-in"
          leave-from-class="opacity-100 translate-y-0"
          leave-to-class="opacity-0 -translate-y-1"
        >
          <div
            v-if="showCityOptions"
            class="absolute top-full left-0 w-full mt-2 bg-white dark:bg-gray-800 border border-gray-100 dark:border-white/10 rounded-2xl shadow-xl z-50 max-h-60 overflow-y-auto"
          >
            <div
              v-for="city in cityOptions"
              :key="city.id"
              class="px-4 py-3 hover:bg-gray-50 dark:hover:bg-white/5 cursor-pointer text-sm font-medium text-custom-black dark:text-white border-b border-gray-50 dark:border-white/5 last:border-0 transition-colors"
              @click="selectCity(city)"
            >
              {{ city.label }}
            </div>
          </div>
        </Transition>
      </div>

      <!-- Address & Postal Code Grid -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
        <!-- Full Address -->
        <div class="flex flex-col gap-2 md:col-span-2">
          <label class="font-semibold text-custom-black dark:text-white text-sm ml-1">Alamat Lengkap</label>
          <textarea
            v-model="form.address"
            required
            rows="3"
            placeholder="Nama jalan, nomor rumah, RT/RW, dll."
            class="w-full p-4 bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-white/10 rounded-2xl focus:bg-white dark:focus:bg-white/10 focus:border-custom-blue focus:ring-2 focus:ring-custom-blue/20 outline-none transition-all font-medium text-custom-black dark:text-white placeholder-gray-400 dark:placeholder-gray-500 resize-none"
          ></textarea>
        </div>
        <!-- Postal Code -->
        <div class="flex flex-col gap-2">
          <label class="font-semibold text-custom-black dark:text-white text-sm ml-1">Kode Pos</label>
          <div class="group relative transition-all duration-300">
            <div class="absolute inset-y-0 left-4 flex items-center pointer-events-none">
              <svg class="size-5 text-gray-400 group-focus-within:text-custom-blue transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
              </svg>
            </div>
            <input
              v-model="form.postal_code"
              type="text"
              required
              placeholder="12345"
              class="w-full h-12 pl-12 pr-4 bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-white/10 rounded-full focus:bg-white dark:focus:bg-white/10 focus:border-custom-blue focus:ring-2 focus:ring-custom-blue/20 outline-none transition-all font-medium text-custom-black dark:text-white placeholder-gray-400 dark:placeholder-gray-500"
            />
          </div>
        </div>
      </div>

      <!-- Primary Toggle -->
      <label class="flex items-center gap-3 cursor-pointer group py-1">
        <input v-model="form.is_primary" type="checkbox" class="sr-only peer" />
        <div
          class="w-[52px] h-7 bg-gray-200 dark:bg-white/10 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 dark:after:border-transparent after:border after:rounded-full after:h-[24px] after:w-[24px] after:shadow-sm after:transition-all peer-checked:bg-custom-blue relative"
        ></div>
        <div class="flex flex-col">
          <span class="font-semibold text-sm text-custom-black dark:text-white group-hover:text-custom-blue transition-colors">Jadikan Alamat Utama</span>
          <span class="text-xs text-custom-grey dark:text-gray-500">Alamat ini akan digunakan secara default saat checkout.</span>
        </div>
      </label>

      <!-- Submit Button -->
      <button
        type="submit"
        :disabled="submitting"
        class="w-full h-12 flex items-center justify-center rounded-full bg-custom-blue text-white font-bold text-base hover:bg-blue-700 hover:shadow-lg hover:shadow-custom-blue/20 active:scale-[0.98] transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed mt-2"
      >
        <div
          v-if="submitting"
          class="size-5 border-2 border-white border-t-transparent rounded-full animate-spin mr-2"
        ></div>
        <span>{{ isEdit ? 'Perbarui Alamat' : 'Simpan Alamat' }}</span>
      </button>
    </form>
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
