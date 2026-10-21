<script setup>
import { computed, ref } from 'vue'
import { useToast } from 'vue-toastification'
import { useAuthStore } from '@/stores/auth'
import { useTransactionStore } from '@/stores/transaction'

const props = defineProps({ transaction: { type: Object, required: true } })
const emit = defineEmits(['cancelled'])
const auth = useAuthStore()
const transactions = useTransactionStore()
const toast = useToast()
const busy = ref(false)
const error = ref('')
const canCancel = computed(() => auth.activeMode === 'buyer' &&
  !!auth.user?.id && props.transaction.buyer?.user?.id === auth.user.id &&
  ['unpaid', 'pending'].includes(props.transaction.payment_status))

const cancel = async () => {
  if (busy.value) return
  busy.value = true
  error.value = ''
  try {
    const orders = await transactions.previewUnpaidCancellation(props.transaction.id)
    const list = orders.map((order) => `${order.code} - ${order.store?.name || 'Toko'}`).join('\n')
    if (!confirm(`Batalkan pesanan dalam pembayaran ini?\n\n${list}\n\nSemua pesanan di atas dibatalkan. Saldo Blukios yang digunakan akan dikembalikan.`)) return
    const result = await transactions.cancelUnpaidTransaction(props.transaction.id)
    toast.success('Pesanan dibatalkan')
    emit('cancelled', result)
  } catch (e) {
    error.value = e?.response?.data?.message || 'Gagal membatalkan pesanan. Silakan coba lagi.'
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div v-if="canCancel">
    <button type="button" :disabled="busy"
      class="px-5 py-2.5 rounded-xl text-sm font-medium border border-red-300 text-red-700 dark:text-red-400 dark:border-red-800 hover:bg-red-50 dark:hover:bg-red-900/20 disabled:opacity-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600"
      @click="cancel">
      {{ busy ? 'Memproses pembatalan...' : 'Batalkan Pesanan' }}
    </button>
    <p v-if="error" role="alert" class="mt-2 text-sm text-red-700 dark:text-red-400">{{ error }}</p>
  </div>
</template>
