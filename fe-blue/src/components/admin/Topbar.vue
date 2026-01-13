<script setup>
import { useRoute } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { storeToRefs } from 'pinia';

const route = useRoute();

const authStore = useAuthStore();
const { user } = storeToRefs(authStore);
const { logout } = authStore;
</script>

<template>
    <div id="Top-Bar" class="flex items-center w-full gap-4 md:gap-6 mt-8 mb-6">
        <!-- Sidebar Toggle (Mobile) -->
        <button @click="$emit('toggleSidebar')" class="flex md:hidden items-center justify-center size-14 shrink-0 bg-white rounded-3xl">
             <img src="@/assets/images/icons/menu-grey.svg" class="size-6" alt="menu">
        </button>

        <div class="flex items-center gap-6 min-h-[102px] h-auto bg-white w-full rounded-3xl p-[18px]">
            <div class="flex flex-col gap-2 w-full">
                <h1 class="font-bold text-2xl capitalize">{{ route.meta.title }}</h1>
                <p class="flex items-center gap-1 font-semibold text-custom-grey leading-none">
                    View Your {{ route.meta.title }}
                </p>
            </div>
        </div>
        <div class="hidden md:flex items-center gap-3 min-h-[102px] h-auto bg-white w-fit rounded-3xl p-[18px]">
            <div class="flex rounded-full overflow-hidden size-14">
                <img :src="user?.profile_picture" class="size-full object-cover" alt="photo">
            </div>
            <div class="flex flex-col gap-[6px] min-w-[155px] w-fit">
                <p class="font-semibold text-lg leading-tight">{{ user?.name }}</p>
                <div class="flex flex-col items-start">
                    <p class="flex items-center gap-1 font-bold text-custom-blue text-sm uppercase leading-none">
                        {{ user?.role === 'admin' ? 'ADMIN' : authStore.currentMode }}
                    </p>
                </div>
            </div>
            <a @click="logout" class="flex w-6">
                <img src="@/assets/images/icons/logout.svg" class="flex size-6 shrink-0" alt="icon">
            </a>
        </div>
    </div>
</template>