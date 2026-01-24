<script setup>
defineProps({
    title: { type: String, required: true },
    value: { type: [String, Number], required: true },
    icon: { type: String, default: 'wallet-2-blue-fill.svg' },
    trend: { type: Object, default: null } // { value: '12%', direction: 'up' | 'down' }
});
</script>

<template>
    <div
        class="flex flex-col w-full rounded-[20px] p-5 gap-6 bg-white border border-transparent hover:border-custom-blue/20 transition-all shadow-[0_2px_10px_rgba(0,0,0,0.02)]">
        <div class="flex flex-col gap-6">
            <div class="flex justify-between items-start">
                <div class="flex size-[56px] bg-custom-blue/10 items-center justify-center rounded-2xl">
                    <img :src="`/src/assets/images/icons/${icon}`" class="flex size-6 shrink-0" alt="icon">
                </div>

                <div v-if="trend" class="flex items-center gap-1 text-xs font-bold px-2 py-1 rounded-full"
                    :class="trend.direction === 'up' ? 'bg-green-100 text-custom-green' : 'bg-red-100 text-custom-red'">
                    <i class="fa-solid"
                        :class="trend.direction === 'up' ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down'"></i>
                    {{ trend.value }}
                </div>
            </div>

            <div class="flex flex-col gap-[6px]">
                <p class="font-bold text-2xl md:text-3xl text-custom-black tracking-tight">{{ value }}</p>
                <p class="font-medium text-sm text-custom-grey">{{ title }}</p>
            </div>
        </div>
    </div>
</template>
