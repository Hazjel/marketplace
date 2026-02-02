<script setup>
import { DialogClose } from 'radix-vue'
import { cn } from '@/lib/utils'

const props = defineProps({
  class: { type: String, default: '' },
  asChild: { type: Boolean, default: false }
})
</script>

<template>
  <DialogClose v-bind="props" :class="cn(props.class)">
    <slot />
  </DialogClose>
</template>
