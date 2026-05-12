<script setup>
import PlaceHolder from '@/assets/images/icons/gallery-grey.svg'
import { formatRupiah, formatToClientTimeZone } from '@/helpers/format'
import { useWithdrawalStore } from '@/stores/withdrawal'
import { storeToRefs } from 'pinia'
import { onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useToast } from 'vue-toastification'

const route = useRoute()
const toast = useToast()

const authStore = useAuthStore()
const { user } = storeToRefs(authStore)

const withdrawal = ref({})
const isConfirmed = ref(false)
const fileInput = ref(null)

const triggerFileInput = () => {
  fileInput.value.click()
}

const withdrawalStore = useWithdrawalStore()
const { loading } = storeToRefs(withdrawalStore)
const { fetchWithdrawalById, approveWithdrawal } = withdrawalStore

const fetchData = async () => {
  const response = await fetchWithdrawalById(route.params.id)

  withdrawal.value = response
  withdrawal.value.proof_url = PlaceHolder
}

const handleAprroveWithdrawal = async () => {
  if (!withdrawal.value.proof) {
    toast.error('Silakan unggah bukti pembayaran terlebih dahulu')
    return
  }
  await approveWithdrawal(withdrawal.value)
  toast.success('Penarikan berhasil disetujui')

  fetchData()
}

const handleImageChange = (e) => {
  const file = e.target.files[0]

  withdrawal.value.proof = file
  withdrawal.value.proof_url = URL.createObjectURL(file)
}

onMounted(fetchData)
</script>

<template>
  <div class="flex flex-col gap-6">
    <!-- Page Header -->
    <div class="rounded-2xl bg-gradient-to-r from-green-600 to-emerald-600 p-6 shadow-sm">
      <div class="flex items-center gap-4">
        <div class="flex size-12 items-center justify-center rounded-xl bg-white/20">
          <svg xmlns="http://www.w3.org/2000/svg" class="size-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
          </svg>
        </div>
        <div>
          <h1 class="text-2xl font-bold text-white">Detail Penarikan</h1>
          <p class="text-green-100">Informasi lengkap permintaan penarikan dana</p>
        </div>
      </div>
    </div>

    <!-- Status Banner -->
    <div
      v-if="withdrawal?.status === 'pending'"
      class="flex items-center gap-3 rounded-2xl bg-amber-50 dark:bg-amber-900/10 border border-amber-200 dark:border-amber-500/20 p-4">
      <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-amber-100 dark:bg-amber-900/30">
        <svg xmlns="http://www.w3.org/2000/svg" class="size-5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
        </svg>
      </div>
      <div>
        <p class="font-semibold text-amber-700 dark:text-amber-400">Status: Menunggu Proses</p>
        <p class="text-sm text-amber-600/80 dark:text-amber-400/70">Penarikan ini masih menunggu persetujuan admin</p>
      </div>
    </div>
    <div
      v-if="withdrawal?.status === 'approved'"
      class="flex items-center gap-3 rounded-2xl bg-green-50 dark:bg-green-900/10 border border-green-200 dark:border-green-500/20 p-4">
      <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-green-100 dark:bg-green-900/30">
        <svg xmlns="http://www.w3.org/2000/svg" class="size-5 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
        </svg>
      </div>
      <div>
        <p class="font-semibold text-green-700 dark:text-green-400">Status: Selesai</p>
        <p class="text-sm text-green-600/80 dark:text-green-400/70">Penarikan telah diproses dan diselesaikan</p>
      </div>
    </div>

    <!-- Main Content -->
    <div class="flex flex-col xl:flex-row w-full gap-6">
      <!-- Left Column -->
      <div class="flex flex-col w-full gap-6">
        <!-- Store Details -->
        <section class="flex flex-col w-full rounded-2xl border border-gray-100 dark:border-white/10 p-6 gap-5 bg-white dark:bg-surface-card shadow-sm">
          <p class="font-bold text-lg dark:text-white">Detail Toko</p>
          <div class="flex items-center gap-4 w-full min-w-0">
            <div class="flex size-[72px] shrink-0 rounded-full bg-gray-100 dark:bg-white/5 overflow-hidden ring-4 ring-green-100 dark:ring-green-900/30">
              <img :src="withdrawal?.store_balance?.store?.logo" class="size-full object-cover" alt="photo" />
            </div>
            <div class="flex flex-col gap-1.5 w-full overflow-hidden">
              <p class="font-bold text-xl leading-tight w-full truncate dark:text-white">
                {{ withdrawal?.store_balance?.store?.name }}
              </p>
              <p class="flex items-center gap-1.5 font-medium text-gray-500 dark:text-gray-400">
                <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                </svg>
                {{ withdrawal?.store_balance?.store?.user?.name }}
              </p>
            </div>
          </div>
          <!-- Stats -->
          <div class="grid grid-cols-2 gap-4 mt-2">
            <div class="flex items-center gap-3 rounded-xl bg-gray-50 dark:bg-white/5 p-4">
              <div class="flex size-10 shrink-0 rounded-lg bg-green-100 dark:bg-green-900/30 items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="size-5 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
                </svg>
              </div>
              <div class="flex flex-col">
                <p class="font-bold text-lg dark:text-white">{{ withdrawal?.store_balance?.store?.transaction_count }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">Transaksi</p>
              </div>
            </div>
            <div class="flex items-center gap-3 rounded-xl bg-gray-50 dark:bg-white/5 p-4">
              <div class="flex size-10 shrink-0 rounded-lg bg-blue-100 dark:bg-blue-900/30 items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="size-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                </svg>
              </div>
              <div class="flex flex-col">
                <p class="font-bold text-lg dark:text-white">{{ withdrawal?.store_balance?.store?.product_count }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">Produk</p>
              </div>
            </div>
          </div>
        </section>

        <!-- Approve Form (Admin only, pending) -->
        <form
          v-if="user.role === 'admin' && withdrawal.status === 'pending'"
          class="flex flex-col w-full rounded-2xl border border-gray-100 dark:border-white/10 p-6 gap-5 bg-white dark:bg-surface-card shadow-sm"
          @submit.prevent="handleAprroveWithdrawal">
          <p class="font-bold text-lg dark:text-white">Bukti Pembayaran</p>
          <div class="flex items-center justify-between w-full">
            <div class="group relative flex size-[100px] rounded-2xl overflow-hidden items-center justify-center bg-gray-100 dark:bg-white/5 border-2 border-dashed border-gray-300 dark:border-white/20">
              <img id="Thumbnail" :src="withdrawal.proof_url" class="size-full object-cover" alt="icon" />
              <input
                id="File-Input"
                ref="fileInput"
                type="file"
                accept="image/*"
                required
                class="hidden"
                @change="handleImageChange" />
            </div>
            <button
              id="Add-Photo"
              type="button"
              class="flex items-center justify-center gap-2 rounded-xl py-3 px-5 bg-gray-900 dark:bg-white/10 text-white font-semibold text-sm hover:bg-gray-800 transition-colors duration-200"
              @click="triggerFileInput">
              <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 0 3Z" />
              </svg>
              Unggah Foto
            </button>
          </div>
          <label class="group flex items-center gap-2.5 cursor-pointer">
            <input
              id="Mark-Complete"
              v-model="isConfirmed"
              type="checkbox"
              required
              class="size-5 appearance-none rounded-md border-2 border-gray-300 checked:border-blue-600 checked:bg-blue-600 transition-all duration-200 relative
                     checked:after:content-[''] checked:after:absolute checked:after:left-[5px] checked:after:top-[2px] checked:after:w-[6px] checked:after:h-[10px] checked:after:border-white checked:after:border-r-2 checked:after:border-b-2 checked:after:rotate-45" />
            <span class="font-medium text-sm text-gray-600 dark:text-gray-300 group-has-[:checked]:text-blue-600 dark:group-has-[:checked]:text-blue-400 transition-colors duration-200">
              Tandai penarikan ini sebagai selesai
            </span>
          </label>
          <button
            id="Process-Withdrawals"
            type="submit"
            class="h-12 w-full rounded-xl flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-200 dark:disabled:bg-white/10 disabled:cursor-not-allowed transition-all duration-200"
            :disabled="!isConfirmed">
            <svg xmlns="http://www.w3.org/2000/svg" class="size-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
            <span class="font-semibold text-sm text-white">Proses Penarikan</span>
          </button>
        </form>

        <!-- Proof Display (approved) -->
        <section
          v-if="withdrawal.status === 'approved'"
          class="flex flex-col w-full rounded-2xl border border-gray-100 dark:border-white/10 p-6 gap-5 bg-white dark:bg-surface-card shadow-sm">
          <p class="font-bold text-lg dark:text-white">Bukti Pembayaran</p>
          <div class="relative h-[256px] w-full rounded-2xl overflow-hidden bg-gray-100 dark:bg-white/5">
            <img :src="withdrawal.proof" class="relative size-full object-cover" alt="proof" />
            <div class="absolute bottom-0 w-full h-[95px] bg-gradient-to-t from-black/50 to-transparent">
              <button
                type="button"
                class="relative flex items-center w-fit h-9 rounded-full py-2 px-3 gap-2 bg-white mx-auto mt-8 hover:bg-gray-100 transition-colors duration-200">
                <svg xmlns="http://www.w3.org/2000/svg" class="size-4 text-gray-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15" />
                </svg>
                <span class="font-bold text-xs text-gray-700">PREVIEW</span>
              </button>
            </div>
          </div>
        </section>
      </div>

      <!-- Right Column: Amount & Info -->
      <div class="flex flex-col w-full xl:w-[450px] shrink-0 gap-6">
        <!-- Amount Card -->
        <section class="flex flex-col w-full rounded-2xl border border-gray-100 dark:border-white/10 p-6 gap-5 bg-white dark:bg-surface-card shadow-sm">
          <div class="flex flex-col items-center justify-center py-6 gap-2">
            <p class="font-bold text-3xl text-blue-600 dark:text-blue-400">
              Rp {{ formatRupiah(withdrawal?.amount) }}
            </p>
            <div class="flex items-center gap-2">
              <svg xmlns="http://www.w3.org/2000/svg" class="size-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" />
              </svg>
              <p class="font-medium text-gray-500 dark:text-gray-400">Total Penarikan</p>
            </div>
          </div>

          <!-- Info List -->
          <div class="flex flex-col gap-4 rounded-xl bg-gray-50 dark:bg-white/5 p-4">
            <div class="flex items-center gap-3">
              <div class="flex size-10 shrink-0 rounded-lg bg-blue-100 dark:bg-blue-900/30 items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="size-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                </svg>
              </div>
              <div class="flex flex-col gap-0.5">
                <p class="font-semibold text-sm dark:text-white">{{ formatToClientTimeZone(withdrawal.created_at) }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">Tanggal Permintaan</p>
              </div>
            </div>
            <hr class="border-gray-200 dark:border-white/10" />
            <div class="flex items-center gap-3">
              <div class="flex size-10 shrink-0 rounded-lg bg-purple-100 dark:bg-purple-900/30 items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="size-5 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                </svg>
              </div>
              <div class="flex flex-col gap-0.5">
                <p class="font-semibold text-sm dark:text-white">{{ withdrawal.bank_account_name }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">Nama Rekening</p>
              </div>
            </div>
            <hr class="border-gray-200 dark:border-white/10" />
            <div class="flex items-center gap-3">
              <div class="flex size-10 shrink-0 rounded-lg bg-green-100 dark:bg-green-900/30 items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="size-5 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0 0 12 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75Z" />
                </svg>
              </div>
              <div class="flex flex-col gap-0.5">
                <p class="font-semibold text-sm dark:text-white">{{ withdrawal.bank_name }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">Nama Bank</p>
              </div>
            </div>
            <hr class="border-gray-200 dark:border-white/10" />
            <div class="flex items-center gap-3">
              <div class="flex size-10 shrink-0 rounded-lg bg-amber-100 dark:bg-amber-900/30 items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="size-5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
                </svg>
              </div>
              <div class="flex flex-col gap-0.5">
                <p class="font-semibold text-sm dark:text-white">{{ withdrawal.bank_account_number }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">Nomor Rekening</p>
              </div>
            </div>
          </div>
        </section>
      </div>
    </div>
  </div>
</template>
