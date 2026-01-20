<script setup>
import { computed } from 'vue';

const props = defineProps({
    variant: {
        type: String,
        default: 'primary',
        validator: (value) => ['primary', 'success', 'warning', 'danger', 'info', 'neutral', 'orange'].includes(value)
    },
    // Light variant (bg-color/10 text-color) vs Solid variant (bg-color text-white)
    type: {
        type: String, 
        default: 'light',
        validator: (value) => ['solid', 'light'].includes(value)
    },
    size: {
        type: String,
        default: 'md',
        validator: (value) => ['sm', 'md'].includes(value)
    },
    circle: {
        type: Boolean,
        default: false
    }
});

const baseClasses = 'flex items-center justify-center font-bold uppercase transition-all duration-300';
const roundedClass = computed(() => props.circle ? 'rounded-full aspect-square p-0' : 'rounded-full');

const sizeClasses = computed(() => {
    if (props.circle) {
         return props.size === 'sm' ? 'size-8 text-xs' : 'size-11 text-base';
    }
    return props.size === 'sm' ? 'py-2 px-4 text-xs' : 'py-3 px-[18px] text-sm';
});

const variantClasses = computed(() => {
    const isSolid = props.type === 'solid';
    
    switch (props.variant) {
        case 'success':
             return isSolid 
                ? 'bg-custom-green text-white' 
                : 'bg-custom-green/10 text-custom-green';
        case 'warning':
             return isSolid 
                ? 'bg-custom-yellow text-[#544607]' 
                : 'bg-custom-yellow/20 text-[#544607]'; // Yellow usually needs darker text on light
        case 'danger':
             return isSolid 
                ? 'bg-custom-red text-white' 
                : 'bg-custom-red/10 text-custom-red';
        case 'info':
             return isSolid 
                ? 'bg-custom-blue text-white' 
                : 'bg-custom-blue/10 text-custom-blue';
        case 'neutral':
             return isSolid 
                ? 'bg-custom-grey text-white' 
                : 'bg-custom-grey/10 text-custom-grey';
        case 'orange': // Special case from previous designs
             return isSolid
                ? 'bg-custom-orange text-white'
                : 'bg-custom-orange/10 text-custom-orange'; 
        default: // Primary = Blue
             return isSolid 
                ? 'bg-custom-blue text-white' 
                : 'bg-custom-blue/10 text-custom-blue';
    }
});
</script>

<template>
    <div :class="[baseClasses, roundedClass, sizeClasses, variantClasses]">
        <slot></slot>
    </div>
</template>
