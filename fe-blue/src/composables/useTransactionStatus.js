// payment_status enum: unpaid/paid/failed (never 'pending')
// delivery_status enum: pending/processing/delivering/completed/cancelled/failed
const FAILURE_STATUSES = ['expire', 'cancel', 'deny', 'failure', 'failed']

const PAYMENT_LABELS = {
  unpaid: 'Belum Bayar',
  paid: 'Lunas',
  failed: 'Gagal'
}

const PAYMENT_STYLES = {
  unpaid:
    'bg-amber-50 text-amber-700 dark:bg-amber-900/20 dark:text-amber-400 ring-1 ring-amber-100 dark:ring-amber-900/30',
  paid: 'bg-green-50 text-green-600 dark:bg-green-900/20 dark:text-green-400 ring-1 ring-green-100 dark:ring-green-900/30',
  failed: 'bg-red-50 text-red-600 dark:bg-red-900/20 dark:text-red-400 ring-1 ring-red-100 dark:ring-red-900/30'
}

const DELIVERY_LABELS = {
  pending: 'Menunggu Diproses',
  processing: 'Diproses',
  delivering: 'Dikirim',
  completed: 'Selesai',
  cancelled: 'Dibatalkan',
  failed: 'Gagal'
}

const DELIVERY_STYLES = {
  pending:
    'bg-amber-50 text-amber-700 dark:bg-amber-900/20 dark:text-amber-400 ring-1 ring-amber-100 dark:ring-amber-900/30',
  processing:
    'bg-blue-50 text-blue-600 dark:bg-blue-900/20 dark:text-blue-400 ring-1 ring-blue-100 dark:ring-blue-900/30',
  delivering:
    'bg-orange-50 text-orange-600 dark:bg-orange-900/20 dark:text-orange-400 ring-1 ring-orange-100 dark:ring-orange-900/30',
  completed:
    'bg-green-50 text-green-600 dark:bg-green-900/20 dark:text-green-400 ring-1 ring-green-100 dark:ring-green-900/30',
  cancelled: 'bg-red-50 text-red-600 dark:bg-red-900/20 dark:text-red-400 ring-1 ring-red-100 dark:ring-red-900/30'
}

const DEFAULT_STYLE = 'bg-gray-50 text-gray-600 dark:bg-gray-800 dark:text-gray-400 ring-1 ring-gray-100 dark:ring-gray-700'

const DEFAULT_ICON = 'help-circle'
const PAYMENT_ICONS = { unpaid: 'clock', paid: null, failed: 'x-circle' }
const DELIVERY_ICONS = {
  pending: 'clock',
  processing: 'package',
  delivering: 'truck',
  completed: 'check-circle',
  cancelled: 'x-circle'
}

export function isFailedTransaction(transaction) {
  return FAILURE_STATUSES.includes(transaction?.payment_status)
}

/**
 * Status pembayaran saja: unpaid/paid/failed -- dipakai view yang menampilkan
 * payment dan delivery sebagai dua badge terpisah (mis. IncomingOrders.vue).
 */
export function resolvePaymentStatus(transaction) {
  if (isFailedTransaction(transaction)) {
    return { label: PAYMENT_LABELS.failed, style: PAYMENT_STYLES.failed, icon: PAYMENT_ICONS.failed }
  }

  const status = transaction?.payment_status
  return {
    label: PAYMENT_LABELS[status] ?? status ?? 'Unknown',
    style: PAYMENT_STYLES[status] ?? DEFAULT_STYLE,
    icon: PAYMENT_ICONS[status] ?? DEFAULT_ICON
  }
}

/**
 * Status pengiriman saja: pending/processing/delivering/completed/cancelled.
 */
export function resolveDeliveryStatus(transaction) {
  const status = transaction?.delivery_status
  return {
    label: DELIVERY_LABELS[status] ?? status ?? 'Unknown',
    style: DELIVERY_STYLES[status] ?? DEFAULT_STYLE,
    icon: DELIVERY_ICONS[status] ?? DEFAULT_ICON
  }
}

/**
 * Satu status gabungan payment+delivery untuk view yang menampilkan satu
 * badge saja (mis. MyTransaction.vue, TransactionList.vue): gagal dulu,
 * lalu belum-bayar, baru status pengiriman.
 */
export function resolveTransactionStatus(transaction) {
  if (isFailedTransaction(transaction)) {
    return { label: 'Gagal', style: PAYMENT_STYLES.failed, icon: 'x-circle', isFailure: true }
  }

  if (transaction?.payment_status === 'unpaid') {
    return {
      label: PAYMENT_LABELS.unpaid,
      style: PAYMENT_STYLES.unpaid,
      icon: PAYMENT_ICONS.unpaid,
      isFailure: false
    }
  }

  const delivery = resolveDeliveryStatus(transaction)
  return { ...delivery, isFailure: false }
}
