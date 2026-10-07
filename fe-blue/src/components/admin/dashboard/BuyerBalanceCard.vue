<script setup>
import { computed, onMounted } from 'vue'
import { formatRupiah, formatDate } from '@/helpers/format'
import { useDashboardSummary } from '@/composables/useDashboardSummary'
import DashboardSection from '@/components/Molecule/DashboardSection.vue'
import EmptyState from '@/components/Atom/EmptyState.vue'

const TYPE_LABELS = {
  refund: 'Refund',
  payment: 'Dipakai belanja',
  payment_returned: 'Dikembalikan'
}

const { data, loading, error, fetch } = useDashboardSummary('balance?per_page=5')

const histories = computed(() => data.value?.histories?.data ?? [])

const sign = (amount) => (amount > 0 ? '+' : amount < 0 ? '−' : '')

onMounted(fetch)
</script>

<template>
  <DashboardSection title="Saldo Blukios" subtitle="Saldo dari refund pesanan">
    <div
      v-if="loading"
      role="status"
      class="animate-pulse flex flex-col gap-4"
      data-testid="balance-skeleton"
    >
      <span class="sr-only">Memuat Saldo Blukios</span>
      <div class="h-10 w-48 rounded-xl bg-gray-200 dark:bg-white/10"></div>
      <div v-for="i in 3" :key="i" class="h-12 rounded-xl bg-gray-200 dark:bg-white/10"></div>
    </div>

    <div
      v-else-if="error"
      role="alert"
      class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 rounded-2xl p-4 bg-red-50 dark:bg-red-500/10 text-red-700 dark:text-red-300"
    >
      <p class="text-sm">
        Saldo Blukios gagal dimuat. {{ typeof error === 'string' ? error : '' }}
      </p>
      <button
        type="button"
        class="px-4 py-2 rounded-full text-sm font-medium bg-white dark:bg-white/10 border border-red-200 dark:border-red-400/30 hover:bg-red-100 dark:hover:bg-red-500/20 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-500 transition-colors"
        @click="fetch"
      >
        Coba lagi
      </button>
    </div>

    <div v-else-if="data" class="flex flex-col gap-5">
      <p class="font-semibold text-3xl text-custom-black dark:text-white" data-testid="balance">
        Rp {{ formatRupiah(data.balance) }}
      </p>

      <ul
        v-if="histories.length"
        class="flex flex-col divide-y divide-gray-100 dark:divide-white/10"
      >
        <li
          v-for="row in histories"
          :key="row.id"
          class="flex items-center justify-between gap-4 py-3"
        >
          <div class="min-w-0">
            <p class="font-medium text-sm text-custom-black dark:text-white">
              {{ TYPE_LABELS[row.type] ?? row.type }}
            </p>
            <p class="text-xs text-custom-grey dark:text-gray-400 truncate">
              {{ formatDate(row.created_at)
              }}<template v-if="row.reference_code"> · {{ row.reference_code }}</template>
            </p>
          </div>
          <p
            class="font-medium text-sm shrink-0"
            :class="
              row.amount > 0
                ? 'text-emerald-700 dark:text-emerald-400'
                : 'text-custom-black dark:text-gray-200'
            "
          >
            {{ sign(row.amount) }}Rp {{ formatRupiah(Math.abs(row.amount)) }}
          </p>
        </li>
      </ul>
      <EmptyState v-else size="sm" title="Belum ada riwayat saldo" />
    </div>
  </DashboardSection>
</template>
