<script setup>
import { can, hasRole } from '@/helpers/permissionHelper';
import { computed, ref, watch, onMounted } from 'vue';
import { useRoute } from 'vue-router';

const route = useRoute()

const props = defineProps({
    item: {
        type: Object,
        required: true
    }
})

const isActive = computed(() => {
    return route.path === props.item.path || route.path.startsWith(`${props.item.path}/`)
})

const hasActiveChild = computed(() => {
    if (!props.item.children) return false;
    return props.item.children.some(child => {
        return route.path === child.path || route.path.startsWith(`${child.path}/`)
    })
})


const isOpen = ref(false)

onMounted(() => {
    if (hasActiveChild.value) {
        isOpen.value = true
    }
})

watch(route, () => {
    if (hasActiveChild.value) {
        isOpen.value = true
    }

    if (!hasActiveChild.value) {
        isOpen.value = false
    }
})
</script>


<template>
    <li class="group" v-if="!item.children && can(item.permission) && hasRole(item.role)"
        :class="{ 'active': isActive }">
        <RouterLink :to="item.path"
            class="flex items-center w-full min-h-14 gap-2 rounded-2xl overflow-hidden py-[10px] pl-4 group-[&.active]:bg-custom-blue/10 transition-300">
            <div class="relative flex size-6 shrink-0">
                <img :src="item.iconDefault"
                    class="size-6 absolute opacity-100 group-[&.active]:opacity-0 transition-300" alt="icon">
                <img :src="item.iconActive"
                    class="size-6 absolute opacity-0 group-[&.active]:opacity-100 transition-300" alt="icon">
            </div>
            <p class="font-medium group-[&.active]:text-custom-blue transition-300 w-full">
                {{ item.label }}
            </p>
            <div v-if="item.badge"
                class="text-white text-[10px] font-bold px-1.5 h-5 min-w-5 flex items-center justify-center rounded-full ml-auto mr-2" style="background-color: red;">
                {{ item.badge > 99 ? '99+' : item.badge }}
            </div>
            <div class="w-2 h-9 shrink-0 rounded-l-xl bg-custom-blue hidden group-[&.active]:flex transition-300">
            </div>
        </RouterLink>
    </li>

    <li class="group flex flex-col"
        v-if="item.children && item.children.some(child => can(child.permission) && hasRole(child.role))">
        <button data-accordion-type="nav"
            :data-expand="`accordion-${(item.path || item.label || 'default').replace(/[\/\s]/g, '-')}`"
            @click="isOpen = !isOpen"
            class="flex items-center w-full min-h-14 gap-2 rounded-2xl overflow-hidden py-[10px] pl-4 group-[&.active]:bg-custom-blue/10 transition-300"
            :class="{ 'opened': isOpen }">
            <div class="relative flex size-6 shrink-0">
                <img :src="item.iconDefault"
                    class="size-6 absolute opacity-100 group-[&.active]:opacity-0 transition-300" alt="icon">
                <img :src="item.iconActive"
                    class="size-6 absolute opacity-0 group-[&.active]:opacity-100 transition-300" alt="icon">
            </div>
            <p class="font-medium group-[&.active]:text-custom-blue transition-300 w-full text-left">
                {{ item.label }}
            </p>
            <img src="@/assets/images/icons/arrow-circle-up-black.svg" class="size-6 flex shrink-0 transition-300"
                :class="{ 'rotate-180': isOpen }" alt="icon">
        </button>
        <div :id="`accordion-${(item.path || item.label || 'default').replace(/[\/\s]/g, '-')}`" class="flex"
            v-show="isOpen">
            <div class="flex w-[56px] shrink-0 justify-end items-start relative">
                <div class="branch-line"
                    v-for="n in Math.max(0, item.children.filter(child => can(child.permission) && hasRole(child.role)).length - 1)"
                    :key="n"></div>
            </div>
            <ul class="flex flex-col gap-1 w-full">
                <li class="group relative" :class="{ 'active': route.path.startsWith(child.path) }"
                    v-for="child in item.children" :key="child.path">
                    <div class="curve-branch" v-if="can(child.permission) && hasRole(child.role)"></div>
                    <RouterLink :to="child.path"
                        class="flex items-center w-full min-h-14 gap-2 rounded-2xl overflow-hidden py-[10px] pl-4 group-[&.active]:bg-custom-blue/10 transition-300"
                        :class="{ 'active': route.path.startsWith(child.path) }"
                        v-if="can(child.permission) && hasRole(child.role)">
                        <div class="relative flex size-6 shrink-0">
                            <img :src="child.iconDefault"
                                class="size-6 absolute opacity-100 group-[&.active]:opacity-0 transition-300"
                                alt="icon">
                            <img :src="child.iconActive"
                                class="size-6 absolute opacity-0 group-[&.active]:opacity-100 transition-300"
                                alt="icon">
                        </div>
                        <p class="font-medium text-custom-grey group-[&.active]:text-custom-blue transition-300 w-full">
                            {{ child.label }}
                        </p>
                        <div
                            class="w-2 h-9 shrink-0 rounded-l-xl bg-custom-blue hidden group-[&.active]:flex transition-300">
                        </div>
                    </RouterLink>
                </li>
            </ul>
        </div>
    </li>
</template>

<style scoped>
.branch-line {
    width: 2px;
    height: 100%;
    background-color: #e5e7eb;
    position: absolute;
    top: 0;
    left: 50%;
    transform: translateX(-50%);
    border-radius: 1px;
}

.branch-line:last-child {
    height: 55%;
}

.curve-branch {
    position: absolute;
    left: 0;
    top: 0;
    width: 28px;
    height: 32px;
    border-left: 2px solid #e5e7eb;
    border-bottom: 2px solid #e5e7eb;
    border-bottom-left-radius: 16px;
    background: transparent;
    z-index: 1;
    transform: translateX(-104%);
}
</style>