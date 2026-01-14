<script setup>
import { DialogTrigger } from 'radix-vue'
import { cn } from '@/lib/utils'

const props = defineProps({
  class: { type: String, default: '' },
  asChild: { type: Boolean, default: false },
})
</script>

<template>
  <DialogTrigger v-bind="props" :class="cn(props.class)">
    <slot />
  </DialogTrigger>
</template>
