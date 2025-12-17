<script setup>
import { useStoreStore } from '@/stores/store';
import { useAuthStore } from '@/stores/auth';
import { storeToRefs } from 'pinia';
import { ref } from 'vue';
import { useRoute } from 'vue-router';
import PlaceHolder from '@/assets/images/icons/gallery-grey.svg'
import { debounce } from 'lodash';

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

const addressSearch = ref('');
const addressOptions = ref([]);
const showAddressOptions = ref(false);
const loadingAddress = ref(false);

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
        showAddressOptions.value = false;
        return;
    }

    loadingAddress.value = true;
    try {
        const response = await fetch(`/tariff/api/v1/destination/search?keyword=${encodeURIComponent(search)}`, {
            headers: {
                'x-api-key': import.meta.env.VITE_RAJAONGKIR_API_KEY
            }
        });

        const data = await response.json();
        
        if (data.data) {
            addressOptions.value = data.data;
            showAddressOptions.value = true;
        }

    } catch (err) {
        console.error('Error fetching address:', err);
        addressOptions.value = [];
    } finally {
        loadingAddress.value = false;
    }
}, 500);

const handleAddressSelect = (selected) => {    store.value.address_id = selected.id;
    store.value.city = selected.city_name;
    store.value.address = selected.label;
    store.value.postal_code = selected.zip_code;
    addressSearch.value = selected.label;
    showAddressOptions.value = false;
};
</script>

<template>
    <form @submit.prevent="handleSubmit" class="flex flex-col w-full rounded-3xl p-5 gap-5 bg-white">
        <h2 class="font-bold text-xl capitalize">Complete the form</h2>
        <div class="flex items-center justify-between">
            <p class="font-semibold text-custom-grey">Store Image</p>
            <div class="flex items-center justify-between w-1/2">
                <div
                    class="group relative flex size-[100px] rounded-2xl overflow-hidden items-center justify-center bg-custom-background">
                    <img id="Thumbnail" :src="store.logo_url" data-default="@/assets/images/icons/gallery-default.svg"
                        class="size-full object-contain" alt="icon" />
                    <input type="file" id="File-Input" accept="image/*"
                        class="absolute inset-0 opacity-0 cursor-pointer" @change="handleImageChange" />
                </div>
                <button type="button" id="Add-Photo"
                    class="flex items-center justify-center rounded-2xl py-4 px-6 bg-custom-black text-white font-semibold text-lg">
                    Add Photo
                </button>
            </div>
        </div>
        <div class="flex items-center justify-between">
            <p class="font-semibold text-custom-grey">Store Name</p>
            <div class="group/errorState flex flex-col gap-2 w-1/2" :class="{ 'invalid': error?.name }">
                <label class="group relative">
                    <div class="input-icon">
                        <img src="@/assets/images/icons/shop-grey.svg" class="flex size-6 shrink-0" alt="icon">
                    </div>
                    <p class="input-placeholder">
                        Enter Store Name
                    </p>
                    <input type="text" class="custom-input" placeholder="" v-model="store.name">
                </label>
                <span class="input-error" v-if="error?.name">{{ error?.name?.join(', ') }}</span>
            </div>
        </div>
        <div class="flex items-center justify-between">
            <p class="font-semibold text-custom-grey">Store Phone</p>
            <div class="group/errorState flex flex-col gap-2 w-1/2" :class="{ 'invalid': error?.phone }">
                <label class="group relative">
                    <div class="input-icon">
                        <img src="@/assets/images/icons/shop-grey.svg" class="flex size-6 shrink-0" alt="icon">
                    </div>
                    <p class="input-placeholder">
                        Enter Store Phone
                    </p>
                    <input type="text" class="custom-input" placeholder="" v-model="store.phone">
                </label>
                <span class="input-error" v-if="error?.phone">{{ error?.phone?.join(', ') }}</span>
            </div>
        </div>
        <div class="flex justify-between">
            <p class="font-semibold text-custom-grey mt-5">Store Description</p>
            <div class="group/errorState flex flex-col gap-2 w-1/2" :class="{ 'invalid': error?.about }">
                <label
                    class="group flex py-4 px-6 rounded-3xl border-[2px] border-custom-border focus-within:border-custom-black transition-300 w-full group-[&.invalid]/errorState:border-custom-red">
                    <div class="flex h-full pr-4 pt-2 border-r-[1.5px] border-custom-border ">
                        <img src="@/assets/images/icons/stickynote-grey.svg" class="flex size-6 shrink-0" alt="icon">
                    </div>
                    <div class="flex flex-col gap-[6px] pl-4 w-full">
                        <p
                            class="placeholder font-semibold text-custom-grey text-sm group-has-[:placeholder-shown]:text-base group-has-[:placeholder-shown]:text-custom-black group-has-[:placeholder-shown]:font-bold transition-300">
                            Enter Store Description
                        </p>
                        <textarea class="appearance-none outline-none w-full font-semibold leading-[160%]" rows="3"
                            placeholder="" v-model="store.about"></textarea>
                    </div>
                </label>
                <span class="font-semibold text-lg text-custom-red leading-none group-[&.invalid]/errorState:block"
                    v-if="error?.about">{{ error?.about?.join(', ') }}</span>
            </div>
        </div>
        <div class="flex items-center justify-between">
            <p class="font-semibold text-custom-grey">Address Searching</p>
            <div class="group/errorState flex flex-col gap-2 w-1/2">
                <label class="group relative">
                    <div class="input-icon">
                        <img src="@/assets/images/icons/global-search-grey.svg" class="flex size-6 shrink-0" alt="icon">
                    </div>
                    <p class="input-placeholder">
                        Enter District
                    </p>
                    <input type="text" class="custom-input" placeholder="" v-model="addressSearch"
                        @input="handleAddressInput(addressSearch)">
                </label>
                <ul class="search-result" v-if="showAddressOptions">
                    <li v-for="option in addressOptions" :key="option.id" @click="handleAddressSelect(option)">
                        {{ option.label }}
                    </li>
                </ul>
            </div>
        </div>
        <div class="flex justify-between">
            <p class="font-semibold text-custom-grey mt-5">Store Address</p>
            <div class="group/errorState flex flex-col gap-2 w-1/2" :class="{ 'invalid': error?.address }">
                <label
                    class="group flex py-4 px-6 rounded-3xl border-[2px] border-custom-border focus-within:border-custom-black transition-300 w-full group-[&.invalid]/errorState:border-custom-red">
                    <div class="flex h-full pr-4 pt-2 border-r-[1.5px] border-custom-border ">
                        <img src="@/assets/images/icons/stickynote-grey.svg" class="flex size-6 shrink-0" alt="icon">
                    </div>
                    <div class="flex flex-col gap-[6px] pl-4 w-full">
                        <p
                            class="placeholder font-semibold text-custom-grey text-sm group-has-[:placeholder-shown]:text-base group-has-[:placeholder-shown]:text-custom-black group-has-[:placeholder-shown]:font-bold transition-300">
                            Enter Store Address
                        </p>
                        <textarea class="appearance-none outline-none w-full font-semibold leading-[160%]" rows="3"
                            placeholder="" v-model="store.address"></textarea>
                    </div>
                </label>
                <span class="font-semibold text-lg text-custom-red leading-none group-[&.invalid]/errorState:block"
                    v-if="error?.address">{{ error?.address?.join(', ') }}</span>
            </div>
        </div>
        <div class="flex items-center justify-between">
            <p class="font-semibold text-custom-grey">City</p>
            <div class="group/errorState flex flex-col gap-2 w-1/2" :class="{ 'invalid': error?.city }">
                <label class="group relative">
                    <div class="input-icon">
                        <img src="@/assets/images/icons/global-search-grey.svg" class="flex size-6 shrink-0" alt="icon">
                    </div>
                    <p class="input-placeholder">
                        Enter City
                    </p>
                    <input type="text" class="custom-input" placeholder="" v-model="store.city">
                </label>
                <span class="input-error" v-if="error?.city">{{ error?.city?.join(', ') }}</span>
            </div>
        </div>
        <div class="flex items-center justify-between">
            <p class="font-semibold text-custom-grey">Postal Code</p>
            <div class="group/errorState flex flex-col gap-2 w-1/2" :class="{ 'invalid': error?.postal_code }">
                <label class="group relative">
                    <div class="input-icon">
                        <img src="@/assets/images/icons/global-search-grey.svg" class="flex size-6 shrink-0" alt="icon">
                    </div>
                    <p class="input-placeholder">
                        Enter Postal Code
                    </p>
                    <input type="text" class="custom-input" placeholder="" v-model="store.postal_code">
                </label>
                <span class="input-error" v-if="error?.postal_code">{{ error?.postal_code?.join(', ') }}</span>
            </div>
        </div>
        <div class="flex items-center justify-end gap-4">
            <a href="my-store.html"
                class="flex items-center justify-center h-14 rounded-full py-4 px-6 gap-2 bg-custom-red text-white font-semibold text-lg">
                Cancel
            </a>
            <button type="submit"
                class="flex items-center justify-center h-14 rounded-full py-4 px-6 gap-2 bg-custom-blue text-white font-semibold text-lg">
                Create Now
            </button>
        </div>
    </form>
</template>