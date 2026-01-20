<script setup>
import { VisXYContainer, VisLine, VisAxis, VisScatter, VisTooltip, VisCrosshair } from '@unovis/vue'
import { ref, computed } from 'vue'

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
    const dateStr = props.data[i]?.date;
    if (!dateStr) return '';
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' }); // e.g. 14 Jan
}
const yTickFormat = (d) => {
    if(d >= 1000000) return (d/1000000).toFixed(1) + 'jt'
    if(d >= 1000) return (d/1000).toFixed(0) + 'k'
    return d
}

</script>

<template>
  <div class="w-full h-[300px]">
    <VisXYContainer :data="data" :margin="{ top: 20, right: 30, bottom: 20, left: 50 }">
        <VisLine :x="x" :y="y" color="#3D7AF0" :lineWidth="3" />
        <VisScatter :x="x" :y="y" color="#fff" :strokeColor="'#3D7AF0'" :strokeWidth="2" :size="20" />
        <VisAxis type="x" :tickFormat="tickFormat" :gridLine="false" />
        <VisAxis type="y" :tickFormat="yTickFormat" />
        <VisCrosshair />
        <VisTooltip />
    </VisXYContainer>
  </div>
</template>
