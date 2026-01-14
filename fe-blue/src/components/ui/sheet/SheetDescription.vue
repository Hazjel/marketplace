<script setup>
import { DialogDescription } from 'radix-vue'
import { cn } from '@/lib/utils'

const props = defineProps({
  class: { type: String, default: '' },
})
</script>

<template>
  <DialogDescription :class="cn('text-sm text-slate-500', props.class)">
    <slot />
  </DialogDescription>
</template>
