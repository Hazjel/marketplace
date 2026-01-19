<script setup>
import { onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { axiosInstance } from '@/plugins/axios';
import { useToast } from "vue-toastification";

const toast = useToast();
const authStore = useAuthStore();
const addresses = ref([]);
const loading = ref(true);

const fetchAddresses = async () => {
    loading.value = true;
    try {
        const response = await axiosInstance.get('/address');
        addresses.value = response.data.data;
    } catch (error) {
        console.error('Error fetching addresses:', error);
    } finally {
        loading.value = false;
    }
};

const deleteAddress = async (id) => {
    if (!confirm('Are you sure you want to delete this address?')) return;
    
    try {
        await axiosInstance.delete(`/address/${id}`);
        toast.success('Address deleted successfully');
        fetchAddresses();
    } catch (error) {
        toast.error('Failed to delete address');
    }
};

onMounted(() => {
    fetchAddresses();
});
</script>

<template>
    <div class="flex flex-col gap-6">
        <div class="flex items-center justify-between">
            <h1 class="font-bold text-2xl">My Addresses</h1>
            <RouterLink :to="{ name: 'user.settings.address.create' }" 
                class="flex items-center gap-2 px-5 py-3 bg-custom-blue text-white rounded-xl font-bold hover:bg-blue-600 transition-colors">
                <i class="fa-solid fa-plus"></i>
                Add New Address
            </RouterLink>
        </div>

        <div v-if="loading" class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div v-for="i in 2" :key="i" class="h-40 bg-gray-100 rounded-2xl animate-pulse"></div>
        </div>

        <div v-else-if="addresses.length === 0" class="flex flex-col items-center justify-center py-20 bg-gray-50 rounded-2xl border-2 border-dashed border-gray-200">
            <div class="size-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                <i class="fa-solid fa-map-location-dot text-2xl text-custom-grey"></i>
            </div>
            <p class="font-bold text-lg text-custom-black">No Addresses Yet</p>
            <p class="text-custom-grey mb-6">Add an address to speed up your checkout.</p>
        </div>

        <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div v-for="address in addresses" :key="address.id" 
                class="relative bg-white border border-custom-stroke rounded-2xl p-6 hover:shadow-lg transition-all duration-300 group">
                
                <div v-if="address.is_primary" class="absolute top-4 right-4 px-3 py-1 bg-custom-blue/10 text-custom-blue text-xs font-bold rounded-full">
                    Primary Address
                </div>

                <div class="flex flex-col gap-1 mb-4">
                    <p class="font-bold text-lg text-custom-black">{{ address.label }}</p>
                    <p class="font-semibold text-custom-black">{{ address.recipient_name }}</p>
                    <p class="text-custom-grey">{{ address.phone }}</p>
                </div>

                <p class="text-sm text-custom-grey leading-relaxed line-clamp-3 mb-6 min-h-[60px]">
                    {{ address.address }}, {{ address.city }}, {{ address.postal_code }}
                </p>

                <div class="flex items-center gap-3 mt-auto">
                    <RouterLink :to="{ name: 'user.settings.address.edit', params: { id: address.id } }" 
                        class="flex-1 py-2.5 text-center border border-custom-stroke rounded-xl text-sm font-bold text-custom-black hover:bg-gray-50 transition-colors">
                        Edit
                    </RouterLink>
                    <button @click="deleteAddress(address.id)" 
                        class="flex-1 py-2.5 text-center border border-custom-stroke rounded-xl text-sm font-bold text-custom-red hover:bg-red-50 hover:border-red-100 transition-colors">
                        Delete
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
