<script setup>
import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

const props = defineProps({
    store: {
        type: Object,
        required: true
    },
    isFollowing: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['follow', 'unfollow']);
const authStore = useAuthStore();

// Safely access nested properties
const storeName = computed(() => props.store?.name || 'Loading...');
const storeLogo = computed(() => props.store?.logo || null);
const storeLocation = computed(() => props.store?.city || 'Unknown Location');
const isVerified = computed(() => props.store?.is_verified || false);
const followersCount = computed(() => props.store?.followers_count || 0);
const rating = computed(() => props.store?.rating || 0);

const handleFollowClick = () => {
    if (props.isFollowing) {
        emit('unfollow');
    } else {
        emit('follow');
    }
};
</script>

<template>
    <div class="w-full relative rounded-[32px] overflow-hidden bg-white/70 backdrop-blur-xl border border-white/50 shadow-xl transition-all duration-300">
        <!-- Background Decoration -->
        <div class="absolute top-0 right-0 w-64 h-64 bg-custom-blue/5 rounded-full blur-3xl -translate-y-1/2 translate-x-1/2 pointer-events-none"></div>
        <div class="absolute bottom-0 left-0 w-40 h-40 bg-orange-100 rounded-full blur-2xl translate-y-1/2 -translate-x-1/2 pointer-events-none"></div>

        <div class="relative z-10 p-5 md:p-8 flex flex-col md:flex-row gap-6 md:gap-6 items-start md:items-center justify-between">
            <!-- Left: Store Info -->
            <div class="flex items-start md:items-center gap-4 md:gap-6 flex-1 min-w-0 w-full">
                <!-- Logo with Ring -->
                <div class="relative flex-shrink-0">
                    <div class="w-16 h-16 md:w-[88px] md:h-[88px] rounded-full p-[3px] bg-gradient-to-br from-custom-blue to-purple-400 shadow-lg">
                        <div class="w-full h-full rounded-full border-[3px] border-white overflow-hidden bg-white">
                            <img :src="storeLogo || '/src/assets/images/logos/logo-blue.png'" 
                                 class="w-full h-full object-cover" 
                                 alt="Store Logo">
                        </div>
                    </div>
                    <div v-if="isVerified" class="absolute -bottom-1 -right-1 bg-white rounded-full p-1 shadow-sm">
                        <img src="@/assets/images/icons/verify-star.svg" class="w-5 h-5 md:w-6 md:h-6" alt="Verified">
                    </div>
                </div>

                <!-- Text Info -->
                <div class="flex flex-col gap-1 md:gap-2 flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                         <h1 class="font-bold text-xl md:text-3xl text-custom-black truncate leading-tight tracking-tight">{{ storeName }}</h1>
                    </div>
                    
                    <div class="flex items-center gap-3 md:gap-4 text-custom-grey text-xs md:text-sm font-medium flex-wrap">
                        <div class="flex items-center gap-1.5">
                            <img src="@/assets/images/icons/location-grey.svg" class="w-3.5 h-3.5 md:w-4 md:h-4 opacity-70" alt="Loc">
                            <span>{{ storeLocation }}</span>
                        </div>
                        <div class="w-1 h-1 rounded-full bg-gray-300"></div>
                        <div class="flex items-center gap-1.5" :class="{'text-green-500': true}">
                            <div class="w-1.5 h-1.5 md:w-2 md:h-2 rounded-full bg-green-500 animate-pulse"></div>
                            <span>Online</span>
                        </div>
                    </div>

                    <!-- Stats Row -->
                    <div class="flex items-center gap-4 md:gap-6 mt-1 md:mt-1">
                        <div class="flex items-center gap-1.5" title="Rating">
                            <img src="@/assets/images/icons/Star-pointy.svg" class="w-4 h-4 md:w-5 md:h-5" alt="Star">
                            <span class="font-bold text-sm md:text-base text-custom-black">{{ rating.toFixed(1) }}</span>
                            <span class="text-[10px] md:text-xs text-custom-grey">/ 5.0</span>
                        </div>
                        <div class="w-[1px] h-3 md:h-4 bg-gray-200"></div>
                        <div class="flex flex-col leading-none">
                            <span class="font-bold text-sm md:text-base text-custom-black">{{ followersCount }}</span>
                            <span class="text-[10px] text-custom-grey uppercase tracking-wide">Followers</span>
                        </div>
                         <div class="w-[1px] h-3 md:h-4 bg-gray-200"></div>
                        <div class="flex flex-col leading-none hidden sm:flex">
                            <span class="font-bold text-sm md:text-base text-custom-black">98%</span>
                            <span class="text-[10px] text-custom-grey uppercase tracking-wide">Chat Reply</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Actions & Search -->
             <div class="flex flex-col gap-3 md:gap-4 w-full md:w-auto md:items-end">
                <div class="flex items-center gap-2 md:gap-3 w-full md:w-auto">
                    <!-- Follow Button -->
                    <button @click="handleFollowClick" 
                        class="flex-1 md:flex-none h-10 md:h-11 px-6 rounded-full font-bold text-sm md:text-base transition-all duration-300 flex items-center justify-center gap-2 border"
                        :class="isFollowing 
                            ? 'bg-transparent border-gray-200 text-custom-grey hover:bg-gray-50' 
                            : 'bg-custom-blue border-custom-blue text-white hover:shadow-lg hover:shadow-blue-500/30'">
                        <span v-if="isFollowing">Following</span>
                        <span v-else>+ Follow</span>
                    </button>

                    <!-- Chat Button -->
                     <RouterLink v-if="authStore.user"
                        :to="{ name: 'user.chat', query: { userId: store?.user?.id } }"
                        class="h-10 w-10 md:h-11 md:w-11 flex-shrink-0 flex items-center justify-center rounded-full bg-white border border-gray-200 text-custom-blue hover:border-custom-blue hover:bg-blue-50 transition-all shadow-sm">
                        <img src="@/assets/images/icons/messages-blue.svg" class="w-5 h-5" alt="Chat">
                    </RouterLink>
                    <RouterLink v-else :to="{ name: 'auth.login' }"
                         class="h-10 w-10 md:h-11 md:w-11 flex-shrink-0 flex items-center justify-center rounded-full bg-white border border-gray-200 text-custom-blue hover:border-custom-blue hover:bg-blue-50 transition-all shadow-sm">
                        <img src="@/assets/images/icons/messages-blue.svg" class="w-5 h-5" alt="Chat">
                    </RouterLink>

                     <!-- Share Button -->
                    <button class="h-10 w-10 md:h-11 md:w-11 flex-shrink-0 flex items-center justify-center rounded-full bg-white border border-gray-200 text-gray-500 hover:text-black hover:border-gray-300 transition-colors shadow-sm">
                         <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
                        </svg>
                    </button>
                </div>

                <!-- Search In Store -->
                <div class="relative w-full md:w-[320px]">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </span>
                    <input type="text" placeholder="Search in this store..." 
                           class="w-full h-10 md:h-11 pl-11 pr-4 rounded-full bg-white/50 border border-white/60 focus:bg-white focus:border-custom-blue focus:ring-2 focus:ring-blue-100 outline-none text-sm transition-all shadow-sm placeholder-gray-400">
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Glassmorphism utility handled by tailwind classes */
</style>
