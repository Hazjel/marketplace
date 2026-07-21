<script setup>
import { VisXYContainer, VisLine, VisAxis, VisScatter, VisTooltip, VisCrosshair } from '@unovis/vue'
import { Scatter } from '@unovis/ts'
import { formatRupiah } from '@/helpers/format'

const props = defineProps({
  data: { type: Array, default: () => [] },
  // Key nilai Y di tiap item data, mis. 'total_revenue' atau 'total_expense'
  yKey: { type: String, default: 'total_revenue' },
  // Label tooltip, mis. 'Pendapatan' atau 'Pengeluaran'
  label: { type: String, default: 'Pendapatan' },
  color: { type: String, default: '#3D7AF0' },
  height: { type: String, default: '300px' },
  // Format nilai Y untuk tooltip & sumbu. Default: Rupiah.
  valueFormatter: { type: Function, default: (v) => `Rp ${formatRupiah(v)}` }
})

const x = (d, i) => i
const y = (d) => Number(d[props.yKey])

const tickFormat = (i) => {
  const dateStr = props.data[i]?.date
  if (!dateStr) return ''
  const date = new Date(dateStr)
  return date.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })
}

const yTickFormat = (d) => {
  if (d >= 1000000) return (d / 1000000).toFixed(1) + 'jt'
  if (d >= 1000) return (d / 1000).toFixed(0) + 'k'
  return d
}

const triggers = {
  [Scatter.selectors.point]: (d) => `
    <div class="bg-white dark:bg-surface-card p-2 rounded-lg shadow-[0_4px_12px_rgba(0,0,0,0.1)] border border-gray-200 dark:border-white/10">
      <p class="text-gray-500 dark:text-gray-400 text-xs mb-1">${props.label}</p>
      <p class="text-gray-900 dark:text-white font-bold text-sm">${props.valueFormatter(d[props.yKey])}</p>
    </div>
  `
}
</script>

<template>
  <div class="w-full" :style="{ height }">
    <VisXYContainer :data="data" :margin="{ top: 20, right: 30, bottom: 20, left: 50 }">
      <VisLine :x="x" :y="y" :color="color" :line-width="3" />
      <VisScatter :x="x" :y="y" color="#fff" :stroke-color="color" :stroke-width="2" :size="20" />
      <VisAxis type="x" :tick-format="tickFormat" :grid-line="false" />
      <VisAxis type="y" :tick-format="yTickFormat" />
      <VisCrosshair />
      <VisTooltip :triggers="triggers" />
    </VisXYContainer>
  </div>
</template>
