<script setup>
import { DropdownMenuItem } from 'radix-vue'
import { cn } from '@/lib/utils'

const props = defineProps({
  class: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
  inset: { type: Boolean, default: false },
  asChild: { type: Boolean, default: false }
})
</script>

<template>
  <DropdownMenuItem
    v-bind="props"
    :class="
      cn(
        'relative flex cursor-default select-none items-center rounded-sm px-2 py-1.5 text-sm outline-none transition-colors focus:bg-accent focus:text-accent-foreground data-[disabled]:pointer-events-none data-[disabled]:opacity-50',
        inset && 'pl-8',
        props.class
      )
    "
  >
    <slot />
  </DropdownMenuItem>
</template>
