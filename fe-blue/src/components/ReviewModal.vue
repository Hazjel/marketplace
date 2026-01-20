<script setup>
import { ref, watch, computed } from 'vue';
import { axiosInstance } from '@/plugins/axios';
import { useToast } from "vue-toastification";

const props = defineProps({
    show: {
        type: Boolean,
        default: false
    },
    transactionId: {
        type: String,
        required: true
    },
    productId: {
        type: String,
        required: true
    },
    productName: {
        type: String,
        default: 'Produk'
    },
    productImage: {
        type: String,
        default: ''
    }
});

const emit = defineEmits(['close', 'submitted']);
const toast = useToast();

const form = ref({
    rating: 0,
    review: '',
    is_anonymous: false,
    attachments: []
});

const loading = ref(false);
const previewImages = ref([]);

// Reset form when modal opens
watch(() => props.show, (val) => {
    if (val) {
        form.value = { rating: 0, review: '', is_anonymous: false, attachments: [] };
        previewImages.value = [];
    }
});

const handleFileChange = (e) => {
    const files = Array.from(e.target.files);
    if (files.length + form.value.attachments.length > 5) {
        toast.error("Maksimal 5 foto/video");
        return;
    }
    
    form.value.attachments = [...form.value.attachments, ...files];

    // Generate Previews
    files.forEach(file => {
        const reader = new FileReader();
        reader.onload = (e) => {
            previewImages.value.push({
                url: e.target.result,
                type: file.type.startsWith('video') ? 'video' : 'image'
            });
        };
        reader.readAsDataURL(file);
    });
};

const removeFile = (index) => {
    form.value.attachments.splice(index, 1);
    previewImages.value.splice(index, 1);
};

const submitReview = async () => {
    if (form.value.rating === 0) {
        toast.warning("Silakan pilih rating bintang terlebih dahulu");
        return;
    }

    loading.value = true;
    try {
        const formData = new FormData();
        formData.append('transaction_id', props.transactionId);
        formData.append('product_id', props.productId);
        formData.append('rating', form.value.rating);
        formData.append('review', form.value.review);
        formData.append('is_anonymous', form.value.is_anonymous ? 1 : 0);

        form.value.attachments.forEach((file) => {
            formData.append('attachments[]', file);
        });

        await axiosInstance.post('product-review', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });

        toast.success("Terima kasih! Ulasan Anda berhasil dikirim.");
        emit('submitted');
        emit('close');
    } catch (error) {
        console.error(error);
        toast.error(error.response?.data?.meta?.message || "Gagal mengirim ulasan");
    } finally {
        loading.value = false;
    }
};

const tempRating = ref(0);
const setRating = (r) => {
    form.value.rating = r;
};
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
        <div class="bg-white rounded-[24px] w-full max-w-lg overflow-hidden flex flex-col max-h-[90vh]">
            <!-- Header -->
            <div class="flex items-center justify-between p-6 border-b border-gray-100">
                <h3 class="text-xl font-bold text-custom-black">Tulis Ulasan</h3>
                <button @click="$emit('close')" class="p-2 hover:bg-gray-100 rounded-full transition-colors">
                    <img src="@/assets/images/icons/close-circle-black.svg" class="w-6 h-6 opacity-60" alt="Close" onerror="this.style.display='none'">
                    <span v-if="$el?.querySelector?.('img')?.style?.display === 'none'" class="text-2xl text-gray-500">&times;</span>
                </button>
            </div>

            <!-- Body (Scrollable) -->
            <div class="p-6 overflow-y-auto custom-scrollbar flex flex-col gap-6">
                <!-- Product Info -->
                <div class="flex items-center gap-4 bg-gray-50 p-4 rounded-xl border border-gray-100">
                    <img :src="productImage || '/src/assets/images/photos/photo-1.png'" 
                         class="w-16 h-16 rounded-lg object-cover bg-white" alt="Product">
                    <div>
                        <p class="font-bold text-custom-black line-clamp-2">{{ productName }}</p>
                    </div>
                </div>

                <!-- Star Rating -->
                <div class="flex flex-col items-center gap-2">
                    <p class="font-semibold text-custom-grey">Bagaimana kualitas produk ini?</p>
                    <div class="flex gap-2">
                        <button v-for="i in 5" :key="i" 
                                @click="setRating(i)"
                                @mouseenter="tempRating = i"
                                @mouseleave="tempRating = 0"
                                class="transition-transform hover:scale-110">
                            <!-- Star Icon SVGs -->
                            <svg v-if="i <= (tempRating || form.rating)" xmlns="http://www.w3.org/2000/svg" class="w-10 h-10 text-custom-orange fill-current" viewBox="0 0 24 24">
                                <path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>
                            </svg>
                            <svg v-else xmlns="http://www.w3.org/2000/svg" class="w-10 h-10 text-gray-300 fill-current" viewBox="0 0 24 24">
                                <path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>
                            </svg>
                        </button>
                    </div>
                    <p class="text-sm font-medium text-custom-orange h-5">
                        {{ ['','Sangat Buruk', 'Buruk', 'Cukup', 'Baik', 'Sangat Baik'][form.rating] || '' }}
                    </p>
                </div>

                <!-- Textarea -->
                <div class="flex flex-col gap-2">
                    <label class="font-bold text-sm text-custom-black">Ulasan Anda</label>
                    <textarea v-model="form.review" rows="4" 
                              class="w-full rounded-2xl border border-custom-stroke p-4 focus:ring-2 focus:ring-custom-blue outline-none resize-none transition-all"
                              placeholder="Ceritakan kepuasan Anda tentang kualitas produk dan pelayanan kami..."></textarea>
                </div>

                <!-- File Upload -->
                <div class="flex flex-col gap-2">
                    <label class="font-bold text-sm text-custom-black">Foto / Video (Opsional)</label>
                    <div class="flex flex-wrap gap-2">
                         <div v-for="(media, idx) in previewImages" :key="idx" class="relative w-20 h-20 rounded-xl overflow-hidden group border border-gray-200">
                             <img v-if="media.type === 'image'" :src="media.url" class="w-full h-full object-cover">
                             <video v-else :src="media.url" class="w-full h-full object-cover"></video>
                             <button @click="removeFile(idx)" class="absolute top-1 right-1 bg-red-500 text-white rounded-full p-0.5 w-5 h-5 flex items-center justify-center text-xs opacity-0 group-hover:opacity-100 transition-opacity">
                                 &times;
                             </button>
                         </div>
                         <label v-if="form.attachments.length < 5" class="w-20 h-20 rounded-xl border-2 border-dashed border-gray-300 flex flex-col items-center justify-center cursor-pointer hover:border-custom-blue hover:bg-blue-50 transition-colors">
                             <span class="text-2xl text-gray-400">+</span>
                             <span class="text-[10px] text-gray-500">Add</span>
                             <input type="file" accept="image/*,video/*" multiple class="hidden" @change="handleFileChange">
                         </label>
                    </div>
                </div>

                <!-- Anonymous Checkbox -->
                <label class="flex items-center gap-3 cursor-pointer select-none">
                    <input type="checkbox" v-model="form.is_anonymous" class="w-5 h-5 rounded border-gray-300 text-custom-blue focus:ring-custom-blue">
                    <span class="text-sm text-custom-grey">Sembunyikan nama saya pada ulasan ini</span>
                </label>
            </div>

            <!-- Footer -->
            <div class="p-6 border-t border-gray-100 flex gap-4">
                <button @click="$emit('close')" class="flex-1 py-3 px-6 rounded-full font-bold text-custom-black bg-gray-100 hover:bg-gray-200 transition-300">
                    Batal
                </button>
                <button @click="submitReview" :disabled="loading" 
                        class="flex-1 py-3 px-6 rounded-full font-bold text-white bg-custom-blue hover:shadow-lg hover:shadow-custom-blue/50 transition-300 disabled:opacity-50 disabled:cursor-not-allowed">
                    {{ loading ? 'Mengirim...' : 'Kirim Ulasan' }}
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background-color: #E5E7EB;
    border-radius: 20px;
}
</style>
