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
    <div class="flex items-center gap-4">
      <RouterLink
        :to="{ name: 'user.settings.address' }"
        class="size-10 rounded-full bg-gray-100 flex items-center justify-center hover:bg-gray-200 transition-colors"
      >
        <i class="fa-solid fa-arrow-left"></i>
      </RouterLink>
      <h1 class="font-bold text-2xl">{{ isEdit ? 'Edit Address' : 'Add New Address' }}</h1>
    </div>

    <form
      class="flex flex-col gap-6 bg-white p-6 md:p-8 rounded-3xl border border-custom-stroke shadow-sm"
      @submit.prevent="submit"
    >
      <!-- Label -->
      <div class="flex flex-col gap-2">
        <label class="font-bold text-custom-black">Address Label</label>
        <input
          v-model="form.label"
          type="text"
          required
          placeholder="e.g. Home, Office"
          class="w-full h-12 px-4 rounded-xl border border-custom-stroke focus:border-custom-black focus:ring-0 outline-none transition-all"
        />
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Recipient -->
        <div class="flex flex-col gap-2">
          <label class="font-bold text-custom-black">Recipient Name</label>
          <input
            v-model="form.recipient_name"
            type="text"
            required
            placeholder="Full Name"
            class="w-full h-12 px-4 rounded-xl border border-custom-stroke focus:border-custom-black focus:ring-0 outline-none transition-all"
          />
        </div>
        <!-- Phone -->
        <div class="flex flex-col gap-2">
          <label class="font-bold text-custom-black">Phone Number</label>
          <input
            v-model="form.phone"
            type="tel"
            required
            placeholder="08..."
            class="w-full h-12 px-4 rounded-xl border border-custom-stroke focus:border-custom-black focus:ring-0 outline-none transition-all"
          />
        </div>
      </div>

      <!-- City Search -->
      <div class="flex flex-col gap-2 relative">
        <label class="font-bold text-custom-black">City / District</label>
        <div class="relative">
          <input
            v-model="citySearch"
            type="text"
            required
            placeholder="Search your city..."
            class="w-full h-12 px-4 pr-10 rounded-xl border border-custom-stroke focus:border-custom-black focus:ring-0 outline-none transition-all"
            @input="handleCityInput(citySearch)"
          />
          <div v-if="loadingCities" class="absolute right-3 top-3.5">
            <div
              class="size-5 border-2 border-custom-grey border-t-transparent rounded-full animate-spin"
            ></div>
          </div>
        </div>

        <!-- Dropdown -->
        <div
          v-if="showCityOptions"
          class="absolute top-full left-0 w-full mt-2 bg-white border border-custom-stroke rounded-xl shadow-xl z-50 max-h-60 overflow-y-auto"
        >
          <div
            v-for="city in cityOptions"
            :key="city.id"
            class="px-4 py-3 hover:bg-gray-50 cursor-pointer text-sm font-medium border-b border-gray-50 last:border-0"
            @click="selectCity(city)"
          >
            {{ city.label }}
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Address Detail -->
        <div class="flex flex-col gap-2 md:col-span-2">
          <label class="font-bold text-custom-black">Full Address</label>
          <textarea
            v-model="form.address"
            required
            rows="3"
            placeholder="Street name, house number, etc."
            class="w-full p-4 rounded-xl border border-custom-stroke focus:border-custom-black focus:ring-0 outline-none transition-all resize-none"
          ></textarea>
        </div>
        <!-- Postal Code -->
        <div class="flex flex-col gap-2">
          <label class="font-bold text-custom-black">Postal Code</label>
          <input
            v-model="form.postal_code"
            type="text"
            required
            placeholder="12345"
            class="w-full h-12 px-4 rounded-xl border border-custom-stroke focus:border-custom-black focus:ring-0 outline-none transition-all"
          />
        </div>
      </div>

      <!-- Primary Toggle -->
      <label class="flex items-center gap-3 cursor-pointer group">
        <input v-model="form.is_primary" type="checkbox" class="sr-only peer" />
        <div
          class="w-[60px] h-8 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-[28px] after:w-[28px] after:transition-all peer-checked:bg-custom-blue relative"
        ></div>
        <span class="font-bold text-custom-black group-hover:text-custom-blue transition-colors"
          >Set as Primary Address</span
        >
      </label>

      <button
        type="submit"
        :disabled="submitting"
        class="w-full h-14 bg-custom-black text-white rounded-xl font-bold hover:bg-black/90 transition-all flex items-center justify-center gap-2 mt-4 disabled:bg-gray-300"
      >
        <div
          v-if="submitting"
          class="size-5 border-2 border-white border-t-transparent rounded-full animate-spin"
        ></div>
        <span>{{ isEdit ? 'Update Address' : 'Save Address' }}</span>
      </button>
    </form>
  </div>
</template>
