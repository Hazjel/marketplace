<script setup>
import { useStoreBalanceStore } from '@/stores/storeBalance'
import { storeToRefs } from 'pinia'
import { ref, onMounted, watch } from 'vue'
import { formatRupiah, parseRupiah } from '@/helpers/format'
import { useWithdrawalStore } from '@/stores/withdrawal'

const storeBalance = ref({})

const withdrawal = ref({
  store_balance_id: null,
  amount: null,
  bank_account_name: null,
  bank_account_number: null,
  bank_name: null
})

const storeBalanceStore = useStoreBalanceStore()
const { loading } = storeToRefs(storeBalanceStore)
const { fetchStoreBalanceByStore } = storeBalanceStore

const withdrawalStore = useWithdrawalStore()
const { loading: loadingWithdrawal, error, success } = storeToRefs(withdrawalStore)
const { createWithdrawal } = withdrawalStore

const fetchStoreBalance = async () => {
  const response = await fetchStoreBalanceByStore()

  storeBalance.value = response
  withdrawal.value.store_balance_id = response.id
}

import { useToast } from 'vue-toastification'

const toast = useToast()

const handleSubmit = async () => {
  const amount = parseRupiah(withdrawal.value.amount)
  if (amount > storeBalance.value.balance) {
    toast.error('Saldo tersedia tidak mencukupi. Dana yang masih ditahan (escrow) belum bisa ditarik.')
    return
  }

  if (amount <= 0) {
    toast.error('Jumlah penarikan harus lebih dari 0')
    return
  }

  await createWithdrawal({
    ...withdrawal.value,
    amount: amount
  })

  if (success.value) {
    toast.success('Permintaan penarikan berhasil dibuat')
    // Redirect or reset form handled by parent/router usually, but good to have toast.
  }
}

watch(
  () => withdrawal.value.amount,
  (newAmount) => {
    if (typeof newAmount === 'string' && newAmount.includes('-')) {
      newAmount = newAmount.replace(/-/g, '')
    }
    withdrawal.value.amount = formatRupiah(newAmount)
  }
)

onMounted(fetchStoreBalance)
</script>

<template>
  <div class="flex flex-col gap-6">
    <!-- Page Header -->
    <div class="rounded-2xl bg-gradient-to-r from-green-600 to-emerald-600 p-6 shadow-sm">
      <div class="flex items-center gap-4">
        <div class="flex size-12 items-center justify-center rounded-xl bg-white/20">
          <svg xmlns="http://www.w3.org/2000/svg" class="size-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
          </svg>
        </div>
        <div>
          <h1 class="text-2xl font-bold text-white">Ajukan Penarikan</h1>
          <p class="text-green-100">Buat permintaan penarikan dana baru</p>
        </div>
      </div>
    </div>

    <!-- Store & Balance Summary -->
    <div class="flex flex-col md:flex-row items-center rounded-2xl border border-gray-100 dark:border-white/10 bg-white dark:bg-surface-card shadow-sm p-5 gap-5">
      <div class="flex items-center gap-4 w-full min-w-0">
        <div class="flex size-[72px] shrink-0 rounded-full bg-gray-100 dark:bg-white/5 overflow-hidden ring-4 ring-green-100 dark:ring-green-900/30">
          <img :src="storeBalance?.store?.logo" class="size-full object-cover" alt="photo" />
        </div>
        <div class="flex flex-col gap-1 w-full overflow-hidden">
          <p class="font-bold text-lg leading-tight w-full truncate dark:text-white">
            {{ storeBalance?.store?.name }}
          </p>
          <p class="flex items-center gap-1.5 text-sm font-medium text-gray-500 dark:text-gray-400">
            <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
            </svg>
            {{ storeBalance?.store?.user?.name }}
          </p>
        </div>
      </div>
      <div class="hidden md:block w-px h-14 bg-gray-200 dark:bg-white/10 shrink-0"></div>
      <div class="flex flex-col w-full items-center justify-center gap-1.5">
        <p class="font-bold text-2xl md:text-3xl text-blue-600 dark:text-blue-400">
          Rp {{ formatRupiah(storeBalance?.balance) }}
        </p>
        <div class="flex items-center gap-1.5">
          <svg xmlns="http://www.w3.org/2000/svg" class="size-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a2.25 2.25 0 0 0-2.25-2.25H15a3 3 0 1 1-6 0H5.25A2.25 2.25 0 0 0 3 12m18 0v6a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 18v-6m18 0V9M3 12V9m18 0a2.25 2.25 0 0 0-2.25-2.25H5.25A2.25 2.25 0 0 0 3 9m18 0V6a2.25 2.25 0 0 0-2.25-2.25H5.25A2.25 2.25 0 0 0 3 6v3" />
          </svg>
          <p class="font-medium text-sm text-gray-500 dark:text-gray-400">Saldo Tersedia</p>
        </div>
      </div>
    </div>

    <!-- Pending Balance Warning -->
    <div
      v-if="storeBalance?.pending_balance > 0"
      class="flex items-center rounded-2xl bg-amber-50 dark:bg-amber-900/10 border border-amber-200 dark:border-amber-500/20 p-4 gap-3">
      <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-amber-100 dark:bg-amber-900/30">
        <svg xmlns="http://www.w3.org/2000/svg" class="size-5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
        </svg>
      </div>
      <div class="flex flex-col gap-0.5">
        <p class="font-semibold text-sm text-amber-700 dark:text-amber-400">
          Rp {{ formatRupiah(storeBalance?.pending_balance) }} sedang ditahan (escrow)
        </p>
        <p class="text-xs text-amber-600/80 dark:text-amber-400/70">
          Dana ini akan tersedia setelah buyer konfirmasi terima pesanan atau auto-complete 7 hari.
        </p>
      </div>
    </div>

    <!-- Form -->
    <form class="flex flex-col w-full rounded-2xl border border-gray-100 dark:border-white/10 bg-white dark:bg-surface-card shadow-sm p-6 gap-6" @submit.prevent="handleSubmit">
      <div class="flex items-center gap-3">
        <div class="flex size-10 items-center justify-center rounded-lg bg-green-100 dark:bg-green-900/30">
          <svg xmlns="http://www.w3.org/2000/svg" class="size-5 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
          </svg>
        </div>
        <h2 class="font-bold text-lg dark:text-white">Formulir Penarikan</h2>
      </div>

      <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-3">
        <p class="font-medium text-sm text-gray-600 dark:text-gray-300">Jumlah Penarikan</p>
        <div class="group/errorState flex flex-col gap-2 w-full md:w-1/2" :class="{ invalid: error?.amount }">
          <label class="group relative">
            <div class="input-icon">
              <svg xmlns="http://www.w3.org/2000/svg" class="size-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" />
              </svg>
            </div>
            <p class="input-placeholder">Masukkan Jumlah</p>
            <input v-model="withdrawal.amount" type="text" class="custom-input" placeholder="" />
          </label>
          <span v-if="error?.amount" class="input-error">{{ error?.amount?.join(', ') }}</span>
        </div>
      </div>

      <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-3">
        <p class="font-medium text-sm text-gray-600 dark:text-gray-300">Nama Bank</p>
        <div class="group/errorState flex flex-col gap-2 w-full md:w-1/2" :class="{ invalid: error?.bank_name }">
          <label class="group relative">
            <div class="input-icon">
              <svg xmlns="http://www.w3.org/2000/svg" class="size-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0 0 12 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75Z" />
              </svg>
            </div>
            <p class="input-placeholder">Pilih Bank</p>
            <select v-model="withdrawal.bank_name" class="custom-input">
              <option :value="null" disabled>Pilih Bank</option>
              <option value="bri">BRI</option>
              <option value="bca">BCA</option>
              <option value="mandiri">MANDIRI</option>
              <option value="bni">BNI</option>
            </select>
          </label>
          <span v-if="error?.bank_name" class="input-error">{{ error?.bank_name?.join(', ') }}</span>
        </div>
      </div>

      <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-3">
        <p class="font-medium text-sm text-gray-600 dark:text-gray-300">Nama Pemilik Rekening</p>
        <div class="group/errorState flex flex-col gap-2 w-full md:w-1/2" :class="{ invalid: error?.bank_account_name }">
          <label class="group relative">
            <div class="input-icon">
              <svg xmlns="http://www.w3.org/2000/svg" class="size-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
              </svg>
            </div>
            <p class="input-placeholder">Masukkan Nama Pemilik</p>
            <input v-model="withdrawal.bank_account_name" type="text" class="custom-input" placeholder="" />
          </label>
          <span v-if="error?.bank_account_name" class="input-error">{{ error?.bank_account_name?.join(', ') }}</span>
        </div>
      </div>

      <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-3">
        <p class="font-medium text-sm text-gray-600 dark:text-gray-300">Nomor Rekening</p>
        <div class="group/errorState flex flex-col gap-2 w-full md:w-1/2" :class="{ invalid: error?.bank_account_number }">
          <label class="group relative">
            <div class="input-icon">
              <svg xmlns="http://www.w3.org/2000/svg" class="size-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
              </svg>
            </div>
            <p class="input-placeholder">Masukkan Nomor Rekening</p>
            <input v-model="withdrawal.bank_account_number" type="text" inputmode="numeric" class="custom-input" placeholder="" />
          </label>
          <span v-if="error?.bank_account_number" class="input-error">{{ error?.bank_account_number?.join(', ') }}</span>
        </div>
      </div>

      <div class="flex items-center justify-end gap-3 pt-2">
        <RouterLink
          :to="{ name: 'admin.my-store-balance' }"
          class="flex items-center justify-center h-11 rounded-xl py-2.5 px-5 gap-2 bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 font-semibold text-sm border border-red-200 dark:border-red-700/50 hover:bg-red-100 dark:hover:bg-red-900/30 transition-colors duration-200">
          Batal
        </RouterLink>
        <button
          type="submit"
          :disabled="loadingWithdrawal"
          class="flex items-center justify-center h-11 rounded-xl py-2.5 px-5 gap-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold text-sm disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200">
          <svg v-if="loadingWithdrawal" class="animate-spin size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
          </svg>
          {{ loadingWithdrawal ? 'Memproses...' : 'Ajukan Sekarang' }}
        </button>
      </div>
    </form>
  </div>
</template>
