<script setup>
import ChatSidebar from '@/components/App/chat/ChatSidebar.vue';
import ChatRoom from '@/components/App/chat/ChatRoom.vue';
import echo from '@/plugins/echo';
import { onMounted, onUnmounted, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useChatStore } from '@/stores/chat';
import { storeToRefs } from 'pinia';


const authStore = useAuthStore();
const chatStore = useChatStore();
const { user } = storeToRefs(authStore);
const { contacts } = storeToRefs(chatStore);
const { fetchContacts, setActiveUser, fetchUserById } = chatStore;
const route = useRoute();

// Function to setup listener
const setupEchoListener = (userId) => {
    if (!userId) return;

    console.log(`Listening on chat.${userId}`);
    echo.private(`chat.${userId}`)
        .listen('MessageSent', (e) => {
            console.log('Message received:', e.message);
            chatStore.pushMessage(e.message);
        });
};

const handleInitialParams = async () => {
    const targetUserId = route.query.userId;
    if (targetUserId) {
        // Check if in contacts
        const existingContact = contacts.value.find(c => c.id == targetUserId); // Use == for type coercion if targetUserId is string
        if (existingContact) {
            setActiveUser(existingContact);
        } else {
            // Fetch user info
            const targetUser = await fetchUserById(targetUserId);
            if (targetUser) {
                setActiveUser(targetUser);
            }
        }
    }
};

onMounted(async () => {
    if (user.value) {
        setupEchoListener(user.value.id);

        // Wait for contacts to be fetched first? Or parallel?
        // Let's ensure we fetch contacts first
        await fetchContacts();
        await handleInitialParams();
    }
});

// Watch for user changes (e.g. late login)
watch(user, (newUser) => {
    if (newUser) {
        setupEchoListener(newUser.id);
    }
});

onUnmounted(() => {
    if (user.value) {
        echo.leave(`chat.${user.value.id}`);
    }
});
</script>

<template>
    <div class="w-full max-w-[1280px] mx-auto px-[52px] my-8">
        <div
            class="flex h-[calc(100vh-180px)] w-full border border-custom-stroke rounded-3xl overflow-hidden shadow-lg shadow-[#00000005]">
            <ChatSidebar />
            <ChatRoom />
        </div>
    </div>
</template>
