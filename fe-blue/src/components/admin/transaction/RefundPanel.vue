<script setup>
import { computed, ref } from 'vue'
import { useToast } from 'vue-toastification'
import { formatRupiah, formatToClientTimeZone } from '@/helpers/format'
import { useTransactionStore } from '@/stores/transaction'
import { useAuthStore } from '@/stores/auth'
import { resolveRefundStatus } from '@/composables/useTransactionStatus'

// Refund for an order the seller cancelled after payment. What Midtrans cannot
// refund goes to Saldo Blukios; older orders still wait on a manual transfer
// (buyer gives a bank account, admin records the transfer or moves it to the
// balance).
const props = defineProps({
  transaction: { type: Object, required: true },
  isBuyer: { type: Boolean, default: false },
  isAdmin: { type: Boolean, default: false }
})

const emit = defineEmits(['updated'])

const toast = useToast()
const transactionStore = useTransactionStore()
const authStore = useAuthStore()

const status = computed(() => resolveRefundStatus(props.transaction))
const account = computed(() => props.transaction.refund_account)
const awaitingManual = computed(() => props.transaction.refund_status === 'manual_required')

const editingAccount = ref(false)
const form = ref({ refund_bank_name: '', refund_account_number: '', refund_account_name: '' })
const transferNote = ref('')
const submitting = ref(false)
const balanceError = ref('')

const toBalance = computed(() => props.transaction.refund_method === 'balance')
const balanceRoute = computed(() => {
  const username = authStore.user?.username
  return props.isBuyer && username ? { name: 'user.dashboard', params: { username } } : null
})

const showAccountForm = computed(
  () => props.isBuyer && awaitingManual.value && (!account.value || editingAccount.value)
)

const description = computed(() => {
  switch (props.transaction.refund_status) {
    case 'processing':
      return 'Dana sedang dikembalikan ke metode pembayaran Anda.'
    case 'manual_required':
      return props.isBuyer
        ? 'Metode pembayaran ini tidak bisa direfund otomatis. Isi rekening tujuan, dana akan ditransfer oleh tim Blukios.'
        : 'Menunggu transfer manual ke rekening pembeli.'
    case 'refunded':
      if (toBalance.value) return 'Dana dikembalikan ke Saldo Blukios.'
      return props.transaction.refund_method === 'manual'
        ? 'Dana sudah ditransfer ke rekening pembeli.'
        : 'Dana sudah dikembalikan ke metode pembayaran. QRIS dan e-wallet bisa butuh beberapa hari sampai masuk.'
    default:
      return ''
  }
})

const startEditing = () => {
  form.value = {
    refund_bank_name: account.value?.bank_name ?? '',
    refund_account_number: account.value?.account_number ?? '',
    refund_account_name: account.value?.account_name ?? ''
  }
  editingAccount.value = true
}

const submitAccount = async () => {
  if (!/^[0-9]{5,30}$/.test(form.value.refund_account_number)) {
    toast.error('Nomor rekening hanya angka, 5-30 digit')
    return
  }

  submitting.value = true
  try {
    const updated = await transactionStore.submitRefundAccount(props.transaction.id, form.value)
    editingAccount.value = false
    toast.success('Rekening refund tersimpan')
    emit('updated', updated)
  } catch {
    toast.error(transactionStore.error || 'Gagal menyimpan rekening')
  } finally {
    submitting.value = false
  }
}

const submitTransfer = async () => {
  if (!transferNote.value.trim()) {
    toast.error('Isi catatan transfer (mis. nomor referensi bank)')
    return
  }

  submitting.value = true
  try {
    const updated = await transactionStore.markRefunded(
      props.transaction.id,
      transferNote.value.trim()
    )
    transferNote.value = ''
    toast.success('Refund ditandai selesai')
    emit('updated', updated)
  } catch {
    toast.error(transactionStore.error || 'Gagal menandai refund')
  } finally {
    submitting.value = false
  }
}

const refundToBalance = async () => {
  const amount = formatRupiah(props.transaction.refund_amount)
  let message = `Kembalikan Rp ${amount} ke Saldo Blukios pembeli?`
  if (account.value) {
    message +=
      '\n\nPastikan dana BELUM ditransfer manual ke rekening pembeli — kalau sudah, pakai Tandai Sudah Ditransfer.'
  }
  if (!confirm(message)) return

  balanceError.value = ''
  submitting.value = true
  try {
    const updated = await transactionStore.refundToBalance(props.transaction.id)
    toast.success('Dana dikembalikan ke Saldo Blukios')
    emit('updated', updated)
  } catch (error) {
    // handleError() drops the message of a non-validation 422 (wrong refund state).
    balanceError.value =
      error?.response?.data?.message ||
      transactionStore.error ||
      'Gagal mengembalikan dana ke Saldo Blukios'
  } finally {
    submitting.value = false
  }
}

const inputClass =
  'w-full h-11 px-4 rounded-xl bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-white/10 text-sm text-custom-black dark:text-white placeholder:text-gray-400 focus:outline-none focus:border-custom-blue focus:ring-2 focus:ring-custom-blue/10'
</script>

<template>
  <section
    class="flex flex-col w-full rounded-2xl p-5 gap-4 bg-white dark:bg-surface-card border border-gray-100 dark:border-white/10 shadow-sm"
  >
    <div class="flex items-center justify-between gap-3">
      <p class="font-medium text-lg dark:text-white">Pengembalian Dana</p>
      <span
        v-if="status"
        class="rounded-full px-2.5 py-1 text-xs font-medium"
        :class="status.style"
      >
        {{ status.label }}
      </span>
    </div>

    <p class="text-sm text-custom-grey dark:text-gray-400">
      {{ description }}
      <RouterLink
        v-if="toBalance && balanceRoute"
        :to="balanceRoute"
        class="font-medium text-custom-blue hover:underline"
      >
        Lihat Saldo Blukios
      </RouterLink>
    </p>

    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
      <div class="flex flex-col gap-0.5">
        <dt class="text-custom-grey dark:text-gray-400">Jumlah</dt>
        <dd class="font-medium text-custom-black dark:text-white">
          Rp {{ formatRupiah(transaction.refund_amount) }}
        </dd>
      </div>
      <div v-if="transaction.refunded_at" class="flex flex-col gap-0.5">
        <dt class="text-custom-grey dark:text-gray-400">Dikembalikan</dt>
        <dd class="font-medium text-custom-black dark:text-white">
          {{ formatToClientTimeZone(transaction.refunded_at) }}
        </dd>
      </div>
      <div v-if="transaction.refund_reason" class="flex flex-col gap-0.5 sm:col-span-2">
        <dt class="text-custom-grey dark:text-gray-400">Alasan pembatalan dari penjual</dt>
        <dd class="text-custom-black dark:text-white break-words">
          {{ transaction.refund_reason }}
        </dd>
      </div>
      <div v-if="isAdmin && transaction.refund_note" class="flex flex-col gap-0.5 sm:col-span-2">
        <dt class="text-custom-grey dark:text-gray-400">Catatan</dt>
        <dd class="text-custom-black dark:text-white break-words">{{ transaction.refund_note }}</dd>
      </div>
    </dl>

    <div
      v-if="account && !showAccountForm"
      class="flex flex-col gap-1 p-4 rounded-xl bg-gray-50 dark:bg-white/5 border border-gray-100 dark:border-white/10 text-sm"
    >
      <p class="text-custom-grey dark:text-gray-400">Rekening tujuan</p>
      <p class="font-medium text-custom-black dark:text-white">
        {{ account.bank_name }} · {{ account.account_number }}
      </p>
      <p class="text-custom-black dark:text-white">a.n. {{ account.account_name }}</p>
      <button
        v-if="isBuyer && awaitingManual"
        type="button"
        class="self-start mt-1 text-sm font-medium text-custom-blue hover:underline"
        @click="startEditing"
      >
        Ubah rekening
      </button>
    </div>

    <form v-if="showAccountForm" class="flex flex-col gap-3" @submit.prevent="submitAccount">
      <label class="flex flex-col gap-1.5">
        <span class="text-sm font-medium text-custom-black dark:text-white">Nama Bank</span>
        <input
          v-model="form.refund_bank_name"
          required
          maxlength="100"
          :class="inputClass"
          placeholder="Contoh: BCA"
        />
      </label>
      <label class="flex flex-col gap-1.5">
        <span class="text-sm font-medium text-custom-black dark:text-white">Nomor Rekening</span>
        <input
          v-model="form.refund_account_number"
          required
          inputmode="numeric"
          maxlength="30"
          :class="inputClass"
          placeholder="Hanya angka"
        />
      </label>
      <label class="flex flex-col gap-1.5">
        <span class="text-sm font-medium text-custom-black dark:text-white"
          >Nama Pemilik Rekening</span
        >
        <input v-model="form.refund_account_name" required maxlength="100" :class="inputClass" />
      </label>
      <div class="flex gap-3">
        <button
          v-if="editingAccount"
          type="button"
          class="flex-1 h-11 rounded-xl bg-gray-100 dark:bg-white/5 text-sm font-medium text-custom-grey dark:text-gray-400"
          @click="editingAccount = false"
        >
          Batal
        </button>
        <button
          type="submit"
          :disabled="submitting"
          class="flex-1 h-11 rounded-xl bg-custom-blue text-white text-sm font-medium hover:bg-blue-700 disabled:opacity-50"
        >
          Simpan Rekening
        </button>
      </div>
    </form>

    <form
      v-if="isAdmin && awaitingManual && account"
      class="flex flex-col gap-3"
      @submit.prevent="submitTransfer"
    >
      <label class="flex flex-col gap-1.5">
        <span class="text-sm font-medium text-custom-black dark:text-white">Catatan transfer</span>
        <input
          v-model="transferNote"
          required
          maxlength="255"
          :class="inputClass"
          placeholder="Bank, tanggal, nomor referensi"
        />
      </label>
      <button
        type="submit"
        :disabled="submitting"
        class="h-11 rounded-xl bg-custom-blue text-white text-sm font-medium hover:bg-blue-700 disabled:opacity-50"
      >
        Tandai Sudah Ditransfer
      </button>
    </form>
    <p v-else-if="isAdmin && awaitingManual" class="text-sm text-amber-700 dark:text-amber-400">
      Pembeli belum mengisi rekening tujuan.
    </p>

    <div v-if="isAdmin && awaitingManual" class="flex flex-col gap-2">
      <button
        type="button"
        :disabled="submitting"
        class="h-11 rounded-xl border border-custom-blue text-custom-blue text-sm font-medium hover:bg-custom-blue/5 disabled:opacity-50"
        @click="refundToBalance"
      >
        Kembalikan ke Saldo Blukios
      </button>
      <p v-if="balanceError" role="alert" class="text-sm text-red-600 dark:text-red-400">
        {{ balanceError }}
      </p>
    </div>
  </section>
</template>
