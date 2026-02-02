<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { axiosInstance as axios } from '@/plugins/axios'
import { useAuthStore } from '@/stores/auth'
import Cookies from 'js-cookie'
import { debounce } from 'lodash'

const router = useRouter()
const authStore = useAuthStore()
const { checkAuth } = authStore

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
    <div class="flex flex-col justify-center px-6 py-12 lg:px-16 xl:px-24 bg-white overflow-y-auto">
      <div class="w-full max-w-xl mx-auto">
        <form autocomplete="off" class="flex flex-col gap-8" @submit.prevent="handleSubmit">
          <div class="flex flex-col gap-2">
            <h1 class="font-bold text-3xl lg:text-4xl text-custom-black">
              Start Selling Today! 🚀
            </h1>
            <p class="text-custom-grey text-lg">Fill in your store details to begin.</p>
          </div>

          <!-- Global Error -->
          <div v-if="errors.general" class="p-4 rounded-xl bg-red-50 text-custom-red font-medium">
            {{ errors.general[0] }}
          </div>
          <div v-if="successMessage" class="p-4 rounded-xl bg-green-50 text-green-600 font-medium">
            {{ successMessage }}
          </div>

          <div class="flex flex-col gap-6">
            <!-- Store Name -->
            <div class="flex flex-col gap-3">
              <p class="font-semibold text-custom-grey">Store Name</p>
              <div class="group/errorState flex flex-col gap-2" :class="{ invalid: errors?.name }">
                <label class="group relative">
                  <div class="input-icon">
                    <img
                      src="@/assets/images/icons/shop-grey.svg"
                      class="flex size-6 shrink-0"
                      alt="icon"
                    />
                  </div>
                  <p class="input-placeholder">Store Name</p>
                  <input
                    v-model="form.name"
                    type="text"
                    class="custom-input"
                    placeholder=""
                    required
                  />
                </label>
                <span v-if="errors.name" class="input-error">{{ errors.name[0] }}</span>
              </div>
            </div>

            <!-- Phone -->
            <div class="flex flex-col gap-3">
              <p class="font-semibold text-custom-grey">Store Phone Number</p>
              <div class="group/errorState flex flex-col gap-2" :class="{ invalid: errors?.phone }">
                <label class="group relative">
                  <div class="input-icon">
                    <img
                      src="@/assets/images/icons/call-grey.svg"
                      class="flex size-6 shrink-0"
                      alt="icon"
                    />
                  </div>
                  <p class="input-placeholder">Ex: 0812345678</p>
                  <input
                    v-model="form.phone"
                    type="tel"
                    class="custom-input"
                    placeholder=""
                    @input="form.phone = form.phone.replace(/[^0-9]/g, '').slice(0, 15)"
                  />
                </label>
                <span v-if="errors.phone" class="input-error">{{ errors.phone[0] }}</span>
                <span v-else-if="form.phone && !form.phone.startsWith('08')" class="input-error"
                  >Phone number must start with 08</span
                >
              </div>
            </div>

            <!-- Address Search (RajaOngkir) -->
            <div class="flex flex-col gap-3">
              <p class="font-semibold text-custom-grey">Search Location (Kecamatan/Kota)</p>
              <div class="group/errorState flex flex-col gap-2 relative">
                <label class="group relative">
                  <div class="input-icon">
                    <img
                      src="@/assets/images/icons/global-search-grey.svg"
                      class="flex size-6 shrink-0"
                      alt="icon"
                    />
                  </div>
                  <p class="input-placeholder">Type district or city...</p>
                  <input
                    v-model="addressSearch"
                    type="text"
                    class="custom-input"
                    placeholder=""
                    @input="handleAddressInput(addressSearch)"
                  />
                </label>

                <!-- Dropdown Results -->
                <ul
                  v-if="showAddressOptions"
                  class="absolute top-full mt-2 w-full bg-white border border-custom-stroke rounded-xl shadow-xl max-h-[300px] overflow-y-auto z-50"
                >
                  <li v-if="loadingAddress" class="p-4 text-center text-custom-grey font-medium">
                    Loading...
                  </li>
                  <li
                    v-for="option in addressOptions"
                    v-else
                    :key="option.id"
                    class="p-4 hover:bg-custom-background cursor-pointer text-base font-medium transition-colors border-b border-custom-stroke last:border-0 leading-relaxed text-custom-black"
                    @click="handleAddressSelect(option)"
                  >
                    {{ option.label }}
                  </li>
                  <li
                    v-if="!loadingAddress && addressOptions.length === 0 && addressSearch"
                    class="p-4 text-center text-custom-grey font-medium"
                  >
                    No results found
                  </li>
                </ul>
              </div>
            </div>

            <!-- City & Postal Code (Grid) -->
            <div class="grid grid-cols-2 gap-4">
              <div class="flex flex-col gap-3">
                <p class="font-semibold text-custom-grey">City</p>
                <div
                  class="group/errorState flex flex-col gap-2"
                  :class="{ invalid: errors?.city }"
                >
                  <label class="group relative">
                    <div class="input-icon">
                      <img
                        src="@/assets/images/icons/location-grey.svg"
                        class="flex size-6 shrink-0"
                        alt="icon"
                      />
                    </div>
                    <p class="input-placeholder text-gray-400">Auto-filled</p>
                    <input
                      v-model="form.city"
                      type="text"
                      class="custom-input bg-gray-50 cursor-not-allowed"
                      placeholder=""
                      readonly
                    />
                  </label>
                  <span v-if="errors.city" class="input-error">{{ errors.city[0] }}</span>
                </div>
              </div>
              <div class="flex flex-col gap-3">
                <p class="font-semibold text-custom-grey">Postal Code</p>
                <div
                  class="group/errorState flex flex-col gap-2"
                  :class="{ invalid: errors?.postal_code }"
                >
                  <label class="group relative">
                    <div class="input-icon">
                      <img
                        src="@/assets/images/icons/box-grey.svg"
                        class="flex size-6 shrink-0"
                        alt="icon"
                      />
                    </div>
                    <p class="input-placeholder text-gray-400">Auto-filled</p>
                    <input
                      v-model="form.postal_code"
                      type="text"
                      class="custom-input bg-gray-50 cursor-not-allowed"
                      placeholder=""
                      readonly
                    />
                  </label>
                  <span v-if="errors.postal_code" class="input-error">{{
                    errors.postal_code[0]
                  }}</span>
                </div>
              </div>
            </div>

            <!-- Full Address -->
            <div class="flex flex-col gap-3">
              <p class="font-semibold text-custom-grey">Full Address Detail</p>
              <div
                class="group/errorState flex flex-col gap-2"
                :class="{ invalid: errors?.address }"
              >
                <label class="group relative">
                  <div class="input-icon h-fit pt-4">
                    <img
                      src="@/assets/images/icons/note-grey.svg"
                      class="flex size-6 shrink-0"
                      alt="icon"
                    />
                  </div>
                  <p class="input-placeholder pt-4">Street name, Building number, etc.</p>
                  <textarea
                    v-model="form.address"
                    class="custom-input min-h-[100px] py-4"
                    placeholder=""
                  ></textarea>
                </label>
                <span v-if="errors.address" class="input-error">{{ errors.address[0] }}</span>
              </div>
            </div>
            <!-- Mobile/Tablet Map Preview (Visible when sidebar map is hidden) -->
            <div v-if="form.city" class="flex flex-col gap-3 lg:hidden">
              <p class="font-semibold text-custom-grey">Store Location Preview</p>
              <div class="w-full h-[200px] rounded-2xl overflow-hidden border border-custom-stroke">
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

          <div class="flex flex-col gap-3 mt-4">
            <button
              type="submit"
              :disabled="isLoading"
              class="flex items-center justify-center h-14 rounded-full py-4 px-6 gap-[10px] bg-custom-black font-semibold capitalize text-white hover:bg-black/80 hover:shadow-lg transition-300 disabled:opacity-50"
            >
              <span v-if="!isLoading">Open My Store Now</span>
              <span v-else>Processing...</span>
            </button>
            <RouterLink
              :to="{ name: 'app.home' }"
              class="text-center font-medium text-custom-grey hover:text-custom-black"
            >
              Cancel
            </RouterLink>
          </div>
        </form>
      </div>
    </div>

    <!-- Right Side: Sticky Map -->
    <div class="hidden lg:block relative sticky top-0 h-screen bg-gray-50">
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
          class="absolute top-8 left-8 z-10 bg-white/95 backdrop-blur-md p-5 rounded-3xl shadow-[0_8px_30px_rgb(0,0,0,0.12)] border border-white/20 flex flex-col gap-4 min-w-[260px] max-w-[320px]"
        >
          <div class="flex items-center gap-3">
            <div
              class="w-10 h-10 rounded-full bg-custom-background flex items-center justify-center shrink-0"
            >
              <img src="@/assets/images/icons/location-grey.svg" class="w-5 h-5" alt="city" />
            </div>
            <div class="flex flex-col">
              <p class="text-[11px] font-bold text-custom-grey uppercase tracking-wider mb-0.5">
                City Location
              </p>
              <p class="font-bold text-custom-black text-lg leading-tight">{{ form.city }}</p>
            </div>
          </div>
          <div class="h-px w-full bg-slate-100"></div>
          <div class="flex items-center gap-3">
            <div
              class="w-10 h-10 rounded-full bg-custom-background flex items-center justify-center shrink-0"
            >
              <img src="@/assets/images/icons/box-grey.svg" class="w-5 h-5" alt="zip" />
            </div>
            <div class="flex flex-col">
              <p class="text-[11px] font-bold text-custom-grey uppercase tracking-wider mb-0.5">
                Postal Code
              </p>
              <p class="font-bold text-custom-black text-lg leading-tight">
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
        <div class="w-20 h-20 rounded-full bg-white flex items-center justify-center shadow-sm">
          <img
            src="@/assets/images/icons/location-grey.svg"
            class="w-10 h-10 opacity-50"
            alt="location"
          />
        </div>
        <div>
          <h3 class="font-bold text-xl text-custom-black mb-2">Locate Your Store</h3>
          <p class="font-medium text-gray-400 max-w-xs mx-auto">
            Enter your city or district in the search bar to see your store location on the map.
          </p>
        </div>
      </div>
    </div>
  </div>
</template>
