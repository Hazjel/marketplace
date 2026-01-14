<script setup>
import { DropdownMenuTrigger } from 'radix-vue'

const props = defineProps({
  disabled: { type: Boolean, default: false },
  asChild: { type: Boolean, default: false },
})
</script>

<template>
  <DropdownMenuTrigger class="outline-none" v-bind="props">
    <slot />
  </DropdownMenuTrigger>
</template>
