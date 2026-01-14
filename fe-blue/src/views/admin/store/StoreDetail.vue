<script setup>
import { useStoreStore } from '@/stores/store';
import { storeToRefs } from 'pinia';
import { onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import { useToast } from "vue-toastification";

const toast = useToast();
const route = useRoute()

const store = ref({})

const storeStore = useStoreStore()
const { loading, success, error } = storeToRefs(storeStore)
const { fetchStoreById, approveStore } = storeStore

const fetchData = async () => {
    const response = await fetchStoreById(route.params.id)

    store.value = response
}

async function handleApprovedStore () {
    await approveStore(route.params.id)

    fetchData()
}

onMounted(fetchData)

watch(success, (value) => {
    if (value) {
        toast.success(value);
        storeStore.success = null;
    }
});
watch(error, (value) => {
    if (value) {
        toast.error(value);
        storeStore.error = null;
    }
});
</script>

<template>

    <div class="flex gap-5">
        <div class="flex flex-col gap-5 w-full animate-fade-in-up">
            <!-- Temporary container -->
            <section class="flex flex-col w-full h-fit rounded-[20px] p-5 gap-5 bg-white" v-if="store.is_verified">
                <p class="font-bold text-xl">Store Details</p>
                <div class="flex items-center w-full gap-5">
                    <div class="flex items-center gap-[14px] w-full min-w-0">
                        <div class="flex size-[84px] shrink-0 rounded-full bg-custom-background overflow-hidden">
                            <img :src="store.logo" class="size-full object-cover"
                                alt="photo" />
                        </div>
                        <div class="flex flex-col gap-[6px] w-full overflow-hidden">
                            <p class="font-bold text-lg leading-tight w-full truncate">
                                {{ store.name }}
                            </p>
                            <p class="flex items-center gap-1 font-semibold text-custom-grey leading-none">
                                <img src="@/assets/images/icons/user-grey.svg" class="size-5" alt="icon" />
                                {{ store.user?.name }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center w-fit shrink-0 gap-1">
                        <img src="@/assets/images/icons/verify-star.svg" class="flex size-6 shrink-0" alt="icon" />
                        <p class="font-bold text-custom-blue text-nowrap">VERIFIED</p>
                    </div>
                </div>
                <hr class="border-custom-stroke" />
                <div class="flex flex-col gap-2">
                    <p class="font-bold text-xl">Store About</p>
                    <p class="font-semibold text-custom-grey whitespace-pre-wrap">{{ store.about }}</p>
                </div>
                <div class="flex flex-col rounded-[20px] border border-custom-stroke p-4 gap-5">
                    <div class="flex items-center gap-[10px] w-[260px]">
                        <div
                            class="flex size-14 shrink-0 rounded-full bg-custom-icon-background overflow-hidden items-center justify-center">
                            <img src="@/assets/images/icons/stickynote-black.svg" class="flex size-6 shrink-0"
                                alt="icon" />
                        </div>
                        <div class="flex flex-col gap-1">
                            <p class="font-bold text-lg leading-none">{{ store.transaction_count }}</p>
                            <p class="font-semibold text-custom-grey">Total Transaction</p>
                        </div>
                    </div>
                    <hr class="border-custom-stroke last:hidden" />
                    <div class="flex items-center gap-[10px] w-[260px]">
                        <div
                            class="flex size-14 shrink-0 rounded-full bg-custom-icon-background overflow-hidden items-center justify-center">
                            <img src="@/assets/images/icons/shopping-cart-black.svg" class="flex size-6 shrink-0"
                                alt="icon" />
                        </div>
                        <div class="flex flex-col gap-1">
                            <p class="font-bold text-lg leading-none">{{ store.product_count }}</p>
                            <p class="font-semibold text-custom-grey">Total Products</p>
                        </div>
                    </div>
                    <hr class="border-custom-stroke last:hidden" />
                    <div class="flex items-center gap-[10px] w-[260px]">
                        <div
                            class="flex size-14 shrink-0 rounded-full bg-custom-icon-background overflow-hidden items-center justify-center">
                            <img src="@/assets/images/icons/shop-add-black.svg" class="flex size-6 shrink-0"
                                alt="icon" />
                        </div>
                        <div class="flex flex-col gap-1">
                            <p class="font-bold text-lg leading-none">Open For Shop</p>
                            <p class="font-semibold text-custom-grey">Store Status</p>
                        </div>
                    </div>
                    <hr class="border-custom-stroke last:hidden" />
                </div>
                <div class="flex flex-col gap-3">
                    <div class="flex items-center gap-[14px]">
                        <button
                            class="flex items-center justify-center size-14 shrink-0 rounded-2xl p-4 gap-2 bg-custom-red/10">
                            <img src="@/assets/images/icons/trash-red.svg" class="flex size-6 shrink-0" alt="icon" />
                        </button>
                        <RouterLink :to="{ name: 'app.store-detail', params: {username: store.username}}"
                            class="flex items-center justify-center h-14 w-full rounded-2xl p-4 gap-2 bg-custom-blue">
                            <span class="font-semibold text-white">Visit Store Page</span>
                            <img src="@/assets/images/icons/arrow-circle-right-white.svg" class="flex size-6 shrink-0"
                                alt="icon" />
                        </RouterLink>
                    </div>
                    <p class="flex items-center gap-2 font-semibold text-custom-grey leading-none">
                        <img src="@/assets/images/icons/calendar-2-grey.svg" class="size-6 flex shrink-0" alt="icon" />
                        Created on 19/02/2020
                    </p>
                </div>
            </section>
            <section class="flex flex-col w-full h-fit rounded-[20px] p-5 gap-5 bg-white" v-if="!store.is_verified">
                <p class="font-bold text-xl">Store Details</p>
                <div class="flex items-center w-full gap-5">
                    <div class="flex items-center gap-[14px] w-full min-w-0">
                        <div class="flex size-[84px] shrink-0 rounded-full bg-custom-background overflow-hidden">
                            <img :src="store.logo" class="size-full object-cover"
                                alt="photo" />
                        </div>
                        <div class="flex flex-col gap-[6px] w-full overflow-hidden">
                            <p class="font-bold text-lg leading-tight w-full truncate">
                                {{ store.name }}
                            </p>
                            <p class="flex items-center gap-1 font-semibold text-custom-grey leading-none">
                                <img src="@/assets/images/icons/user-grey.svg" class="size-5" alt="icon" />
                                {{ store.user?.name }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center w-fit shrink-0 gap-1">
                        <img src="@/assets/images/icons/information.svg" class="flex size-6 shrink-0" alt="icon" />
                        <p class="font-bold text-custom-red text-nowrap">NOT ACTIVE</p>
                    </div>
                </div>
                <hr class="border-custom-stroke" />
                <div class="flex flex-col gap-2">
                    <p class="font-bold text-xl">Store About</p>
                    <p class="font-semibold text-custom-grey whitespace-pre-wrap">{{ store.about }}</p>
                </div>
                <div class="flex flex-col gap-3">
                    <div class="flex items-center gap-[14px]">
                        <a @click="handleApprovedStore"
                            class="flex items-center justify-center h-14 w-full rounded-2xl p-4 gap-2 bg-custom-blue">
                            <span class="font-semibold text-white">Approve Store</span>
                            <img src="@/assets/images/icons/tick-circle-white.svg" class="flex size-6 shrink-0"
                                alt="icon" />
                        </a>
                    </div>
                    <p class="flex items-center gap-2 font-semibold text-custom-grey leading-none">
                        <img src="@/assets/images/icons/calendar-2-grey.svg" class="size-6 flex shrink-0" alt="icon" />
                        Created on 19/02/2020
                    </p>
                </div>
            </section>
        </div>
        <section class="flex flex-col w-full h-fit rounded-[20px] p-5 gap-5 bg-white animate-fade-in-up delay-100">
            <p class="font-bold text-xl">Store Address</p>
            <div class="flex flex-col rounded-[20px] border border-custom-stroke p-4 gap-5">
                <div class="flex items-center gap-[10px] w-[260px]">
                    <div
                        class="flex size-14 shrink-0 rounded-full bg-custom-icon-background overflow-hidden items-center justify-center">
                        <img src="@/assets/images/icons/buildings-black.svg" class="flex size-6 shrink-0" alt="icon" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <p class="font-bold text-lg leading-none">{{ store.city }}</p>
                        <p class="font-semibold text-custom-grey">City Location</p>
                    </div>
                </div>
                <hr class="border-custom-stroke last:hidden" />
                <div class="flex items-center gap-[10px] w-[260px]">
                    <div
                        class="flex size-14 shrink-0 rounded-full bg-custom-icon-background overflow-hidden items-center justify-center">
                        <img src="@/assets/images/icons/keyboard-black.svg" class="flex size-6 shrink-0" alt="icon" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <p class="font-bold text-lg leading-none">{{ store.postal_code }}</p>
                        <p class="font-semibold text-custom-grey">Post Code</p>
                    </div>
                </div>
            </div>
            <div class="flex flex-col gap-3">
                <div class="w-full h-[290px] overflow-hidden rounded-3xl resize-none">
                    <div id="g-mapdisplay" class="size-full">
                        <iframe class="size-full border-none" frameborder="0"
                            src="https://www.google.com/maps/embed/v1/place?q=Malang&key=AIzaSyBFw0Qbyq9zTFTd-tUY6dZWTgaQzuU17R8">
                        </iframe>
                    </div>
                </div>
                <p class="font-semibold text-lg leading-[1.6em]">
                    {{ store.address }}
                </p>
            </div>
        </section>
    </div>
</template>
