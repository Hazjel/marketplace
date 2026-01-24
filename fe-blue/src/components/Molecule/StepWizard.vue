<script setup>
defineProps({
    steps: {
        type: Array,
        required: true // ['Basic Info', 'Details', 'Media']
    },
    currentStep: {
        type: Number,
        required: true
    }
});

const emit = defineEmits(['change-step']);
</script>

<template>
    <div class="w-full flex items-center justify-between mb-8 px-4 relative">
        <!-- Connecting Line -->
        <div class="absolute left-0 top-1/2 -translate-y-1/2 w-full h-1 bg-gray-100 -z-10"></div>
        <div class="absolute left-0 top-1/2 -translate-y-1/2 h-1 bg-custom-blue -z-10 transition-all duration-300"
            :style="{ width: `${((currentStep - 1) / (steps.length - 1)) * 100}%` }"></div>

        <!-- Steps -->
        <div v-for="(step, index) in steps" :key="index" class="flex flex-col items-center gap-2 cursor-pointer"
            @click="index + 1 < currentStep ? emit('change-step', index + 1) : null">

            <div class="size-10 rounded-full flex items-center justify-center font-bold text-sm border-[3px] transition-all duration-300"
                :class="[
                    currentStep > index + 1 ? 'bg-custom-blue border-custom-blue text-white' :
                        currentStep === index + 1 ? 'bg-white border-custom-blue text-custom-blue ring-4 ring-blue-50' :
                            'bg-white border-gray-200 text-gray-400'
                ]">
                <span v-if="currentStep > index + 1"><i class="fa-solid fa-check"></i></span>
                <span v-else>{{ index + 1 }}</span>
            </div>
            <span class="text-xs font-semibold uppercase tracking-wider"
                :class="currentStep >= index + 1 ? 'text-custom-blue' : 'text-gray-400'">
                {{ step }}
            </span>
        </div>
    </div>
</template>
