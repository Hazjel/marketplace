<script setup>
defineProps({
    title: {
        type: String,
        required: true
    },
    subtitle: {
        type: String,
        default: ''
    },
    link: {
        type: [String, Object],
        required: true
    },
    linkText: {
        type: String,
        default: 'VIEW ALL'
    }
})
</script>

<template>
    <div class="flex items-center justify-between gap-4">
        <h2 class="font-extrabold text-lg md:text-[32px] leading-tight text-custom-black dark:text-white">
            {{ title }}
            <br v-if="subtitle" class="hidden md:block">
            <span v-if="subtitle"> {{ subtitle }}</span>
        </h2>
        <RouterLink :to="link"
            class="flex shrink-0 items-center h-10 md:h-14 rounded-[18px] py-2 px-3 md:py-4 md:px-6 gap-[10px] bg-custom-black hover:bg-black/80 dark:bg-white/5 dark:hover:bg-white/10 dark:border dark:border-white/10 transition-all">
            <span class="font-medium text-white hidden md:block">{{ linkText }}</span>
            <img src="@/assets/images/icons/arrow-right-white.svg" class="flex size-5 md:size-6 shrink-0" alt="icon">
        </RouterLink>
    </div>
</template>
