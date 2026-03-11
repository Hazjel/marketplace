<script setup>
import { computed } from 'vue'
import { cva } from 'class-variance-authority'
import { cn } from '@/lib/utils'

const inputVariants = cva(
  'h-[76px] w-full border-[1.5px] rounded-[18px] pl-20 pr-6 pb-[14.5px] pt-[34.5px] font-semibold transition-all duration-300 appearance-none outline-none placeholder-shown:pt-[14.5px] focus:border-custom-blue focus:ring-1 focus:ring-custom-blue',
  {
    variants: {
      error: {
        true: 'border-2 border-custom-red ring-custom-red',
        false: 'border-custom-stroke'
      }
    },
    defaultVariants: {
      error: false
    }
  }
)

const props = defineProps({
  modelValue: {
    type: [String, Number],
    default: ''
  },
  label: {
    type: String,
    required: true
  },
  type: {
    type: String,
    default: 'text'
  },
  placeholder: {
    type: String,
    default: ''
  },
  error: {
    type: [String, Array],
    default: null
  },
  readonly: {
    type: Boolean,
    default: false
  },
  disabled: {
    type: Boolean,
    default: false
  },
  class: {
    type: String,
    default: ''
  }
})

const emit = defineEmits(['update:modelValue'])

const errorMessage = computed(() => {
  if (Array.isArray(props.error)) {
    return props.error.join(', ')
  }
  return props.error
})

const computedClass = computed(() => {
  return cn(inputVariants({ error: !!errorMessage.value }), props.class)
})

const updateValue = (e) => {
  emit('update:modelValue', e.target.value)
}
</script>

<template>
  <div class="group/errorState flex flex-col gap-2 w-full" :class="{ invalid: !!errorMessage }">
    <label class="group relative">
      <div
        v-if="$slots.icon"
        class="input-icon absolute top-1/2 left-6 flex h-7 -translate-y-1/2 items-center border-r-[1.5px] border-custom-stroke pr-4"
      >
        <slot name="icon"></slot>
      </div>

      <p
        class="input-placeholder absolute left-[81px] top-[25px] -translate-y-1/2 text-sm font-semibold text-custom-grey transition-all duration-300 pointer-events-none group-focus-within:top-[25px] group-has-placeholder-shown:top-[38px] group-has-placeholder-shown:text-base group-has-placeholder-shown:font-bold group-has-placeholder-shown:text-custom-black"
      >
        {{ label }}
      </p>

      <input
        :type="type"
        :class="computedClass"
        :placeholder="placeholder"
        :value="modelValue"
        :readonly="readonly"
        :disabled="disabled"
        @input="updateValue"
      />
    </label>
    <span
      v-if="errorMessage"
      class="input-error hidden text-sm font-semibold leading-none text-custom-red group-[.invalid]/errorState:block"
      >{{ errorMessage }}</span
    >
  </div>
</template>
