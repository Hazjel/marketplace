<script setup>
import { DropdownMenuRoot } from 'radix-vue'

const props = defineProps({
  defaultOpen: { type: Boolean, required: false },
  open: { type: Boolean, required: false },
  dir: { type: String, required: false },
  modal: { type: Boolean, default: true }
})
const emits = defineEmits(['update:open'])
</script>

<template>
  <DropdownMenuRoot v-bind="props" @update:open="emits('update:open', $event)">
    <slot />
  </DropdownMenuRoot>
</template>
