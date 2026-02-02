<script setup>
import { computed } from 'vue'

const props = defineProps({
  currentStep: {
    type: Number,
    default: 2 // 1: Cart, 2: Checkout/Shipping, 3: Success/Payment
  }
})

const steps = [
  { number: 1, label: 'Shopping Cart', route: 'app.cart' },
  { number: 2, label: 'Checkout Details', route: null }, // Current page
  { number: 3, label: 'Payment', route: null }
]
</script>

<template>
  <div class="w-full max-w-[600px] mx-auto mb-8">
    <div class="relative flex justify-between items-center w-full">
      <!-- Connecting Line -->
      <div
        class="absolute top-1/2 left-0 w-full h-[3px] bg-gray-100 -z-10 -translate-y-1/2 rounded-full overflow-hidden"
      >
        <!-- Progress Fill -->
        <div
          class="h-full bg-custom-blue transition-all duration-500"
          :style="{ width: `${((currentStep - 1) / (steps.length - 1)) * 100}%` }"
        ></div>
      </div>

      <!-- Steps -->
      <div
        v-for="(step, index) in steps"
        :key="step.number"
        class="flex flex-col items-center gap-2 group relative"
      >
        <div
          class="flex items-center justify-center size-8 md:size-10 rounded-full border-[3px] transition-all duration-300 relative bg-white z-10"
          :class="[
            currentStep > step.number
              ? 'bg-custom-blue border-custom-blue'
              : currentStep === step.number
                ? 'bg-white border-custom-blue shadow-[0_0_0_4px_rgba(59,130,246,0.2)]'
                : 'bg-white border-gray-200'
          ]"
        >
          <!-- Check Icon for Completed -->
          <svg
            v-if="currentStep > step.number"
            xmlns="http://www.w3.org/2000/svg"
            class="size-4 md:size-5 text-white"
            viewBox="0 0 20 20"
            fill="currentColor"
          >
            <path
              fill-rule="evenodd"
              d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
              clip-rule="evenodd"
            />
          </svg>

          <!-- Number for Active/Pending -->
          <span
            v-else
            class="text-xs md:text-sm font-bold transition-colors"
            :class="currentStep === step.number ? 'text-custom-blue' : 'text-gray-400'"
          >
            {{ step.number }}
          </span>
        </div>

        <span
          class="text-[10px] md:text-xs font-bold uppercase tracking-wider absolute top-10 md:top-12 transition-colors whitespace-nowrap"
          :class="[
            currentStep >= step.number ? 'text-custom-black' : 'text-gray-400',
            index === 0
              ? 'left-0 text-left'
              : index === steps.length - 1
                ? 'right-0 text-right'
                : 'left-1/2 -translate-x-1/2 text-center'
          ]"
        >
          {{ step.label }}
        </span>
      </div>
    </div>
  </div>
</template>
