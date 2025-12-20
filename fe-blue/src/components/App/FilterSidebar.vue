<script setup>
import { ref, watch, defineEmits } from 'vue';

const props = defineProps({
    initialFilters: {
        type: Object,
        default: () => ({})
    }
});

const emit = defineEmits(['filter-change']);

// Helper to ensure array
const ensureArray = (val) => {
    if (!val) return [];
    if (Array.isArray(val)) return val;
    return [val];
};

// Filters State
const priceMin = ref(props.initialFilters.min_price || '');
const priceMax = ref(props.initialFilters.max_price || '');
const selectedConditions = ref(ensureArray(props.initialFilters.condition));
const minRating = ref(props.initialFilters.min_rating || null);
const createdSince = ref(props.initialFilters.created_since ? parseInt(props.initialFilters.created_since) : null);

// Data Lists
const conditions = [
    { label: 'Baru', value: 'New' },
    { label: 'Bekas', value: 'Used' }
];

const timeOptions = [
    { label: '7 Hari', value: 7 },
    { label: '14 Hari', value: 14 },
    { label: '1 Bulan', value: 30 },
    { label: '3 Bulan', value: 90 }
];

const ratingLevels = [4];

// Handle Changes
const applyFilters = () => {
    emit('filter-change', {
        min_price: priceMin.value,
        max_price: priceMax.value,
        condition: selectedConditions.value,
        min_rating: minRating.value,
        min_rating: minRating.value,
        created_since: createdSince.value
    });
};

// Auto-apply on change
watch([priceMin, priceMax, selectedConditions, minRating, createdSince], () => {
    // Middleware: Prevent negative values
    if (priceMin.value < 0) priceMin.value = 0;
    if (priceMax.value < 0) priceMax.value = 0;
    
    applyFilters();
});

// Toggle Sections
const openSections = ref({
    price: true,
    condition: true,
    rating: true,
    rating: true,
    added: true
});

const toggleSection = (section) => {
    openSections.value[section] = !openSections.value[section];
};
</script>

<template>
    <div class="flex flex-col gap-6 w-[250px] min-w-[250px]">
        <h3 class="font-bold text-xl">Filter</h3>

        <div class="flex flex-col gap-4 bg-white p-4 rounded-xl border border-gray-100 dark:bg-gray-800 dark:border-gray-700">
            
            <!-- Harga -->
            <div class="border-b border-gray-100 last:border-0 pb-4 last:pb-0">
                <button @click="toggleSection('price')" class="flex justify-between items-center w-full mb-3">
                    <span class="font-bold text-custom-black dark:text-white">Harga</span>
                    <i :class="openSections.price ? 'rotate-180' : ''" class="transition-transform fa-solid fa-chevron-down text-xs"></i>
                </button>
                <div v-if="openSections.price" class="flex flex-col gap-3">
                    <div class="flex rounded-lg shadow-sm">
                        <span class="inline-flex items-center px-3 rounded-l-lg border border-r-0 border-gray-300 bg-gray-50 text-gray-500 text-xs font-bold">
                            Rp
                        </span>
                        <input type="number" v-model="priceMin" placeholder="Termurah" 
                            class="rounded-none rounded-r-lg bg-white border border-gray-300 text-gray-900 focus:ring-custom-blue focus:border-custom-blue block flex-1 min-w-0 w-full text-sm p-2.5">
                    </div>
                    <div class="flex rounded-lg shadow-sm">
                         <span class="inline-flex items-center px-3 rounded-l-lg border border-r-0 border-gray-300 bg-gray-50 text-gray-500 text-xs font-bold">
                            Rp
                        </span>
                        <input type="number" v-model="priceMax" placeholder="Termahal" 
                            class="rounded-none rounded-r-lg bg-white border border-gray-300 text-gray-900 focus:ring-custom-blue focus:border-custom-blue block flex-1 min-w-0 w-full text-sm p-2.5">
                    </div>
                </div>
            </div>

            <!-- Kondisi -->
            <div class="border-b border-gray-100 last:border-0 pb-4 last:pb-0 pt-4">
                <button @click="toggleSection('condition')" class="flex justify-between items-center w-full mb-3">
                    <span class="font-bold text-custom-black dark:text-white">Kondisi</span>
                    <i :class="openSections.condition ? 'rotate-180' : ''" class="transition-transform fa-solid fa-chevron-down text-xs"></i>
                </button>
                <div v-if="openSections.condition" class="flex flex-col gap-2">
                    <label v-for="cond in conditions" :key="cond.value" class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" :value="cond.value" v-model="selectedConditions" class="w-4 h-4 text-custom-blue rounded focus:ring-custom-blue">
                        <span class="text-custom-grey text-sm">{{ cond.label }}</span>
                    </label>
                </div>
            </div>

            <!-- Rating -->
            <div class="border-b border-gray-100 last:border-0 pb-4 last:pb-0 pt-4">
                <button @click="toggleSection('rating')" class="flex justify-between items-center w-full mb-3">
                    <span class="font-bold text-custom-black dark:text-white">Rating</span>
                    <i :class="openSections.rating ? 'rotate-180' : ''" class="transition-transform fa-solid fa-chevron-down text-xs"></i>
                </button>
                <div v-if="openSections.rating" class="flex flex-col gap-2">
                    <label v-for="level in ratingLevels" :key="level" class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" :true-value="level" :false-value="null" v-model="minRating" class="w-4 h-4 text-custom-blue rounded focus:ring-custom-blue">
                        <div class="flex items-center gap-2">
                            <div class="flex gap-0.5">
                                <i v-for="n in level" :key="n" class="fa-solid fa-star text-xs" style="color: #FFC107;"></i>
                            </div>
                            <span class="text-custom-grey text-sm">{{ level }} ke atas</span>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Terakhir ditambahkan -->
            <div class="pt-4">
                  <button @click="toggleSection('added')" class="flex justify-between items-center w-full mb-3">
                    <span class="font-bold text-custom-black dark:text-white">Terakhir ditambahkan</span>
                    <i :class="openSections.added ? 'rotate-180' : ''" class="transition-transform fa-solid fa-chevron-down text-xs"></i>
                </button>
                 <div v-if="openSections.added" class="flex flex-wrap gap-2">
                    <button v-for="opt in timeOptions" :key="opt.value" 
                        @click="createdSince = (createdSince === opt.value ? null : opt.value)"
                        :class="createdSince === opt.value ? 'bg-custom-blue/10 text-custom-blue border-custom-blue' : 'bg-white text-custom-grey border-gray-300 hover:border-custom-blue'"
                        class="px-3 py-1.5 rounded-full text-xs border font-medium transition-colors">
                        {{ opt.label }}
                    </button>
                 </div>
            </div>
        </div>
    </div>
</template>
