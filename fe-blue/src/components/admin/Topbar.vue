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
    <div id="Top-Bar" class="flex items-center w-full gap-6 mt-[30px] mb-6">
        <div class="flex items-center gap-6 h-[102px] bg-white w-full rounded-3xl p-[18px]">
            <div class="flex flex-col gap-2 w-full">
                <h1 class="font-bold text-2xl capitalize">{{ route.meta.title }}</h1>
                <p class="flex items-center gap-1 font-semibold text-custom-grey leading-none">
                    View Your {{ route.meta.title }}
                </p>
            </div>
        </div>
        <div class="flex items-center gap-3 h-[102px] bg-white w-fit rounded-3xl p-[18px]">
            <div class="flex rounded-full overflow-hidden size-14">
                <img :src="user?.profile_picture" class="size-full object-cover" alt="photo">
            </div>
            <div class="flex flex-col gap-[6px] min-w-[155px] w-fit">
                <p class="font-semibold text-lg leading-tight">{{ user?.name }}</p>
                <p class="flex items-center gap-1 font-semibold text-custom-grey text-lg leading-none">
                    <img src="@/assets/images/icons/user-grey.svg" class="size-[18px]" alt="icon"> 
                    {{ user?.role }}
                </p>
            </div>
            <a @click="logout" class="flex w-6">
                <img src="@/assets/images/icons/logout.svg" class="flex size-6 shrink-0" alt="icon">
            </a>
        </div>
    </div>
</template>