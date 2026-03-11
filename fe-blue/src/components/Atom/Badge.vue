<script setup>
import { computed } from 'vue'
import { cva } from 'class-variance-authority'
import { cn } from '@/lib/utils'

const badgeVariants = cva(
  'inline-flex items-center justify-center font-bold uppercase transition-all duration-300',
  {
    variants: {
      variant: {
        primary: '',
        success: '',
        warning: '',
        danger: '',
        info: '',
        neutral: '',
        orange: ''
      },
      type: {
        solid: 'text-white',
        light: ''
      },
      size: {
        xs: 'py-1 px-2.5 text-[10px] leading-tight',
        sm: 'py-1.5 px-3 text-xs',
        md: 'py-3 px-[18px] text-sm'
      },
      circle: {
        true: 'rounded-full aspect-square p-0 flex items-center justify-center',
        false: 'rounded-full'
      }
    },
    compoundVariants: [
      // Primary
      { variant: 'primary', type: 'solid', class: 'bg-custom-blue text-white' },
      { variant: 'primary', type: 'light', class: 'bg-custom-blue/10 text-custom-blue' },
      // Success
      { variant: 'success', type: 'solid', class: 'bg-custom-green text-white' },
      { variant: 'success', type: 'light', class: 'bg-custom-green/10 text-custom-green' },
      // Warning
      { variant: 'warning', type: 'solid', class: 'bg-custom-yellow text-[#544607]' },
      { variant: 'warning', type: 'light', class: 'bg-custom-yellow/20 text-[#544607]' },
      // Danger
      { variant: 'danger', type: 'solid', class: 'bg-custom-red text-white' },
      { variant: 'danger', type: 'light', class: 'bg-custom-red/10 text-custom-red' },
      // Info
      { variant: 'info', type: 'solid', class: 'bg-custom-blue text-white' },
      { variant: 'info', type: 'light', class: 'bg-custom-blue/10 text-custom-blue' },
      // Neutral
      { variant: 'neutral', type: 'solid', class: 'bg-custom-grey text-white' },
      { variant: 'neutral', type: 'light', class: 'bg-custom-grey/10 text-custom-grey' },
      // Orange
      { variant: 'orange', type: 'solid', class: 'bg-custom-orange text-white' },
      { variant: 'orange', type: 'light', class: 'bg-custom-orange/10 text-custom-orange' },
      // Circle overrides for sizes
      { circle: true, size: 'sm', class: 'size-8 text-xs px-0 py-0' },
      { circle: true, size: 'md', class: 'size-11 text-base px-0 py-0' }
    ],
    defaultVariants: {
      variant: 'primary',
      type: 'light',
      size: 'md',
      circle: false
    }
  }
)

const props = defineProps({
  variant: {
    type: String,
    default: 'primary'
  },
  type: {
    type: String,
    default: 'light'
  },
  size: {
    type: String,
    default: 'md'
  },
  circle: {
    type: Boolean,
    default: false
  },
  class: {
    type: String,
    default: ''
  }
})

const computedClass = computed(() => {
  return cn(
    badgeVariants({
      variant: props.variant,
      type: props.type,
      size: props.size,
      circle: props.circle
    }),
    props.class
  )
})
</script>

<template>
  <div :class="computedClass">
    <slot></slot>
  </div>
</template>
