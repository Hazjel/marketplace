<script setup>
import { computed } from 'vue'

const props = defineProps({
  // { unpaid, paid, failed, pending, shipping, delivering, delivered, completed, cancelled }
  breakdown: { type: Object, required: true },
  // Daftar key yang mau ditampilkan + label + warna. Default: delivery status.
  items: {
    type: Array,
    default: () => [
      { key: 'pending', label: 'Menunggu Diproses', color: 'bg-amber-500' },
      { key: 'shipping', label: 'Dikirim', color: 'bg-blue-500' },
      { key: 'delivering', label: 'Dalam Perjalanan', color: 'bg-indigo-500' },
      { key: 'completed', label: 'Selesai', color: 'bg-emerald-500' },
      { key: 'cancelled', label: 'Dibatalkan', color: 'bg-red-500' }
    ]
  }
})

const total = computed(() =>
  props.items.reduce((sum, item) => sum + (props.breakdown[item.key] || 0), 0)
)

const rows = computed(() =>
  props.items.map((item) => {
    const count = props.breakdown[item.key] || 0
    const percent = total.value > 0 ? Math.round((count / total.value) * 100) : 0
    return { ...item, count, percent }
  })
)
</script>

<template>
  <div class="flex flex-col gap-4">
    <template v-if="total > 0">
      <div v-for="row in rows" :key="row.key" class="flex flex-col gap-1.5">
        <div class="flex items-center justify-between text-sm">
          <span class="font-medium text-gray-700 dark:text-gray-300">{{ row.label }}</span>
          <span class="font-bold text-gray-900 dark:text-white">{{ row.count }}</span>
        </div>
        <div class="w-full h-2 rounded-full bg-gray-100 dark:bg-white/10 overflow-hidden">
          <div
            class="h-full rounded-full transition-all"
            :class="row.color"
            :style="{ width: `${row.percent}%` }"
          ></div>
        </div>
      </div>
    </template>
    <div v-else class="text-center py-6 text-gray-500 dark:text-gray-400 text-sm">Belum ada data pesanan</div>
  </div>
</template>
