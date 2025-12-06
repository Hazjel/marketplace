<script setup>
import { useStoreBalanceStore } from '@/stores/storeBalance';
import { useWithdrawalStore } from '@/stores/withdrawal';
import { storeToRefs } from 'pinia';
import { onMounted, computed } from 'vue';
import { ref } from 'vue';
import { RouterLink } from 'vue-router';
import { formatRupiah } from '@/helpers/format';
import Pagination from '@/components/admin/Pagination.vue';


const storeBalance = ref({})

const storeBalanceStore = useStoreBalanceStore()
const { loading } = storeToRefs(storeBalanceStore)
const { fetchStoreBalanceByStore } = storeBalanceStore

const withdrawalStore = useWithdrawalStore()
const { withdrawals, meta, loading: loadingWithdrawal } = storeToRefs(withdrawalStore)
const { fetchWithdrawalsPaginated } = withdrawalStore

// Tambahkan serverOptions
const serverOptions = ref({
    page: 1,
    row_per_page: 10
})

// Computed untuk cek apakah ada data
const hasWithdrawals = computed(() => {
    return withdrawals.value && Array.isArray(withdrawals.value) && withdrawals.value.length > 0
})

// Computed untuk empty state
const showEmptyState = computed(() => {
    return !loadingWithdrawal.value && !hasWithdrawals.value
})

// Computed untuk total withdrawal
const totalWithdrawals = computed(() => {
    return withdrawals.value?.length || 0
})

// Computed untuk completed withdrawals
const completedWithdrawals = computed(() => {
    return Array.isArray(withdrawals.value) 
        ? withdrawals.value.filter((w) => w.status === 'completed').length 
        : 0
})

// Computed untuk pending withdrawals
const pendingWithdrawals = computed(() => {
    return Array.isArray(withdrawals.value) 
        ? withdrawals.value.filter((w) => w.status === 'pending').length 
        : 0
})

const fetchStoreBalance = async () => {
    const response = await fetchStoreBalanceByStore()
    storeBalance.value = response

    await fetchWithdrawalsPaginated(serverOptions.value)
}

onMounted(fetchStoreBalance)
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
                        <img src="@/assets/images/icons/card-tick-black.svg" class="flex size-6 shrink-0"
                            alt="icon">
                    </div>
                    <div class="flex flex-col gap-1">
                        <p class="font-bold text-lg leading-none">{{ completedWithdrawals }}</p>
                        <p class="font-semibold text-custom-grey">Completed Withdrawals</p>
                    </div>
                </div>
                <hr class="border-custom-stroke last:hidden">
                <div class="flex items-center gap-[10px] w-[260px]">
                    <div
                        class="flex size-14 shrink-0 rounded-full bg-custom-icon-background overflow-hidden items-center justify-center">
                        <img src="@/assets/images/icons/card-tick-black.svg" class="flex size-6 shrink-0"
                            alt="icon">
                    </div>
                    <div class="flex flex-col gap-1">
                        <p class="font-bold text-lg leading-none">{{ pendingWithdrawals }}</p>
                        <p class="font-semibold text-custom-grey">Waiting Withdrawals</p>
                    </div>
                </div>
                <hr class="border-custom-stroke last:hidden">
            </div>
        </section>
        <div class="flex flex-col gap-5 w-[470px] shrink-0">
            <div class="relative w-full rounded-[20px] bg-custom-black overflow-hidden">
                <img src="@/assets/images/backgrounds/round-ornament.svg"
                    class="size-full object-contain object-right opacity-55 absolute" alt="icon">
                <div class="relative flex items-center min-h-[68px] gap-[10px] p-4">
                    <img src="@/assets/images/icons/shield-tick-white-fill.svg"
                        class="flex size-9 shrink-0" alt="icon">
                    <p class="font-bold text-lg text-white">Your funds are safe and well protected</p>
                </div>
            </div>
            <div class="flex relative w-[470px] h-[304px] shrink-0 overflow-hidden">
                <img src="@/assets/images/backgrounds/wallet.png" class="size-full object-contain"
                    alt="wallet">
                <div
                    class="flex flex-col items-center justify-center gap-2 text-center min-w-0 w-full px-4 absolute transform -translate-x-1/2 left-1/2 top-[51px]">
                    <p class="font-medium text-[#BFC6E9] leading-none">Seller Balance:</p>
                    <p class="w-full font-extrabold text-[40px] text-white leading-none">
                        Rp <span id="balanceText">{{ storeBalance.balance }}</span>
                    </p>
                </div>
                <button id="toggleBalance"
                    class="flex items-center justify-center rounded-full border border-white/[0.03] bg-white/[0.06] py-3 px-4 gap-2 absolute transform -translate-x-1/2 left-1/2 bottom-[42px]">
                    <img id="eyeIcon" src="@/assets/images/icons/eye-slash-white.svg"
                        class="flex size-5 shrink-0" alt="icon">
                    <p id="toggleText" class="font-medium text-white">Hide Balance</p>
                </button>
            </div>
        </div>
    </div>
    
    <div class="flex flex-col flex-1 rounded-[20px] p-5 gap-6 bg-white">
        <div class="header flex items-center justify-between">
            <div class="flex flex-col gap-2">
                <p class="font-bold text-xl">All Withdrawals</p>
                <div class="flex items-center gap-1">
                    <img src="@/assets/images/icons/note-grey.svg" class="flex size-6 shrink-0" alt="icon">
                    <p class="font-semibold text-custom-grey">{{ totalWithdrawals }} Total Withdrawal</p>
                </div>
            </div>
            <a href="create-withdrawals.html"
                class="flex h-14 items-center rounded-full py-4 px-6 bg-custom-blue gap-[6px]">
                <span class="font-semibold text-lg text-white leading-none">Request Withdraw</span>
                <img src="@/assets/images/icons/add-circle-white.svg" class="flex size-6 shrink-0"
                    alt="icon">
            </a>
        </div>
        
        <!-- Tampilkan list jika ada data -->
        <section v-if="hasWithdrawals" id="List-Withdrawal" class="flex flex-col flex-1 gap-6 w-full">
            <div class="list flex flex-col gap-5">
                <div
                    class="card flex rounded-[20px] border border-custom-stroke p-4 gap-5 justify-between bg-white" 
                    v-for="withdrawal in withdrawals" 
                    :key="withdrawal.id">
                    <div class="flex items-center gap-[14px] flex-1">
                        <div
                            :class="[
                                'flex size-[72px] rounded-2xl overflow-hidden items-center justify-center p-5',
                                withdrawal.status === 'completed' ? 'bg-custom-green/10' : 'bg-custom-orange/10'
                            ]">
                            <img 
                                :src="withdrawal.status === 'completed' 
                                    ? require('@/assets/images/icons/card-tick-green-fill.svg') 
                                    : require('@/assets/images/icons/card-send-orange-fill.svg')"
                                class="size-full object-contain"
                                alt="icon">
                                class="size-full object-contain" 
                                alt="icon">
                        </div>
                        <div class="flex flex-col gap-[6px]">
                            <p class="font-bold text-[22px] text-custom-orange">Rp {{ formatRupiah(withdrawal.amount) }}</p>
                            <p class="font-semibold text-custom-grey leading-none">Withdrawal</p>
                        </div>
                    </div>
                    <div class="flex flex-1 items-center">
                        <p
                            :class="[
                                'badge rounded-full py-3 px-[18px] flex shrink-0 font-bold uppercase',
                                withdrawal.status === 'completed' 
                                    ? 'bg-custom-green/10 text-custom-green' 
                                    : 'bg-custom-orange/10 text-custom-orange'
                            ]">
                            {{ withdrawal.status }}
                        </p>
                    </div>
                    <a href="withdrawal-details.html"
                        class="flex items-center justify-center h-14 w-[126px] shrink-0 rounded-2xl p-4 gap-2 bg-custom-blue">
                        <img src="@/assets/images/icons/eye-white.svg" class="flex size-6 shrink-0"
                            alt="icon">
                        <span class="font-semibold text-white">Details</span>
                    </a>
                </div>
            </div>
            <Pagination :meta="meta" :server-options="serverOptions"/>
        </section>
        
        <!-- Tampilkan empty state jika tidak ada data -->
        <div v-if="showEmptyState" id="Empty-State" class="flex flex-col flex-1 items-center justify-center gap-4">
            <img src="@/assets/images/icons/note-remove-grey.svg" class="size-[52px]" alt="icon">
            <div class="flex flex-col gap-1 items-center text-center">
                <p class="font-semibold text-custom-grey">Oops, you don't have any data yet</p>
            </div>
        </div>
    </div>
</template>