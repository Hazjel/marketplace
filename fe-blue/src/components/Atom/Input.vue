<script setup>
import { computed } from 'vue'

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
  }
})

const emit = defineEmits(['update:modelValue'])

// Handle array of errors or single string
const errorMessage = computed(() => {
  if (Array.isArray(props.error)) {
    return props.error.join(', ')
  }
  return props.error
})

const updateValue = (e) => {
  emit('update:modelValue', e.target.value)
}
</script>

<template>
  <div class="group/errorState flex flex-col gap-2 w-full" :class="{ invalid: !!errorMessage }">
    <label class="group relative">
      <div v-if="$slots.icon" class="input-icon">
        <slot name="icon"></slot>
      </div>

      <p class="input-placeholder">
        {{ label }}
      </p>

      <input
        :type="type"
        class="custom-input"
        :placeholder="placeholder"
        :value="modelValue"
        :readonly="readonly"
        :disabled="disabled"
        @input="updateValue"
      />
    </label>
    <span v-if="errorMessage" class="input-error">{{ errorMessage }}</span>
  </div>
</template>
