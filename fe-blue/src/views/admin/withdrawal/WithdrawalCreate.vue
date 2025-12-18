<script setup>
    import { useStoreBalanceStore } from '@/stores/storeBalance';
    import { storeToRefs } from 'pinia';
    import { ref, onMounted, watch } from 'vue';
    import { formatRupiah, parseRupiah } from '@/helpers/format';
    import { useWithdrawalStore } from '@/stores/withdrawal';

    const storeBalance =  ref({})

    const withdrawal = ref({
        store_balance_id: null,
        amount: null,
        bank_account_name: null,
        bank_account_number: null,
        bank_name: null,
    })

    const storeBalanceStore = useStoreBalanceStore()
    const { loading } = storeToRefs(storeBalanceStore)
    const { fetchStoreBalanceByStore } = storeBalanceStore

    const withdrawalStore = useWithdrawalStore()
    const { loading: loadingWithdrawal, error } = storeToRefs(withdrawalStore)
    const { createWithdrawal } = withdrawalStore

    const fetchStoreBalance = async () => {
        const response = await fetchStoreBalanceByStore()

        storeBalance.value = response
        withdrawal.value.store_balance_id = response.id
    }

    const handleSubmit = async () => {
        await createWithdrawal({
            ...withdrawal.value,
            amount: parseRupiah(withdrawal.value.amount)
        })
    }

    watch(() => withdrawal.value.amount, (newAmount) => {
        if (typeof newAmount === 'string' && newAmount.includes('-')) {
            newAmount = newAmount.replace(/-/g, '')
        }
        withdrawal.value.amount = formatRupiah(newAmount)
    })

onMounted(fetchStoreBalance)
</script>

<template>
    <div class="flex items-center h-[132px] rounded-[20px] p-5 gap-5 bg-white">
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
                        <div class="flex shrink-0 h-[52px] min-w-0 border border-custom-stroke"></div>
                        <div class="flex flex-col w-full items-center justify-center gap-[6px]">
                            <p class="font-bold text-[32px] text-custom-blue">Rp {{ formatRupiah(storeBalance?.balance) }}</p>
                            <div class="flex items-center gap-1">
                                <img src="@/assets/images/icons/wallet-3-grey.svg" class="size-5" alt="icon">
                                <p class="font-semibold text-custom-grey leading-none">My Balance</p>
                            </div>
                        </div>
                    </div>
                    <form @submit.prevent="handleSubmit" class="flex flex-col w-full rounded-3xl p-5 gap-5 bg-white">
                        <h2 class="font-bold text-xl capitalize">Complete the form</h2>
                        <div class="flex items-center justify-between">
                            <p class="font-semibold text-custom-grey">Amount Total</p>
                            <div class="group/errorState flex flex-col gap-2 w-1/2" :class="{ 'invalid': error?.amount}">
                                <label class="group relative">
                                    <div class="input-icon">
                                        <img src="@/assets/images/icons/money-grey.svg" class="flex size-6 shrink-0" alt="icon">
                                    </div>
                                    <p class="input-placeholder">
                                        Enter Total Amount
                                    </p>
                                    <input type="text" class="custom-input" placeholder="" v-model="withdrawal.amount">
                                </label>
                                <span class="input-error" v-if="error?.amount">{{ error?.amount?.join(', ') }}</span>
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <p class="font-semibold text-custom-grey">Enter Bank Name</p>
                            <div class="group/errorState flex flex-col gap-2 w-1/2" :class="{ 'invalid': error?.bank_name}">
                                <label class="group relative">
                                    <div class="input-icon">
                                        <img src="@/assets/images/icons/profile-grey.svg" class="flex size-6 shrink-0" alt="icon">
                                    </div>
                                    <p class="input-placeholder">
                                        Your Bank Name
                                    </p>
                                    <select v-model="withdrawal.bank_name" class="custom-input">
                                        <option :value="null" disabled>Select Bank</option>
                                        <option value="bri">BRI</option>
                                        <option value="bca">BCA</option>
                                        <option value="mandiri">MANDIRI</option>
                                        <option value="bni">BNI</option>
                                    </select>
                                </label>
                                <span class="input-error" v-if="error?.bank_name">{{ error?.bank_name?.join(', ') }}</span>
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <p class="font-semibold text-custom-grey">Your Bank Account Name</p>
                            <div class="group/errorState flex flex-col gap-2 w-1/2" :class="{ 'invalid': error?.bank_account_name}">
                                <label class="group relative">
                                    <div class="input-icon">
                                        <img src="@/assets/images/icons/bank-grey.svg" class="flex size-6 shrink-0" alt="icon">
                                    </div>
                                    <p class="input-placeholder">
                                        Enter Bank Account Name
                                    </p>
                                    <input type="text" class="custom-input" placeholder="" v-model="withdrawal.bank_account_name">
                                </label>
                                <span class="input-error" v-if="error?.bank_account_name">{{ error?.bank_account_name?.join(', ') }}</span>
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <p class="font-semibold text-custom-grey">Your Bank Account Number</p>
                            <div class="group/errorState flex flex-col gap-2 w-1/2" :class="{ 'invalid': error?.bank_account_number}">
                                <label class="group relative">
                                    <div class="input-icon">
                                        <img src="@/assets/images/icons/card-receive-grey.svg" class="flex size-6 shrink-0" alt="icon">
                                    </div>
                                    <p class="input-placeholder">
                                        Enter Bank Account Number
                                    </p>
                                    <input type="text" inputmode="numeric" class="custom-input" placeholder="" v-model="withdrawal.bank_account_number">
                                </label>
                                <span class="input-error" v-if="error?.bank_account_number">{{ error?.bank_account_number?.join(', ') }}</span>
                            </div>
                        </div>
                        <div class="flex items-center justify-end gap-4">
                            <RouterLink :to="{ name: 'admin.my-store-balance' }" class="flex items-center justify-center h-14 rounded-full py-4 px-6 gap-2 bg-custom-red text-white font-semibold text-lg">
                                Cancel
                            </RouterLink>
                            <button type="submit" class="flex items-center justify-center h-14 rounded-full py-4 px-6 gap-2 bg-custom-blue text-white font-semibold text-lg">
                                Request Now
                            </button>
                        </div>
                    </form>
</template>