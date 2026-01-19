<script setup>
import { computed } from 'vue';
import { RouterLink } from 'vue-router';

const props = defineProps({
    variant: {
        type: String,
        default: 'primary',
        validator: (value) => ['primary', 'secondary', 'outline', 'ghost', 'danger'].includes(value)
    },
    size: {
        type: String,
        default: 'lg',
        validator: (value) => ['sm', 'md', 'lg'].includes(value)
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
    }
});

const baseClasses = 'flex items-center justify-center gap-2 font-semibold transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed';

const sizeClasses = computed(() => {
    switch (props.size) {
        case 'sm': return 'h-10 px-4 text-sm rounded-xl';
        case 'md': return 'h-12 px-5 text-base rounded-[16px]';
        case 'lg': return 'h-14 px-6 text-lg rounded-full'; // Primary style often uses full rounded
        default: return 'h-14 px-6 text-lg rounded-full';
    }
});

const variantClasses = computed(() => {
    switch (props.variant) {
        case 'primary':
            return 'bg-custom-blue text-white hover:shadow-lg hover:shadow-custom-blue/30 active:scale-95';
        case 'secondary':
            return 'bg-custom-black text-white hover:bg-custom-black/80 active:scale-95 rounded-2xl'; // Override rounded for secondary based on existing usage
        case 'outline':
            return 'border-[1.5px] border-custom-stroke bg-white text-custom-black hover:border-custom-black active:bg-gray-50';
        case 'ghost':
            return 'bg-transparent text-custom-grey hover:text-custom-black hover:bg-gray-100';
        case 'danger':
            return 'bg-custom-red text-white hover:shadow-lg hover:shadow-custom-red/30 active:scale-95';
        default:
            return 'bg-custom-blue text-white';
    }
});

const widthClass = computed(() => props.block ? 'w-full' : 'w-fit');
</script>

<template>
    <component 
        :is="to ? RouterLink : 'button'" 
        :to="to"
        :type="to ? null : type"
        :disabled="loading || disabled"
        :class="[baseClasses, sizeClasses, variantClasses, widthClass]"
    >
        <span v-if="loading" class="size-5 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
        <slot v-else></slot>
    </component>
</template>
