<script setup>
import { DialogTitle } from 'radix-vue'
import { cn } from '@/lib/utils'

const props = defineProps({
  class: { type: String, default: '' },
})
</script>

<template>
  <DialogTitle :class="cn('text-lg font-semibold text-slate-950', props.class)">
    <slot />
  </DialogTitle>
</template>
