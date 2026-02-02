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
  <div class="flex w-full gap-5">
    <div class="flex flex-col w-full gap-5">
      <div
        v-if="withdrawal?.status === 'pending'"
        class="relative w-full rounded-[20px] bg-custom-orange overflow-hidden"
      >
        <img
          src="@/assets/images/backgrounds/round-ornament.svg"
          class="size-full object-contain object-right opacity-55 absolute"
          alt="icon"
        />
        <div class="relative flex items-center min-h-[68px] gap-[10px] p-4">
          <img
            src="@/assets/images/icons/timer-white-fill.svg"
            class="flex size-9 shrink-0"
            alt="icon"
          />
          <p class="font-bold text-lg text-white">The withdrawal status is still pending</p>
        </div>
      </div>
      <div
        v-if="withdrawal?.status === 'approved'"
        class="relative w-full rounded-[20px] bg-custom-green overflow-hidden"
      >
        <img
          src="@/assets/images/backgrounds/round-ornament.svg"
          class="size-full object-contain object-right opacity-55 absolute"
          alt="icon"
        />
        <div class="relative flex items-center min-h-[68px] gap-[10px] p-4">
          <img
            src="@/assets/images/icons/tick-circle-white-fill.svg"
            class="flex size-9 shrink-0"
            alt="icon"
          />
          <p class="font-bold text-lg text-white">The withdrawal status is complete</p>
        </div>
      </div>
      <section class="flex flex-col w-full rounded-[20px] p-5 gap-5 bg-white">
        <p class="font-bold text-xl">Store Details</p>
        <div class="flex items-center gap-[14px] w-full min-w-0">
          <div class="flex size-[92px] shrink-0 rounded-full bg-custom-background overflow-hidden">
            <img
              :src="withdrawal?.store_balance?.store?.logo"
              class="size-full object-cover"
              alt="photo"
            />
          </div>
          <div class="flex flex-col gap-[6px] w-full overflow-hidden">
            <p class="font-bold text-[22px] leading-tight w-full truncate">
              {{ withdrawal?.store_balance?.store?.name }}
            </p>
            <p class="flex items-center gap-1 font-semibold text-lg text-custom-grey leading-none">
              <img src="@/assets/images/icons/user-grey.svg" class="size-5" alt="icon" />
              {{ withdrawal?.store_balance?.store?.user?.name }}
            </p>
          </div>
        </div>
        <div class="flex flex-col rounded-[20px] border border-custom-stroke p-4 gap-5">
          <div class="flex items-center gap-[10px] w-[260px]">
            <div
              class="flex size-14 shrink-0 rounded-full bg-custom-icon-background overflow-hidden items-center justify-center"
            >
              <img
                src="@/assets/images/icons/stickynote-black.svg"
                class="flex size-6 shrink-0"
                alt="icon"
              />
            </div>
            <div class="flex flex-col gap-1">
              <p class="font-bold text-lg leading-none">
                {{ withdrawal?.store_balance?.store?.transaction_count }}
              </p>
              <p class="font-semibold text-custom-grey">Total Transactions</p>
            </div>
          </div>
          <hr class="border-custom-stroke last:hidden" />
          <div class="flex items-center gap-[10px] w-[260px]">
            <div
              class="flex size-14 shrink-0 rounded-full bg-custom-icon-background overflow-hidden items-center justify-center"
            >
              <img
                src="@/assets/images/icons/box-black.svg"
                class="flex size-6 shrink-0"
                alt="icon"
              />
            </div>
            <div class="flex flex-col gap-1">
              <p class="font-bold text-lg leading-none">
                {{ withdrawal?.store_balance?.store?.product_count }}
              </p>
              <p class="font-semibold text-custom-grey">Total Products</p>
            </div>
          </div>
          <hr class="border-custom-stroke last:hidden" />
        </div>
      </section>
      <form
        v-if="user.role === 'admin' && withdrawal.status === 'pending'"
        class="flex flex-col w-full rounded-[20px] p-5 gap-5 bg-white"
        @submit.prevent="handleAprroveWithdrawal"
      >
        <p class="fld text-xl">Proof of Payment</p>
        <div class="flex items-center justify-between w-full">
          <div
            class="group relative flex size-[100px] rounded-2xl overflow-hidden items-center justify-center bg-custom-background"
          >
            <img
              id="Thumbnail"
              :src="withdrawal.proof_url"
              data-default="@/assets/images/icons/gallery-default.svg"
              class="size-full object-cover"
              alt="icon"
            />
            <input
              id="File-Input"
              ref="fileInput"
              type="file"
              accept="image/*"
              required
              class="hidden"
              @change="handleImageChange"
            />
          </div>
          <button
            id="Add-Photo"
            type="button"
            class="flex items-center justify-center rounded-2xl py-4 px-6 bg-custom-black text-white font-semibold text-lg"
            @click="triggerFileInput"
          >
            Add Photo
          </button>
        </div>
        <label class="group flex items-center gap-[7px] relative">
          <input
            id="Mark-Complete"
            v-model="isConfirmed"
            type="checkbox"
            name=""
            required
            class="size-[18px] appearance-none rounded-md checked:border-[3px] checked:border-solid checked:border-white checked:bg-custom-blue ring-2 ring-custom-grey checked:ring-custom-blue transition-300"
          />
          <span
            class="font-bold text-custom-grey leading-none group-has-[:checked]:text-custom-blue transition-300"
            >Mark this withdrawal as complete</span
          >
        </label>
        <button
          id="Process-Withdrawals"
          type="submit"
          class="h-14 w-full rounded-full flex items-center justify-center py-4 px-6 bg-custom-blue disabled:bg-custom-stroke transition-300"
          :disabled="!isConfirmed"
        >
          <span class="font-semibold text-lg text-white">Process Withdrawals</span>
        </button>
      </form>
      <section
        v-if="withdrawal.status === 'approved'"
        class="flex flex-col w-full rounded-[20px] p-5 gap-5 bg-white"
      >
        <p class="font-bold text-xl">Proof of Payment</p>
        <div class="relative h-[256px] w-full rounded-2xl overflow-hidden bg-custom-background">
          <img :src="withdrawal.proof" class="relative size-full object-cover" alt="proof" />
          <div
            class="absolute bottom-0 w-full h-[95px] bg-[linear-gradient(180deg,rgba(41,45,50,0)_0%,rgba(41,45,50,0.6)_100%)]"
          >
            <button
              type="button"
              data-modal="Preview-Modal"
              class="relative flex items-center w-fit h-10 rounded-full py-[10px] px-3 gap-[6px] bg-white mx-auto mt-[30px] mb-auto"
            >
              <img
                src="@/assets/images/icons/maximize-3-black.svg"
                class="flex size-5 shrink-0"
                alt="icon"
              />
              <span class="font-extrabold text-sm leading-none">PREVIEW</span>
            </button>
          </div>
        </div>
      </section>
    </div>
    <div class="flex flex-col w-full gap-5">
      <section class="flex flex-col w-full rounded-[20px] p-5 gap-5 bg-white">
        <div class="flex flex-col items-center justify-center h-[142px] w-full py-8 [6px]">
          <p class="font-bold text-[32px] text-custom-blue">
            Rp {{ formatRupiah(withdrawal?.amount) }}
          </p>
          <div class="flex items-center gap-[6px]">
            <img
              src="@/assets/images/icons/card-send-grey.svg"
              class="flex size-6 shrink-0"
              alt="icon"
            />
            <p class="font-semibold text-custom-grey leading-none">Total Withdrawal</p>
          </div>
        </div>
        <div class="flex flex-col rounded-[20px] border border-custom-stroke p-4 gap-5">
          <div class="flex items-center gap-[10px] w-[260px]">
            <div
              class="flex size-14 shrink-0 rounded-full bg-custom-icon-background overflow-hidden items-center justify-center"
            >
              <img
                src="@/assets/images/icons/calendar-2-black.svg"
                class="flex size-6 shrink-0"
                alt="icon"
              />
            </div>
            <div class="flex flex-col gap-1">
              <p class="font-bold text-lg leading-none">
                {{ formatToClientTimeZone(withdrawal.created_at) }}
              </p>
              <p class="font-semibold text-custom-grey">Request Date</p>
            </div>
          </div>
          <hr class="border-custom-stroke last:hidden" />
          <div class="flex items-center gap-[10px] w-[260px]">
            <div
              class="flex size-14 shrink-0 rounded-full bg-custom-icon-background overflow-hidden items-center justify-center"
            >
              <img
                src="@/assets/images/icons/profile-black.svg"
                class="flex size-6 shrink-0"
                alt="icon"
              />
            </div>
            <div class="flex flex-col gap-1">
              <p class="font-bold text-lg leading-none">{{ withdrawal.bank_account_name }}</p>
              <p class="font-semibold text-custom-grey">Bank Account Name</p>
            </div>
          </div>
          <hr class="border-custom-stroke last:hidden" />
          <div class="flex items-center gap-[10px] w-[260px]">
            <div
              class="flex size-14 shrink-0 rounded-full bg-custom-icon-background overflow-hidden items-center justify-center"
            >
              <img
                src="@/assets/images/icons/bank-black.svg"
                class="flex size-6 shrink-0"
                alt="icon"
              />
            </div>
            <div class="flex flex-col gap-1">
              <p class="font-bold text-lg leading-none">{{ withdrawal.bank_name }}</p>
              <p class="font-semibold text-custom-grey">Bank Name</p>
            </div>
          </div>
          <hr class="border-custom-stroke last:hidden" />
          <div class="flex items-center gap-[10px] w-[260px]">
            <div
              class="flex size-14 shrink-0 rounded-full bg-custom-icon-background overflow-hidden items-center justify-center"
            >
              <img
                src="@/assets/images/icons/card-tick-black.svg"
                class="flex size-6 shrink-0"
                alt="icon"
              />
            </div>
            <div class="flex flex-col gap-1">
              <p class="font-bold text-lg leading-none">{{ withdrawal.bank_account_number }}</p>
              <p class="font-semibold text-custom-grey">Bank Account Number</p>
            </div>
          </div>
          <hr class="border-custom-stroke last:hidden" />
        </div>
      </section>
    </div>
  </div>
</template>
