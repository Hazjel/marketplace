<script setup>
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { cva } from 'class-variance-authority'
import { cn } from '@/lib/utils'

const buttonVariants = cva(
  'inline-flex items-center justify-center gap-2 font-semibold transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-custom-blue focus-visible:ring-offset-2',
  {
    variants: {
      variant: {
        primary: 'bg-custom-blue text-white hover:bg-blue-700 hover:shadow-soft',
        accent: 'bg-custom-orange text-white hover:bg-orange-600 hover:shadow-soft',
        secondary: 'bg-custom-black text-white hover:bg-gray-800 rounded-2xl',
        dark: 'bg-custom-black text-white hover:bg-gray-800 rounded-2xl',
        outline:
          'border-[1.5px] border-custom-stroke bg-white text-custom-black hover:border-custom-black hover:bg-gray-50',
        ghost: 'bg-transparent text-custom-grey hover:text-custom-black hover:bg-gray-100',
        danger: 'bg-custom-red text-white hover:shadow-lg hover:shadow-custom-red/30',
        link: 'text-custom-blue underline-offset-4 hover:underline'
      },
      size: {
        sm: 'h-10 px-4 text-sm rounded-xl',
        md: 'h-12 px-5 text-base rounded-[16px]',
        lg: 'h-14 px-6 text-lg rounded-full',
        icon: 'size-10 rounded-full p-0'
      },
      block: {
        true: 'w-full',
        false: 'w-fit'
      }
    },
    defaultVariants: {
      variant: 'primary',
      size: 'lg',
      block: false
    }
  }
)

const props = defineProps({
  variant: {
    type: String,
    default: 'primary'
  },
  size: {
    type: String,
    default: 'lg'
  },
  to: {
    type: [String, Object],
    default: null
  },
  loading: {
    type: Boolean,
    default: false
  },
  disabled: {
    type: Boolean,
    default: false
  },
  type: {
    type: String,
    default: 'button'
  },
  block: {
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
    buttonVariants({
      variant: props.variant,
      size: props.size,
      block: props.block
    }),
    props.class
  )
})
</script>

<template>
  <component
    :is="to ? RouterLink : 'button'"
    :to="to"
    :type="to ? null : type"
    :disabled="loading || disabled"
    :class="computedClass"
  >
    <span
      v-if="loading"
      class="size-5 border-2 border-white/30 border-t-white rounded-full animate-spin"
    ></span>
    <slot v-else></slot>
  </component>
</template>
