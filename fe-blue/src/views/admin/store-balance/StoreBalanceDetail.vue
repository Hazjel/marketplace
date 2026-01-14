<script setup>
import PlaceHolder from '@/assets/images/icons/gallery-grey.svg'
import { formatRupiah, formatToClientTimeZone } from '@/helpers/format';
import { useStoreBalanceStore } from '@/stores/storeBalance';
import { storeToRefs } from 'pinia';
import { onMounted, ref, computed, watch } from 'vue';
import { useRoute } from 'vue-router';
import Pagination from '@/components/admin/Pagination.vue';
import { useToast } from "vue-toastification";

const toast = useToast();
const route = useRoute()

const storeBalance = ref({})
const isShowBalance = ref(false)

const storeBalanceStore = useStoreBalanceStore()
const { loading, success, error } = storeToRefs(storeBalanceStore)
const { fetchStoreBalanceById } = storeBalanceStore

const histories = computed(() => storeBalance.value?.store_balance_histories || [])
const hasHistories = computed(() => Array.isArray(histories.value) && histories.value.length > 0)

const fetchData = async () => {
    const response = await fetchStoreBalanceById(route.params.id)

    storeBalance.value = response
}

onMounted(fetchData)

watch(success, (value) => {
    if (value) {
        toast.success(value);
        storeBalanceStore.success = null;
    }
});
watch(error, (value) => {
    if (value) {
        toast.error(value);
        storeBalanceStore.error = null;
    }
});
</script>

<template>
    <div class="flex w-full gap-5">
        <section class="flex flex-col w-full rounded-[20px] p-5 gap-5 bg-white">
            <p class="font-bold text-xl">Store Details</p>
            <div class="flex items-center gap-[14px] w-full min-w-0">
                <div class="flex size-[92px] shrink-0 rounded-full bg-custom-background overflow-hidden">
                    <img :src="storeBalance?.store?.logo" class="size-full object-cover" alt="photo">
                </div>
                <div class="flex flex-col gap-[6px] w-full overflow-hidden">
                    <p class="font-bold text-[22px] leading-tight w-full truncate">
                        {{ storeBalance?.store?.name }}
                    </p>
                    <p class="flex items-center gap-1 font-semibold text-lg text-custom-grey leading-none">
                        <img src="@/assets/images/icons/user-grey.svg" class="size-5" alt="icon">
                        {{ storeBalance?.store?.user?.name }}
                    </p>
                </div>
            </div>
            <div class="flex flex-col rounded-[20px] border border-custom-stroke p-4 gap-5">
                <div class="flex items-center gap-[10px] w-[260px]">
                    <div
                        class="flex size-14 shrink-0 rounded-full bg-custom-icon-background overflow-hidden items-center justify-center">
                        <img src="@/assets/images/icons/stickynote-black.svg" class="flex size-6 shrink-0" alt="icon">
                    </div>
                    <div class="flex flex-col gap-1">
                        <p class="font-bold text-lg leading-none">{{ storeBalance?.store?.transaction_count }}</p>
                        <p class="font-semibold text-custom-grey">Total Transactions</p>
                    </div>
                </div>
                <hr class="border-custom-stroke last:hidden">
                <div class="flex items-center gap-[10px] w-[260px]">
                    <div
                        class="flex size-14 shrink-0 rounded-full bg-custom-icon-background overflow-hidden items-center justify-center">
                        <img src="@/assets/images/icons/box-black.svg" class="flex size-6 shrink-0" alt="icon">
                    </div>
                    <div class="flex flex-col gap-1">
                        <p class="font-bold text-lg leading-none">{{ storeBalance?.store?.product_count }}</p>
                        <p class="font-semibold text-custom-grey">Total Products</p>
                    </div>
                </div>
                <hr class="border-custom-stroke last:hidden">
            </div>
        </section>
        <div class="flex flex-col gap-5 w-[470px] shrink-0">
            <div class="relative w-full rounded-[20px] bg-custom-green overflow-hidden">
                <img src="@/assets/images/backgrounds/round-ornament.svg"
                    class="size-full object-contain object-right opacity-55 absolute" alt="icon">
                <div class="relative flex items-center min-h-[68px] gap-[10px] p-4">
                    <img src="@/assets/images/icons/shield-tick-white-fill.svg" class="flex size-9 shrink-0"
                        alt="icon">
                    <p class="font-bold text-lg text-white">Your funds are safe and well protected</p>
                </div>
            </div>
            <div class="flex relative w-[470px] h-[304px] shrink-0 overflow-hidden">
                <img src="@/assets/images/backgrounds/wallet.png" class="size-full object-contain" alt="wallet">
                <div
                    class="flex flex-col items-center justify-center gap-2 text-center min-w-0 w-full px-4 absolute transform -translate-x-1/2 left-1/2 top-[51px]">
                    <p class="font-medium text-[#BFC6E9] leading-none">Seller Balance:</p>
                    <p class="w-full font-extrabold text-[40px] text-white leading-none">
                        <span v-if="isShowBalance">Rp {{ formatRupiah(storeBalance.balance) }}</span>
                        <span v-else>Rp xxx.xxx.xxx</span>
                    </p>
                </div>
                <button @click="isShowBalance = false"
                    class="flex items-center justify-center rounded-full border border-white/[0.03] bg-white/[0.06] py-3 px-4 gap-2 absolute transform -translate-x-1/2 left-1/2 bottom-[42px]" v-if="isShowBalance">
                    <img id="eyeIcon" src="@/assets/images/icons/eye-slash-white.svg" class="flex size-5 shrink-0"
                        alt="icon">
                    <p id="toggleText" class="font-medium text-white">Hide Balance</p>
                </button>
                <button @click="isShowBalance = true"
                    class="flex items-center justify-center rounded-full border border-white/[0.03] bg-white/[0.06] py-3 px-4 gap-2 absolute transform -translate-x-1/2 left-1/2 bottom-[42px]" v-else>
                    <img id="eyeIcon" src="@/assets/images/icons/eye-white.svg" class="flex size-5 shrink-0"
                        alt="icon">
                    <p id="toggleText" class="font-medium text-white">Hide Balance</p>
                </button>
            </div>
        </div>
    </div>
    <div class="flex flex-col flex-1 rounded-[20px] p-5 gap-6 bg-white">
        <div class="header flex items-center justify-between">
            <div class="flex flex-col gap-2">
                <p class="font-bold text-xl">Wallet History Transaction</p>
            </div>
        </div>
        <section v-if="hasHistories" id="List-Withdrawal" class="flex flex-col flex-1 gap-6 w-full">
            <div class="list flex flex-col gap-5">
                <div class="card flex rounded-[20px] border border-custom-stroke p-4 gap-5 justify-between bg-white" v-for="history in histories" :key="history.id">
                    <div class="flex items-center gap-[14px] flex-1">
                        <div
                            class="flex size-[72px] rounded-2xl overflow-hidden items-center justify-center p-5 bg-custom-orange/10">
                            <img src="@/assets/images/icons/card-send-orange-fill.svg" class="size-full object-contain"
                                alt="icon">
                        </div>
                        <div class="flex flex-col gap-[6px]">
                            <p class="font-bold text-[22px] text-custom-orange">Rp {{ formatRupiah(history.amount) }}</p>
                            <p class="font-semibold text-custom-grey leading-none">{{ history.type }}</p>
                        </div>
                    </div>
                    <div class="flex flex-1 items-center">
                        <p
                            class="badge rounded-full py-3 px-[18px] flex shrink-0 font-bold uppercase bg-custom-orange/10 text-custom-orange">
                            {{ history.remarks }}
                        </p>
                    </div>
                </div>
            </div>
        </section>
        <div v-else id="Empty-State" class="flex flex-col flex-1 items-center justify-center gap-4">
            <img src="@/assets/images/icons/note-remove-grey.svg" class="size-[52px]" alt="icon">
            <div class="flex flex-col gap-1 items-center text-center">
                <p class="font-semibold text-custom-grey">Oops, you don't have any data yet</p>
            </div>
        </div>
    </div>
</template>