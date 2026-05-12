<script setup>
import { useStoreStore } from '@/stores/store'
import { useAuthStore } from '@/stores/auth'
import { storeToRefs } from 'pinia'
import { ref } from 'vue'
import { useRoute } from 'vue-router'
import PlaceHolder from '@/assets/images/icons/gallery-grey.svg'
import { debounce } from 'lodash'

const route = useRoute()

const authStore = useAuthStore()
const { user } = storeToRefs(authStore)

const storeStore = useStoreStore()
const { loading, error } = storeToRefs(storeStore)
const { createStore, fetchStoreById } = storeStore

const store = ref({
  user_id: user.value.id,
  name: null,
  logo: null,
  logo_url: PlaceHolder,
  about: null,
  phone: null,
  address_id: 0,
  city: null,
  address: null,
  postal_code: null
})

const addressSearch = ref('')
const addressOptions = ref([])
const showAddressOptions = ref(false)
const loadingAddress = ref(false)

const handleSubmit = async () => {
  await createStore(store.value)
}

const handleImageChange = (e) => {
  const file = e.target.files[0]

  store.value.logo = file
  store.value.logo_url = URL.createObjectURL(file)
}

const handleAddressInput = debounce(async (search) => {
  if (!search.trim()) {
    showAddressOptions.value = false
    return
  }

  loadingAddress.value = true
  try {
    const response = await fetch(
      `/tariff/api/v1/destination/search?keyword=${encodeURIComponent(search)}`,
      {
        headers: {
          'x-api-key': import.meta.env.VITE_RAJAONGKIR_API_KEY
        }
      }
    )

    const data = await response.json()

    if (data.data) {
      addressOptions.value = data.data
      showAddressOptions.value = true
    }
  } catch (err) {
    console.error('Error fetching address:', err)
    addressOptions.value = []
  } finally {
    loadingAddress.value = false
  }
}, 500)

const handleAddressSelect = (selected) => {
  store.value.address_id = selected.id
  store.value.city = selected.city_name
  store.value.address = selected.label
  store.value.postal_code = selected.zip_code
  addressSearch.value = selected.label
  showAddressOptions.value = false
}
</script>

<template>
  <form class="flex flex-col w-full rounded-2xl p-6 gap-5 bg-white dark:bg-surface-card dark:text-white border border-gray-100 dark:border-white/10 shadow-sm" @submit.prevent="handleSubmit">
    <!-- Page Header -->
    <div class="flex items-center gap-4 pb-4 border-b border-gray-100 dark:border-white/10">
      <div class="flex size-11 items-center justify-center rounded-xl bg-gradient-to-br from-blue-500 to-blue-600 shadow-sm">
        <svg xmlns="http://www.w3.org/2000/svg" class="size-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
        </svg>
      </div>
      <div>
        <h1 class="font-bold text-xl text-gray-900 dark:text-white">Buat Toko Baru</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">Lengkapi informasi toko Anda</p>
      </div>
    </div>

    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <p class="font-semibold text-gray-600 dark:text-gray-300">Foto Toko</p>
      <div class="flex items-center justify-between w-full md:w-1/2">
        <div
          class="group relative flex size-20 rounded-2xl overflow-hidden items-center justify-center bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-white/10"
        >
          <img
            id="Thumbnail"
            :src="store.logo_url"
            data-default="@/assets/images/icons/gallery-default.svg"
            class="size-full object-contain"
            alt="icon"
          />
          <input
            id="File-Input"
            type="file"
            accept="image/*"
            class="absolute inset-0 opacity-0 cursor-pointer"
            @change="handleImageChange"
          />
        </div>
        <button
          id="Add-Photo"
          type="button"
          class="flex items-center justify-center rounded-xl py-2.5 px-5 bg-gray-900 dark:bg-white dark:text-gray-900 text-white font-semibold text-sm hover:bg-gray-800 dark:hover:bg-gray-100 transition-colors"
        >
          Tambah Foto
        </button>
      </div>
    </div>
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <p class="font-semibold text-gray-600 dark:text-gray-300">Nama Toko</p>
      <div class="group/errorState flex flex-col gap-2 w-full md:w-1/2" :class="{ invalid: error?.name }">
        <label class="group relative">
          <div class="input-icon">
            <img
              src="@/assets/images/icons/shop-grey.svg"
              class="flex size-6 shrink-0"
              alt="icon"
            />
          </div>
          <p class="input-placeholder">Enter Store Name</p>
          <input v-model="store.name" type="text" class="custom-input" placeholder="" />
        </label>
        <span v-if="error?.name" class="input-error">{{ error?.name?.join(', ') }}</span>
      </div>
    </div>
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <p class="font-semibold text-gray-600 dark:text-gray-300">Telepon Toko</p>
      <div class="group/errorState flex flex-col gap-2 w-full md:w-1/2" :class="{ invalid: error?.phone }">
        <label class="group relative">
          <div class="input-icon">
            <img
              src="@/assets/images/icons/shop-grey.svg"
              class="flex size-6 shrink-0"
              alt="icon"
            />
          </div>
          <p class="input-placeholder">Enter Store Phone</p>
          <input v-model="store.phone" type="text" class="custom-input" placeholder="" />
        </label>
        <span v-if="error?.phone" class="input-error">{{ error?.phone?.join(', ') }}</span>
      </div>
    </div>
    <div class="flex flex-col md:flex-row justify-between gap-4">
      <p class="font-semibold text-gray-600 dark:text-gray-300 mt-2 md:mt-5">Deskripsi Toko</p>
      <div class="group/errorState flex flex-col gap-2 w-full md:w-1/2" :class="{ invalid: error?.about }">
        <label
          class="group flex py-4 px-6 rounded-3xl border-[2px] border-custom-border focus-within:border-custom-black transition-300 w-full group-[&.invalid]/errorState:border-custom-red"
        >
          <div class="flex h-full pr-4 pt-2 border-r-[1.5px] border-custom-border">
            <img
              src="@/assets/images/icons/stickynote-grey.svg"
              class="flex size-6 shrink-0"
              alt="icon"
            />
          </div>
          <div class="flex flex-col gap-[6px] pl-4 w-full">
            <p
              class="placeholder font-semibold text-custom-grey text-sm group-has-[:placeholder-shown]:text-base group-has-[:placeholder-shown]:text-custom-black group-has-[:placeholder-shown]:font-bold transition-300"
            >
              Enter Store Description
            </p>
            <textarea
              v-model="store.about"
              class="appearance-none outline-none w-full font-semibold leading-[160%]"
              rows="3"
              placeholder=""
            ></textarea>
          </div>
        </label>
        <span
          v-if="error?.about"
          class="font-semibold text-lg text-custom-red leading-none group-[&.invalid]/errorState:block"
          >{{ error?.about?.join(', ') }}</span
        >
      </div>
    </div>
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <p class="font-semibold text-gray-600 dark:text-gray-300">Pencarian Alamat</p>
      <div class="group/errorState flex flex-col gap-2 w-full md:w-1/2">
        <label class="group relative">
          <div class="input-icon">
            <img
              src="@/assets/images/icons/global-search-grey.svg"
              class="flex size-6 shrink-0"
              alt="icon"
            />
          </div>
          <p class="input-placeholder">Enter District</p>
          <input
            v-model="addressSearch"
            type="text"
            class="custom-input"
            placeholder=""
            @input="handleAddressInput(addressSearch)"
          />
        </label>
        <ul v-if="showAddressOptions" class="search-result">
          <li
            v-for="option in addressOptions"
            :key="option.id"
            @click="handleAddressSelect(option)"
          >
            {{ option.label }}
          </li>
        </ul>
      </div>
    </div>
    <div class="flex flex-col md:flex-row justify-between gap-4">
      <p class="font-semibold text-gray-600 dark:text-gray-300 mt-2 md:mt-5">Alamat Toko</p>
      <div class="group/errorState flex flex-col gap-2 w-full md:w-1/2" :class="{ invalid: error?.address }">
        <label
          class="group flex py-4 px-6 rounded-3xl border-[2px] border-custom-border focus-within:border-custom-black transition-300 w-full group-[&.invalid]/errorState:border-custom-red"
        >
          <div class="flex h-full pr-4 pt-2 border-r-[1.5px] border-custom-border">
            <img
              src="@/assets/images/icons/stickynote-grey.svg"
              class="flex size-6 shrink-0"
              alt="icon"
            />
          </div>
          <div class="flex flex-col gap-[6px] pl-4 w-full">
            <p
              class="placeholder font-semibold text-custom-grey text-sm group-has-[:placeholder-shown]:text-base group-has-[:placeholder-shown]:text-custom-black group-has-[:placeholder-shown]:font-bold transition-300"
            >
              Enter Store Address
            </p>
            <textarea
              v-model="store.address"
              class="appearance-none outline-none w-full font-semibold leading-[160%]"
              rows="3"
              placeholder=""
            ></textarea>
          </div>
        </label>
        <span
          v-if="error?.address"
          class="font-semibold text-lg text-custom-red leading-none group-[&.invalid]/errorState:block"
          >{{ error?.address?.join(', ') }}</span
        >
      </div>
    </div>
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <p class="font-semibold text-gray-600 dark:text-gray-300">Kota</p>
      <div class="group/errorState flex flex-col gap-2 w-full md:w-1/2" :class="{ invalid: error?.city }">
        <label class="group relative">
          <div class="input-icon">
            <img
              src="@/assets/images/icons/global-search-grey.svg"
              class="flex size-6 shrink-0"
              alt="icon"
            />
          </div>
          <p class="input-placeholder">Enter City</p>
          <input v-model="store.city" type="text" class="custom-input" placeholder="" />
        </label>
        <span v-if="error?.city" class="input-error">{{ error?.city?.join(', ') }}</span>
      </div>
    </div>
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <p class="font-semibold text-gray-600 dark:text-gray-300">Kode Pos</p>
      <div
        class="group/errorState flex flex-col gap-2 w-full md:w-1/2"
        :class="{ invalid: error?.postal_code }"
      >
        <label class="group relative">
          <div class="input-icon">
            <img
              src="@/assets/images/icons/global-search-grey.svg"
              class="flex size-6 shrink-0"
              alt="icon"
            />
          </div>
          <p class="input-placeholder">Enter Postal Code</p>
          <input v-model="store.postal_code" type="text" class="custom-input" placeholder="" />
        </label>
        <span v-if="error?.postal_code" class="input-error">{{
          error?.postal_code?.join(', ')
        }}</span>
      </div>
    </div>
    <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100 dark:border-white/10">
      <a
        href="my-store.html"
        class="flex items-center justify-center h-11 rounded-xl py-3 px-5 gap-2 bg-gray-100 dark:bg-white/10 text-gray-600 dark:text-white font-semibold text-sm hover:bg-gray-200 dark:hover:bg-white/20 transition-colors"
      >
        Batal
      </a>
      <button
        type="submit"
        :disabled="loading"
        class="flex items-center justify-center h-11 rounded-xl py-3 px-5 gap-2 bg-blue-600 text-white font-semibold text-sm hover:bg-blue-700 transition-colors shadow-sm disabled:opacity-50 disabled:cursor-not-allowed"
      >
        <svg v-if="loading" class="animate-spin size-4 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
        </svg>
        {{ loading ? 'Membuat...' : 'Buat Toko' }}
      </button>
    </div>
  </form>
</template>
