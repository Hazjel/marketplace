<script setup>
import { useAuthStore } from '@/stores/auth';
import { useTransactionStore } from '@/stores/transaction';
import { storeToRefs } from 'pinia';
import { onMounted, ref, watch } from 'vue';
import { formatRupiah, formatDate } from '@/helpers/format';
import { useToast } from "vue-toastification";

const toast = useToast();
const authStore = useAuthStore();
const { user } = storeToRefs(authStore);

const transactionStore = useTransactionStore();
const { transactions, loading, success, error } = storeToRefs(transactionStore);
const { fetchTransactionsPaginated, updateTransaction } = transactionStore;

const activeTab = ref('all');
const filters = ref({
    search: '',
});

const serverOptions = ref({
    page: 1,
    row_per_page: 10
});

const tabs = [
    { label: 'All Orders', value: 'all' },
    { label: 'Unpaid', value: 'unpaid' },
    { label: 'New Orders', value: 'paid' }, // Paid but not processed
    { label: 'Processing', value: 'processing' },
    { label: 'Delivering', value: 'delivering' },
    { label: 'Completed', value: 'completed' },
    { label: 'Cancelled', value: 'cancelled' },
];

const fetchData = async () => {
    // Basic filter mapping
    let statusFilter = {};
    if (activeTab.value !== 'all') {
        if (activeTab.value === 'unpaid') statusFilter = { payment_status: 'unpaid' };
        else if (activeTab.value === 'paid') statusFilter = { payment_status: 'paid', delivery_status: 'pending' };
        else if (activeTab.value === 'processing') statusFilter = { delivery_status: 'processing' };
        else if (activeTab.value === 'delivering') statusFilter = { delivery_status: 'delivering' };
        else if (activeTab.value === 'completed') statusFilter = { delivery_status: 'completed' };
        else if (activeTab.value === 'cancelled') statusFilter = { delivery_status: 'cancelled' };
    }

    // Since we don't have extensive backend filtering for status in getAllPaginated (it accepts basic filters),
    // we might receive all and filter client side OR current backend implementation limits.
    // Ideally backend should support 'status' filter.
    // For now, let's just fetch and let backend return all for store, trying to pass param if backend supports dynamic filters
    // Looking at controller `getAllPaginated`, it supports: min_price, max_price, condition, city, min_rating, stock_status, created_since.
    // It DOES NOT seem to support `delivery_status` or `payment_status` directly in the `getAllPaginated` validation.
    // LIMITATION: Sprint 3 might need backend update for `delivery_status` filter.
    // WORKAROUND: For now, fetch all and filter client-side (not ideal for huge data) or rely on searching code.
    // ACTUALLY: Let's assume we update backend or use what we have.
    // I will implement client-side filter for now to unblock, but note it for refactor.
    
    await fetchTransactionsPaginated({
        ...serverOptions.value,
        ...filters.value,
    });
};

// Computed filtered transactions because API doesn't support status filter yet
import { computed } from 'vue';
const filteredTransactions = computed(() => {
    if (!transactions.value) return [];
    if (activeTab.value === 'all') return transactions.value;
    
    return transactions.value.filter(t => {
        if (activeTab.value === 'unpaid') return t.payment_status === 'unpaid';
        if (activeTab.value === 'paid') return t.payment_status === 'paid' && t.delivery_status === 'pending';
        if (activeTab.value === 'processing') return t.delivery_status === 'processing';
        if (activeTab.value === 'delivering') return t.delivery_status === 'delivering';
        if (activeTab.value === 'completed') return t.delivery_status === 'completed';
        if (activeTab.value === 'cancelled') return t.delivery_status === 'cancelled' || t.payment_status === 'failed';
        return true;
    });
});

const showResiModal = ref(false);
const selectedTransactionId = ref(null);
const resiInput = ref('');

const openResiModal = (id) => {
    selectedTransactionId.value = id;
    resiInput.value = '';
    showResiModal.value = true;
};

const submitResi = async () => {
    if (!resiInput.value) return;
    
    await updateTransaction(selectedTransactionId.value, {
        delivery_status: 'delivering',
        tracking_number: resiInput.value
    });
    
    showResiModal.value = false;
    fetchData(); 
};

const handleAccept = async (id) => {
    await updateTransaction(id, {
        delivery_status: 'processing'
    });
    fetchData();
};

const handleReject = async (id) => {
    if(confirm('Are you sure you want to reject this order? Stock will be restored.')) {
        await updateTransaction(id, {
            delivery_status: 'cancelled'
        });
        fetchData();
    }
};

onMounted(fetchData);

watch(activeTab, () => {
    serverOptions.value.page = 1; // Reset to page 1
    // Re-fetch mostly to refresh data, even if filtering is client-side for now
    fetchData();
});

watch(success, (val) => {
    if (val) {
        toast.success(val);
        transactionStore.success = null; // reset
    }
});
watch(error, (val) => {
    if (val) {
        toast.error(val);
        transactionStore.error = null;
    }
});

</script>

<template>
    <div class="flex flex-col gap-6 animate-fade-in-up">
        <div class="flex items-center justify-between">
            <h1 class="font-bold text-2xl">Incoming Orders</h1>
        </div>

        <!-- Tabs -->
        <div class="flex items-center gap-3 overflow-x-auto pb-2 hide-scrollbar">
            <button v-for="tab in tabs" :key="tab.value"
                @click="activeTab = tab.value"
                class="px-5 py-3 rounded-full font-semibold whitespace-nowrap transition-all duration-300 border"
                :class="activeTab === tab.value ? 'bg-custom-black text-white border-custom-black' : 'bg-white text-custom-grey border-custom-stroke hover:border-custom-black'">
                {{ tab.label }}
            </button>
        </div>

        <!-- List -->
        <div class="flex flex-col gap-4">
            <div v-for="t in filteredTransactions" :key="t.id" class="flex flex-col md:flex-row gap-5 p-5 bg-white rounded-[20px] border border-custom-stroke">
                <!-- Product Info -->
                <div class="flex-1 flex flex-col gap-4">
                    <div class="flex items-center gap-2">
                        <span class="font-bold text-lg">{{ t.code }}</span>
                        <span class="px-3 py-1 rounded-full text-xs font-bold uppercase"
                            :class="{
                                'bg-yellow-100 text-yellow-600': t.payment_status === 'unpaid',
                                'bg-green-100 text-green-600': t.payment_status === 'paid',
                                'bg-red-100 text-red-600': t.payment_status === 'failed',
                            }">
                            {{ t.payment_status }}
                        </span>
                        <span class="px-3 py-1 rounded-full text-xs font-bold uppercase"
                             :class="{
                                'bg-gray-100 text-gray-600': t.delivery_status === 'pending',
                                'bg-blue-100 text-blue-600': t.delivery_status === 'processing',
                                'bg-purple-100 text-purple-600': t.delivery_status === 'delivering',
                                'bg-green-100 text-green-600': t.delivery_status === 'completed',
                                'bg-red-100 text-red-600': t.delivery_status === 'cancelled',
                            }">
                            {{ t.delivery_status }}
                        </span>
                    </div>
                    
                    <div v-for="detail in t.transaction_details" :key="detail.id" class="flex items-start gap-4">
                        <img :src="detail.product.product_images?.find(i => i.is_thumbnail)?.image || detail.product.product_images?.[0]?.image" 
                             class="w-16 h-16 rounded-xl object-cover bg-gray-100" alt="">
                        <div>
                            <p class="font-bold text-custom-black line-clamp-1">{{ detail.product.name }}</p>
                            <p class="text-custom-grey text-sm">{{ detail.qty }} x {{ formatRupiah(detail.product.price) }}</p>
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-2 mt-auto">
                        <img src="@/assets/images/icons/location-grey.svg" class="w-4 h-4" alt="">
                        <p class="text-sm text-custom-grey">{{ t.city }}, {{ t.postal_code }}</p>
                    </div>
                </div>

                <!-- Customer & Actions -->
                <div class="flex flex-col justify-between items-end gap-4 min-w-[200px]">
                    <div class="text-right">
                        <p class="text-sm text-custom-grey">Total Amount</p>
                        <p class="font-bold text-xl text-custom-blue">{{ formatRupiah(t.grand_total) }}</p>
                        <p class="text-xs text-custom-grey mt-1">{{ formatDate(t.created_at) }}</p>
                    </div>

                    <div class="flex flex-col gap-2 w-full md:w-auto">
                        <!-- Actions for New Paid Orders -->
                        <template v-if="t.payment_status === 'paid' && t.delivery_status === 'pending'">
                             <button @click="handleAccept(t.id)" class="px-6 py-3 rounded-full bg-custom-black text-white font-semibold text-sm hover:bg-black/80 transition-300">
                                Accept Order
                            </button>
                            <button @click="handleReject(t.id)" class="px-6 py-3 rounded-full bg-gray-100 text-custom-red font-semibold text-sm hover:bg-red-50 transition-300">
                                Reject Order
                            </button>
                        </template>

                        <!-- Actions for Processing Orders -->
                        <template v-if="t.delivery_status === 'processing'">
                            <button @click="openResiModal(t.id)" class="px-6 py-3 rounded-full bg-custom-blue text-white font-semibold text-sm hover:bg-blue-600 transition-300">
                                Input Resi
                            </button>
                        </template>
                        
                        <!-- Tracking Info -->
                        <div v-if="t.tracking_number" class="text-right bg-gray-50 p-3 rounded-xl border border-dashed border-gray-300">
                             <p class="text-xs text-custom-grey">Tracking Number</p>
                             <p class="font-bold font-mono">{{ t.tracking_number }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="filteredTransactions.length === 0" class="flex flex-col items-center justify-center p-10 bg-white rounded-[20px] border border-custom-stroke">
                <img src="@/assets/images/icons/note-remove-grey.svg" class="size-16 opacity-50 mb-4" alt="">
                <p class="font-semibold text-custom-grey">No orders found.</p>
            </div>
        </div>

        <!-- Resi Modal -->
        <Teleport to="body">
            <Transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100" leave-active-class="transition duration-150 ease-in" leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-95">
            <div v-if="showResiModal" class="fixed inset-0 z-[999] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showResiModal = false"></div>
                <div class="relative bg-white rounded-3xl p-6 w-full max-w-md shadow-2xl">
                    <h3 class="font-bold text-xl mb-4">Input Tracking Number</h3>
                    <div class="flex flex-col gap-4">
                        <label class="flex flex-col gap-2">
                            <span class="text-sm font-semibold text-custom-grey">Resi Number</span>
                            <input v-model="resiInput" type="text" class="custom-input h-12 pt-0 pb-0 flex items-center" placeholder="JP...">
                        </label>
                        <div class="flex gap-3 mt-2">
                            <button @click="showResiModal = false" class="flex-1 py-3 rounded-full bg-gray-100 font-semibold text-custom-grey">Cancel</button>
                            <button @click="submitResi" class="flex-1 py-3 rounded-full bg-custom-black text-white font-semibold">Submit</button>
                        </div>
                    </div>
                </div>
            </div>
            </Transition>
        </Teleport>
    </div>
</template>
