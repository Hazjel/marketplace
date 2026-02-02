<script setup>
import { VisXYContainer, VisLine, VisAxis, VisScatter, VisTooltip, VisCrosshair } from '@unovis/vue'
import { Scatter } from '@unovis/ts'
import { ref, computed } from 'vue'
import { formatRupiah } from '@/helpers/format'

const props = defineProps({
  data: {
    type: Array,
    default: () => []
  }
})

// Accessors
const x = (d, i) => i
const y = (d) => Number(d.total_revenue)

// Format
const tickFormat = (i) => {
  const dateStr = props.data[i]?.date
  if (!dateStr) return ''
  const date = new Date(dateStr)
  return date.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' }) // e.g. 14 Jan
}
const yTickFormat = (d) => {
  if (d >= 1000000) return (d / 1000000).toFixed(1) + 'jt'
  if (d >= 1000) return (d / 1000).toFixed(0) + 'k'
  return d
}

const triggers = {
  [Scatter.selectors.point]: (d) => {
    return `
            <div style="background: white; padding: 8px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); border: 1px solid #E5E7EB;">
                <p style="color: #6B7280; font-size: 12px; margin-bottom: 4px;">Pendapatan</p>
                <p style="color: #111827; font-weight: bold; font-size: 14px;">Rp ${formatRupiah(d.total_revenue)}</p>
            </div>
        `
  }
}
</script>

<template>
  <div class="w-full h-[300px]">
    <VisXYContainer :data="data" :margin="{ top: 20, right: 30, bottom: 20, left: 50 }">
      <VisLine :x="x" :y="y" color="#3D7AF0" :line-width="3" />
      <VisScatter :x="x" :y="y" color="#fff" :stroke-color="'#3D7AF0'" :stroke-width="2" :size="20" />
      <VisAxis type="x" :tick-format="tickFormat" :grid-line="false" />
      <VisAxis type="y" :tick-format="yTickFormat" />
      <VisCrosshair />
      <VisTooltip :triggers="triggers" />
    </VisXYContainer>
  </div>
</template>
