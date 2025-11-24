<script setup>
const emit = defineEmits(['closeAlert'])
defineProps({
    success: {
        type: String,
        required: false
    },
    error: {
        type: String,
        required: false
    }
})

const closeAlert = () => {
    emit('closeAlert')
}
</script>

<template>
    <div class="relative w-full rounded-[20px] bg-custom-orange overflow-hidden" v-if="error">
        <img src="@/assets/images/backgrounds/round-ornament.svg"
            class="size-full object-contain object-right opacity-55 absolute" alt="icon">
        <div class="relative flex items-center min-h-[68px] gap-[10px] p-4">
            <p class="font-bold text-lg text-white">{{ error }}</p>
            <button @click="closeAlert" class="absolute top-1/2 right-6 transform -translate-y-1/2 m-[10px]">
                <img src="@/assets/images/icons/close-circle-white.svg" class="flex size-6" alt="close">
            </button>
        </div>
    </div>
    <div class="relative w-full rounded-[20px] bg-custom-green overflow-hidden" v-if="success">
        <img src="@/assets/images/backgrounds/round-ornament.svg"
            class="size-full object-contain object-right opacity-55 absolute" alt="icon">
        <div class="relative flex items-center min-h-[68px] gap-[10px] p-4">
            <p class="font-bold text-lg text-white">{{ success }}</p>
            <button @click="closeAlert" class="absolute top-1/2 right-6 transform -translate-y-1/2 m-[10px]">
                <img src="@/assets/images/icons/close-circle-white.svg" class="flex size-6" alt="close">
            </button>
        </div>
    </div>
</template>
