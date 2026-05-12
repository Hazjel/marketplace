<script setup>
import PlaceHolder from '@/assets/images/icons/gallery-grey.svg'
import { formatRupiah, formatToClientTimeZone } from '@/helpers/format'
import { useStoreBalanceStore } from '@/stores/storeBalance'
import { storeToRefs } from 'pinia'
import { onMounted, ref, computed, watch } from 'vue'
import { useRoute } from 'vue-router'
import Pagination from '@/components/admin/Pagination.vue'
import { useToast } from 'vue-toastification'

const toast = useToast()
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
    toast.success(value)
    storeBalanceStore.success = null
  }
})
watch(error, (value) => {
  if (value) {
    toast.error(value)
    storeBalanceStore.error = null
  }
})
</script>

<template>
  <div class="flex flex-col gap-6">
    <!-- Page Header -->
    <div class="rounded-2xl bg-gradient-to-r from-green-600 to-emerald-600 p-6 shadow-sm">
      <div class="flex items-center gap-4">
        <div class="flex size-12 items-center justify-center rounded-xl bg-white/20">
          <svg xmlns="http://www.w3.org/2000/svg" class="size-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a2.25 2.25 0 0 0-2.25-2.25H15a3 3 0 1 1-6 0H5.25A2.25 2.25 0 0 0 3 12m18 0v6a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 18v-6m18 0V9M3 12V9m18 0a2.25 2.25 0 0 0-2.25-2.25H5.25A2.25 2.25 0 0 0 3 9m18 0V6a2.25 2.25 0 0 0-2.25-2.25H5.25A2.25 2.25 0 0 0 3 6v3" />
          </svg>
        </div>
        <div>
          <h1 class="text-2xl font-bold text-white">Detail Saldo Toko</h1>
          <p class="text-green-100">Informasi lengkap saldo dan riwayat transaksi</p>
        </div>
      </div>
    </div>

    <!-- Main Content -->
    <div class="flex flex-col xl:flex-row w-full gap-6">
      <!-- Store Info -->
      <section class="flex flex-col w-full rounded-2xl border border-gray-100 dark:border-white/10 p-6 gap-5 bg-white dark:bg-surface-card shadow-sm">
        <p class="font-bold text-lg dark:text-white">Detail Toko</p>
        <div class="flex items-center gap-4 w-full min-w-0">
          <div class="flex size-[80px] shrink-0 rounded-full bg-gray-100 dark:bg-white/5 overflow-hidden ring-4 ring-green-100 dark:ring-green-900/30">
            <img :src="storeBalance?.store?.logo" class="size-full object-cover" alt="photo" />
          </div>
          <div class="flex flex-col gap-1.5 w-full overflow-hidden">
            <p class="font-bold text-xl leading-tight w-full truncate dark:text-white">
              {{ storeBalance?.store?.name }}
            </p>
            <p class="flex items-center gap-1.5 font-medium text-gray-500 dark:text-gray-400">
              <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
              </svg>
              {{ storeBalance?.store?.user?.name }}
            </p>
          </div>
        </div>
        <!-- Stats -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-2">
          <div class="flex items-center gap-3 rounded-xl bg-gray-50 dark:bg-white/5 p-4">
            <div class="flex size-11 shrink-0 rounded-lg bg-green-100 dark:bg-green-900/30 items-center justify-center">
              <svg xmlns="http://www.w3.org/2000/svg" class="size-5 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
              </svg>
            </div>
            <div class="flex flex-col">
              <p class="font-bold text-lg dark:text-white">{{ storeBalance?.store?.transaction_count }}</p>
              <p class="text-sm text-gray-500 dark:text-gray-400">Total Transaksi</p>
            </div>
          </div>
          <div class="flex items-center gap-3 rounded-xl bg-gray-50 dark:bg-white/5 p-4">
            <div class="flex size-11 shrink-0 rounded-lg bg-blue-100 dark:bg-blue-900/30 items-center justify-center">
              <svg xmlns="http://www.w3.org/2000/svg" class="size-5 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
              </svg>
            </div>
            <div class="flex flex-col">
              <p class="font-bold text-lg dark:text-white">{{ storeBalance?.store?.product_count }}</p>
              <p class="text-sm text-gray-500 dark:text-gray-400">Total Produk</p>
            </div>
          </div>
        </div>
      </section>

      <!-- Balance Card -->
      <div class="flex flex-col gap-5 w-full xl:w-[470px] shrink-0">
        <!-- Security Badge -->
        <div class="relative w-full rounded-2xl bg-gray-900 dark:bg-white/10 dark:border dark:border-white/10 overflow-hidden">
          <div class="absolute inset-0 opacity-10">
            <div class="absolute -right-10 -top-10 size-40 rounded-full bg-green-500"></div>
            <div class="absolute -right-5 -bottom-5 size-24 rounded-full bg-emerald-500"></div>
          </div>
          <div class="relative flex items-center gap-3 p-4">
            <svg xmlns="http://www.w3.org/2000/svg" class="size-8 text-green-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
            </svg>
            <p class="font-semibold text-white">Dana aman dan terlindungi</p>
          </div>
        </div>

        <!-- Wallet Balance -->
        <div class="flex relative w-full h-auto aspect-[470/304] bg-custom-blue rounded-[30px] shrink-0 overflow-hidden">
          <img src="@/assets/images/backgrounds/wallet.png" class="size-full object-cover" alt="wallet" />
          <div class="flex flex-col items-center justify-center gap-2 text-center min-w-0 w-full px-4 absolute transform -translate-x-1/2 left-1/2 top-[15%] sm:top-[51px]">
            <p class="font-medium text-[#BFC6E9] leading-none">Saldo Tersedia:</p>
            <p class="w-full font-extrabold text-3xl sm:text-[40px] text-white leading-none break-all">
              <span v-if="isShowBalance">Rp {{ formatRupiah(storeBalance.balance) }}</span>
              <span v-else>Rp **********</span>
            </p>
          </div>
          <button
            v-if="isShowBalance"
            class="flex items-center justify-center rounded-full border border-white/10 bg-white/10 backdrop-blur-sm py-3 px-4 gap-2 absolute transform -translate-x-1/2 left-1/2 bottom-[15%] sm:bottom-[42px] hover:bg-white/20 transition-all duration-200"
            @click="isShowBalance = false">
            <svg xmlns="http://www.w3.org/2000/svg" class="size-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
            </svg>
            <p class="font-medium text-white text-sm">Sembunyikan</p>
          </button>
          <button
            v-else
            class="flex items-center justify-center rounded-full border border-white/10 bg-white/10 backdrop-blur-sm py-3 px-4 gap-2 absolute transform -translate-x-1/2 left-1/2 bottom-[15%] sm:bottom-[42px] hover:bg-white/20 transition-all duration-200"
            @click="isShowBalance = true">
            <svg xmlns="http://www.w3.org/2000/svg" class="size-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
            </svg>
            <p class="font-medium text-white text-sm">Tampilkan</p>
          </button>
        </div>

        <!-- Pending Balance -->
        <div
          v-if="storeBalance?.pending_balance > 0"
          class="rounded-2xl bg-amber-50 dark:bg-amber-900/10 border border-amber-200 dark:border-amber-500/20 p-4">
          <div class="flex items-center gap-3">
            <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-amber-100 dark:bg-amber-900/30">
              <svg xmlns="http://www.w3.org/2000/svg" class="size-5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
              </svg>
            </div>
            <div class="flex flex-col gap-0.5">
              <p class="font-bold text-amber-700 dark:text-amber-400">
                <span v-if="isShowBalance">Rp {{ formatRupiah(storeBalance.pending_balance) }}</span>
                <span v-else>Rp ***</span>
                <span class="font-medium text-sm ml-2">ditahan (escrow)</span>
              </p>
              <p class="text-sm text-amber-600/80 dark:text-amber-400/70">Akan dirilis setelah pesanan selesai</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- History Section -->
    <div class="flex flex-col flex-1 rounded-2xl border border-gray-100 dark:border-white/10 bg-white dark:bg-surface-card shadow-sm p-6 gap-6">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="flex size-10 items-center justify-center rounded-lg bg-green-100 dark:bg-green-900/30">
            <svg xmlns="http://www.w3.org/2000/svg" class="size-5 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
          </div>
          <h2 class="font-bold text-lg dark:text-white">Riwayat Transaksi Wallet</h2>
        </div>
      </div>

      <section v-if="hasHistories" id="List-Withdrawal" class="flex flex-col flex-1 gap-4 w-full">
        <div class="list flex flex-col gap-3">
          <div
            v-for="history in histories"
            :key="history.id"
            class="flex items-center rounded-2xl border border-gray-100 dark:border-white/10 p-4 gap-4 bg-white dark:bg-surface-card hover:shadow-sm transition-shadow duration-200">
            <div class="flex size-14 shrink-0 rounded-xl bg-amber-50 dark:bg-amber-900/20 items-center justify-center">
              <svg xmlns="http://www.w3.org/2000/svg" class="size-6 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" />
              </svg>
            </div>
            <div class="flex flex-col gap-1 flex-1">
              <p class="font-bold text-xl text-amber-600 dark:text-amber-400">Rp {{ formatRupiah(history.amount) }}</p>
              <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ history.type }}</p>
            </div>
            <span class="inline-flex items-center rounded-full bg-amber-50 dark:bg-amber-900/20 px-3 py-1.5 text-xs font-semibold text-amber-700 dark:text-amber-400 ring-1 ring-amber-200 dark:ring-amber-700/50 uppercase">
              {{ history.remarks }}
            </span>
          </div>
        </div>
      </section>

      <!-- Empty State -->
      <div v-else id="Empty-State" class="flex flex-col flex-1 items-center justify-center py-16 gap-4">
        <div class="flex size-20 items-center justify-center rounded-full bg-gray-100 dark:bg-white/5">
          <svg xmlns="http://www.w3.org/2000/svg" class="size-10 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
          </svg>
        </div>
        <div class="flex flex-col gap-1 items-center text-center">
          <p class="font-semibold text-gray-900 dark:text-white">Belum ada riwayat transaksi</p>
          <p class="text-sm text-gray-500 dark:text-gray-400">Riwayat transaksi wallet akan muncul di sini</p>
        </div>
      </div>
    </div>
  </div>
</template>
