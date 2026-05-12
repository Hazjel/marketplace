<script setup>
import { useStoreBalanceStore } from '@/stores/storeBalance'
import { useStoreStore } from '@/stores/store'
import { useWithdrawalStore } from '@/stores/withdrawal'
import { storeToRefs } from 'pinia'
import { onMounted, computed } from 'vue'
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import { formatRupiah } from '@/helpers/format'
import iconTickGreen from '@/assets/images/icons/card-tick-green-fill.svg'
import iconSendOrange from '@/assets/images/icons/card-send-orange-fill.svg'
import iconEyeSlash from '@/assets/images/icons/eye-slash-white.svg'
import iconEye from '@/assets/images/icons/eye-white.svg'
import Pagination from '@/components/admin/Pagination.vue'

const storeBalance = ref({})
const store = ref({})
const isBalanceHidden = ref(false)
import { useAuthStore } from '@/stores/auth'
const authStore = useAuthStore()
const { user } = storeToRefs(authStore)

const getRoute = (name, params = {}) => {
  if (user.value?.role === 'admin') {
    // Admin uses admin.* routes
    return { name: `admin.${name}`, params }
  }
  // Seller uses user.* routes with username
  return {
    name: `user.${name}`,
    params: { username: user.value?.username, ...params }
  }
}
const storeBalanceStore = useStoreBalanceStore()
const { loading } = storeToRefs(storeBalanceStore)
const { fetchStoreBalanceByStore } = storeBalanceStore

const storeStore = useStoreStore()
const { fetchStoreByUser } = storeStore

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

const toggleBalanceValue = computed(() => {
  return isBalanceHidden.value ? 'Rp **********' : `Rp ${formatRupiah(storeBalance.value?.balance)}`
})

const pendingBalanceValue = computed(() => {
  return isBalanceHidden.value ? 'Rp **********' : `Rp ${formatRupiah(storeBalance.value?.pending_balance)}`
})

const toggleBalance = () => {
  isBalanceHidden.value = !isBalanceHidden.value
}

const fetchStoreBalance = async () => {
  const response = await fetchStoreBalanceByStore()
  storeBalance.value = response || { balance: 0 }

  const storeResponse = await fetchStoreByUser()
  store.value = storeResponse

  await fetchWithdrawalsPaginated(serverOptions.value)
}

onMounted(fetchStoreBalance)
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
          <h1 class="text-2xl font-bold text-white">Saldo Toko Saya</h1>
          <p class="text-green-100">Kelola saldo dan riwayat penarikan</p>
        </div>
      </div>
    </div>

    <!-- Store Info & Balance Cards -->
    <div class="flex flex-col xl:flex-row w-full gap-6">
      <!-- Store Details -->
      <section class="flex flex-col w-full rounded-2xl border border-gray-100 dark:border-white/10 p-6 gap-5 bg-white dark:bg-surface-card shadow-sm">
        <p class="font-bold text-lg dark:text-white">Detail Toko</p>
        <div class="flex flex-col md:flex-row items-center gap-4 w-full min-w-0">
          <div class="flex size-[80px] shrink-0 rounded-full bg-gray-100 dark:bg-white/5 overflow-hidden ring-4 ring-green-100 dark:ring-green-900/30">
            <img :src="store?.logo" class="size-full object-cover" alt="photo" />
          </div>
          <div class="flex flex-col gap-1.5 w-full overflow-hidden text-center md:text-left">
            <p class="font-bold text-xl leading-tight w-full truncate dark:text-white">
              {{ store?.name }}
            </p>
            <p class="flex items-center justify-center md:justify-start gap-1.5 font-medium text-gray-500 dark:text-gray-400">
              <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
              </svg>
              {{ store?.user?.name }}
            </p>
          </div>
        </div>
        <!-- Stats -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-2">
          <div class="flex items-center gap-3 rounded-xl bg-green-50 dark:bg-green-900/10 p-4 border border-green-100 dark:border-green-800/30">
            <div class="flex size-11 shrink-0 rounded-lg bg-green-100 dark:bg-green-900/30 items-center justify-center">
              <svg xmlns="http://www.w3.org/2000/svg" class="size-5 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
              </svg>
            </div>
            <div class="flex flex-col">
              <p class="font-bold text-lg dark:text-white">{{ completedWithdrawals }}</p>
              <p class="text-sm text-gray-500 dark:text-gray-400">Penarikan Selesai</p>
            </div>
          </div>
          <div class="flex items-center gap-3 rounded-xl bg-amber-50 dark:bg-amber-900/10 p-4 border border-amber-100 dark:border-amber-800/30">
            <div class="flex size-11 shrink-0 rounded-lg bg-amber-100 dark:bg-amber-900/30 items-center justify-center">
              <svg xmlns="http://www.w3.org/2000/svg" class="size-5 text-amber-600 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
              </svg>
            </div>
            <div class="flex flex-col">
              <p class="font-bold text-lg dark:text-white">{{ pendingWithdrawals }}</p>
              <p class="text-sm text-gray-500 dark:text-gray-400">Menunggu Proses</p>
            </div>
          </div>
        </div>
      </section>

      <!-- Balance Section -->
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

        <!-- Wallet Visual -->
        <div class="flex relative w-full h-auto aspect-[470/304] bg-custom-blue rounded-[30px] shrink-0 overflow-hidden">
          <img src="@/assets/images/backgrounds/wallet.png" class="size-full object-cover" alt="wallet" />
          <div class="flex flex-col items-center justify-center gap-2 text-center min-w-0 w-full px-4 absolute transform -translate-x-1/2 left-1/2 top-[15%] sm:top-[51px]">
            <p class="font-medium text-[#BFC6E9] leading-none">Saldo Tersedia:</p>
            <p class="w-full font-extrabold text-3xl sm:text-[40px] text-white leading-none break-all">
              <span id="balanceText">{{ toggleBalanceValue }}</span>
            </p>
          </div>
          <button
            id="toggleBalance"
            class="flex items-center justify-center rounded-full border border-white/10 bg-white/10 backdrop-blur-sm py-3 px-4 gap-2 absolute transform -translate-x-1/2 left-1/2 bottom-[15%] sm:bottom-[42px] hover:bg-white/20 transition-all duration-200"
            @click="toggleBalance">
            <img id="eyeIcon" :src="isBalanceHidden ? iconEye : iconEyeSlash" class="flex size-5 shrink-0" alt="icon" />
            <p id="toggleText" class="font-medium text-white text-sm">
              {{ isBalanceHidden ? 'Tampilkan Saldo' : 'Sembunyikan' }}
            </p>
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
              <p class="font-bold text-amber-700 dark:text-amber-400">{{ pendingBalanceValue }}</p>
              <p class="text-sm text-amber-600/80 dark:text-amber-400/70">Saldo ditahan (escrow) — akan dirilis setelah buyer konfirmasi</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Withdrawals List -->
    <div class="flex flex-col flex-1 rounded-2xl border border-gray-100 dark:border-white/10 bg-white dark:bg-surface-card shadow-sm p-6 gap-6">
      <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
          <div class="flex size-10 items-center justify-center rounded-lg bg-green-100 dark:bg-green-900/30">
            <svg xmlns="http://www.w3.org/2000/svg" class="size-5 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 4.5h14.25M3 9h9.75M3 13.5h5.25m5.25-.75L17.25 9m0 0L21 12.75M17.25 9v12" />
            </svg>
          </div>
          <div>
            <h2 class="font-bold text-lg dark:text-white">Semua Penarikan</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ totalWithdrawals }} Total Penarikan</p>
          </div>
        </div>
        <RouterLink
          :to="getRoute('withdrawal.create')"
          class="flex h-11 items-center rounded-xl py-2.5 px-5 bg-blue-600 hover:bg-blue-700 transition-colors duration-200 gap-2 shadow-sm">
          <svg xmlns="http://www.w3.org/2000/svg" class="size-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
          </svg>
          <span class="font-semibold text-sm text-white">Ajukan Penarikan</span>
        </RouterLink>
      </div>

      <!-- List -->
      <section v-if="hasWithdrawals" id="List-Withdrawal" class="flex flex-col flex-1 gap-4 w-full">
        <div class="list flex flex-col gap-3">
          <div
            v-for="withdrawal in withdrawals" :key="withdrawal.id"
            class="flex flex-col md:flex-row rounded-2xl border border-gray-100 dark:border-white/10 p-4 gap-4 justify-between bg-white dark:bg-surface-card items-center hover:shadow-sm transition-shadow duration-200">
            <div class="flex items-center gap-4 w-full md:w-auto md:flex-1">
              <div
                :class="[
                  'flex size-14 shrink-0 rounded-xl items-center justify-center',
                  withdrawal.status === 'completed' ? 'bg-green-50 dark:bg-green-900/20' : 'bg-amber-50 dark:bg-amber-900/20'
                ]">
                <svg v-if="withdrawal.status === 'completed'" xmlns="http://www.w3.org/2000/svg" class="size-6 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                <svg v-else xmlns="http://www.w3.org/2000/svg" class="size-6 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" />
                </svg>
              </div>
              <div class="flex flex-col gap-1">
                <p class="font-bold text-xl text-amber-600 dark:text-amber-400">
                  Rp {{ formatRupiah(withdrawal.amount) }}
                </p>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Penarikan Dana</p>
              </div>
            </div>
            <div class="flex flex-col md:flex-row w-full md:w-auto items-center gap-3">
              <span
                :class="[
                  'inline-flex items-center rounded-full px-3 py-1.5 text-xs font-semibold uppercase ring-1',
                  withdrawal.status === 'completed'
                    ? 'bg-green-50 text-green-700 ring-green-200 dark:bg-green-900/20 dark:text-green-400 dark:ring-green-700/50'
                    : 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-900/20 dark:text-amber-400 dark:ring-amber-700/50'
                ]">
                {{ withdrawal.status }}
              </span>
              <RouterLink
                :to="getRoute('withdrawal.detail', { id: withdrawal.id })"
                class="flex items-center justify-center h-10 w-full md:w-auto px-4 rounded-xl gap-2 bg-blue-600 hover:bg-blue-700 transition-colors duration-200">
                <svg xmlns="http://www.w3.org/2000/svg" class="size-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                </svg>
                <span class="font-semibold text-sm text-white">Detail</span>
              </RouterLink>
            </div>
          </div>
        </div>
        <Pagination :meta="meta" :server-options="serverOptions" />
      </section>

      <!-- Empty State -->
      <div v-if="showEmptyState" id="Empty-State" class="flex flex-col flex-1 items-center justify-center py-16 gap-4">
        <div class="flex size-20 items-center justify-center rounded-full bg-gray-100 dark:bg-white/5">
          <svg xmlns="http://www.w3.org/2000/svg" class="size-10 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
          </svg>
        </div>
        <div class="flex flex-col gap-1 items-center text-center">
          <p class="font-semibold text-gray-900 dark:text-white">Belum ada data penarikan</p>
          <p class="text-sm text-gray-500 dark:text-gray-400">Ajukan penarikan pertama Anda</p>
        </div>
      </div>
    </div>
  </div>
</template>
