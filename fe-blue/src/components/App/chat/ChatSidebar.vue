<script setup>
import { useChatStore } from '@/stores/chat';
import { storeToRefs } from 'pinia';
import { onMounted } from 'vue';

const chatStore = useChatStore();
const { contacts, loadingContacts, activeUser } = storeToRefs(chatStore);
const { fetchContacts, setActiveUser } = chatStore;

onMounted(() => {
    fetchContacts();
});

const selectUser = (user) => {
    setActiveUser(user);
};
</script>

<template>
    <div class="flex flex-col w-[320px] border-r border-custom-stroke h-full bg-white">
        <div class="p-6 border-b border-custom-stroke">
            <h2 class="font-bold text-xl">Messages</h2>
        </div>
        <div class="flex flex-col flex-1 overflow-y-auto">
            <div v-if="loadingContacts" class="flex justify-center py-10">
                <div class="size-8 border-4 border-custom-blue border-t-transparent rounded-full animate-spin"></div>
            </div>

            <div v-else-if="contacts.length === 0" class="p-6 text-center text-custom-grey">
                No conversations yet.
            </div>

            <button v-for="user in contacts" :key="user.id" @click="selectUser(user)" :class="['flex items-center gap-4 p-4 hover:bg-custom-background transition-all text-left border-b border-custom-stroke last:border-0',
                activeUser?.id === user.id ? 'bg-custom-background' : '']">
                <div class="size-12 rounded-full overflow-hidden bg-gray-200 shrink-0">
                    <img :src="user.profile_picture || 'https://ui-avatars.com/api/?name=' + user.name"
                        class="size-full object-cover" alt="avatar">
                </div>
                <div class="flex flex-col flex-1 min-w-0">
                    <h3 class="font-bold text-sm truncate">{{ user.name }}</h3>
                    <p class="text-xs text-custom-grey truncate">Click to chat</p>
                </div>
            </button>
        </div>
    </div>
</template>
