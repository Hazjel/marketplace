<script setup>
import { VisXYContainer, VisLine, VisAxis, VisArea, VisTooltip, VisCrosshair } from '@unovis/vue'
import { ref, computed } from 'vue'

const props = defineProps({
  data: {
    type: Array,
    default: () => []
  }
})

// Accessors
const x = (d) => d.date
const y = (d) => d.total

// Format
const tickFormat = (d) => d 
const yTickFormat = (d) => {
    if(d >= 1000000) return (d/1000000).toFixed(1) + 'jt'
    if(d >= 1000) return (d/1000).toFixed(0) + 'k'
    return d
}

</script>

<template>
  <div class="w-full h-[300px]">
    <VisXYContainer :data="data" :margin="{ top: 20, right: 30, bottom: 20, left: 50 }">
        <VisArea :x="x" :y="y" color="#3D7AF0" :opacity="0.1" />
        <VisLine :x="x" :y="y" color="#3D7AF0" :lineWidth="2" />
        <VisAxis type="x" :tickFormat="tickFormat" />
        <VisAxis type="y" :tickFormat="yTickFormat" />
        <VisCrosshair />
        <VisTooltip />
    </VisXYContainer>
  </div>
</template>
