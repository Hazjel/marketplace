<script setup>
import { formatDate } from '@/helpers/format';
import { useStoreStore } from '@/stores/store';
import { useAuthStore } from '@/stores/auth';
import { storeToRefs } from 'pinia';
import { onMounted, ref, computed } from 'vue';
import { RouterLink } from 'vue-router';

const store = ref({})

const storeStore = useStoreStore()
const { loading } = storeToRefs(storeStore)

const authStore = useAuthStore()
const { user } = storeToRefs(authStore)

const getEditStoreRoute = () => {
    if (user.value?.role === 'admin') {
        return { name: 'admin.edit-store' }
    }
    return {
        name: 'user.edit-store',
        params: { username: user.value?.username }
    }
}
const { fetchStoreByUser } = storeStore

const fetchStore = async () => {
    const response = await fetchStoreByUser()

    store.value = response
}

onMounted(fetchStore)

const fullAddress = computed(() => {
    if (!store.value) return ''
    return [
        store.value.address, 
        store.value.city, 
        store.value.postal_code
    ].filter(Boolean).join(', ')
})

const mapSrc = computed(() => {
    const query = fullAddress.value || 'Malang'
    return `https://www.google.com/maps/embed/v1/place?q=${encodeURIComponent(query)}&key=AIzaSyBFw0Qbyq9zTFTd-tUY6dZWTgaQzuU17R8`
})
</script>

<template>
    <div class="flex flex-1 gap-5">
        <section class=" flex flex-col w-full h-fit rounded-[20px] p-5 gap-5 bg-white" v-if="store">
            <p class="font-bold text-xl">Store Details</p>
            <div class="flex items-center w-full gap-5">
                <div class="flex items-center gap-[14px] w-full min-w-0">
                    <div class="flex size-[84px] shrink-0 rounded-full bg-custom-background overflow-hidden">
                        <img :src="store?.logo" class="size-full object-cover" alt="photo">
                    </div>
                    <div class="flex flex-col gap-[6px] w-full overflow-hidden">
                        <p class="font-bold text-lg leading-tight w-full truncate">
                            {{ store.name }}
                        </p>
                        <p class="flex items-center gap-1 font-semibold text-custom-grey leading-none">
                            <img src="@/assets/images/icons/user-grey.svg" class="size-5" alt="icon">
                            {{ store.user?.name }}
                        </p>
                    </div>
                </div>
                <div class="flex items-center w-fit shrink-0 gap-1">
                    <img src="@/assets/images/icons/verify-star.svg" class="flex size-6 shrink-0" alt="icon"
                        v-if="store?.is_verified">
                    <p class="font-bold text-custom-blue text-nowrap" v-if="store?.is_verified">VERIFIED</p>
                    <p class="font-bold text-custom-blue text-nowrap" v-else>NOT VERIFIED</p>
                </div>
            </div>
            <hr class="border-custom-stroke">
            <div class="flex flex-col gap-2">
                <p class="font-bold text-xl">Store About</p>
                <p class="font-semibold text-custom-grey" v-html="store?.about"></p>
            </div>
            <div class="flex flex-col gap-3">
                <div class="flex items-center gap-[14px]">
                    <RouterLink :to="getEditStoreRoute()"
                        class="flex items-center justify-center h-14 w-full rounded-2xl p-4 gap-2 bg-custom-black">
                        <img src="@/assets/images/icons/edit-white.svg" class="flex size-6 shrink-0" alt="icon">
                        <span class="font-semibold text-white">Edit Store</span>
                    </RouterLink>
                </div>
                <p class="flex items-center gap-2 font-semibold text-custom-grey leading-none">
                    <img src="@/assets/images/icons/calendar-2-grey.svg" class="size-6 flex shrink-0" alt="icon">
                    Created on {{ formatDate(store?.created_at) }}
                </p>
            </div>
        </section>
        <section class="flex flex-col w-full h-fit rounded-[20px] p-5 gap-5 bg-white" v-if="store">
            <p class="font-bold text-xl">Store Address</p>
            <div class="flex flex-col rounded-[20px] border border-custom-stroke p-4 gap-5">
                <div class="flex items-center gap-[10px] w-[260px]">
                    <div
                        class="flex size-14 shrink-0 rounded-full bg-custom-icon-background overflow-hidden items-center justify-center">
                        <img src="@/assets/images/icons/buildings-black.svg" class="flex size-6 shrink-0" alt="icon">
                    </div>
                    <div class="flex flex-col gap-1">
                        <p class="font-bold text-lg leading-none">{{ store?.city }}</p>
                        <p class="font-semibold text-custom-grey">City Location</p>
                    </div>
                </div>
                <hr class="border-custom-stroke last:hidden">
                <div class="flex items-center gap-[10px] w-[260px]">
                    <div
                        class="flex size-14 shrink-0 rounded-full bg-custom-icon-background overflow-hidden items-center justify-center">
                        <img src="@/assets/images/icons/keyboard-black.svg" class="flex size-6 shrink-0" alt="icon">
                    </div>
                    <div class="flex flex-col gap-1">
                        <p class="font-bold text-lg leading-none">{{ store?.postal_code }} </p>
                        <p class="font-semibold text-custom-grey">Post Code</p>
                    </div>
                </div>
            </div>
            <div class="flex flex-col gap-3">
                <div class="w-full h-[290px] overflow-hidden rounded-3xl resize-none">
                    <div id="g-mapdisplay" class="size-full">
                        <iframe class="size-full border-none" frameborder="0"
                            :src="mapSrc">
                        </iframe>
                    </div>
                </div>
                <p class="font-semibold text-lg leading-[1.6em]" v-if="fullAddress">{{ fullAddress }}</p>
                <p class="font-semibold text-lg leading-[1.6em]" v-else>No address set</p>
            </div>
        </section>
        <div id="Empty-State-Store" class="flex flex-1 bg-white rounded-[20px] p-5" v-else>
            <div
                class="flex flex-col flex-1 items-center justify-center gap-4 rounded-2xl border-2 border-dashed border-custom-stroke p-5">
                <img src="@/assets/images/icons/shop-remove-grey.svg" class="size-[52px]" alt="icon">
                <div class="flex flex-col gap-1 items-center text-center">
                    <p class="font-semibold text-custom-grey">Oops, you don't have store profile</p>
                </div>
                <RouterLink :to="{ name: 'admin.create-store' }"
                    class="flex h-[52px] items-center rounded-2xl py-4 px-6 bg-custom-blue gap-[10px]">
                    <span class="font-semibold text-lg text-white leading-none">Create Now +</span>
                </RouterLink>
            </div>
        </div>
    </div>
</template>