<script setup>
defineProps({
    meta: {
        type: Object,
        required: true,
    },
    serverOptions: {
        type: Object,
        required: true,
    },
})

// Function to generate page numbers with ellipsis
const getPageNumbers = (currentPage, lastPage, maxVisible = 5) => {
    const pages = []

    if (lastPage <= maxVisible) {
        // If total pages is less than max visible, show all pages
        for (let i = 1; i <= lastPage; i++) {
            pages.push(i)
        }
    } else {
        // Always show first page
        pages.push(1)

        if (currentPage <= 3) {
            // Show pages 1, 2, 3, 4, 5, ..., lastPage
            for (let i = 2; i <= Math.min(5, lastPage - 1); i++) {
                pages.push(i)
            }
            if (lastPage > 5) {
                pages.push('...')
            }
        } else if (currentPage >= lastPage - 2) {
            // Show pages 1, ..., lastPage-4, lastPage-3, lastPage-2, lastPage-1, lastPage
            if (lastPage > 5) {
                pages.push('...')
            }
            for (let i = Math.max(2, lastPage - 4); i <= lastPage; i++) {
                pages.push(i)
            }
        } else {
            // Show pages around current page: 1, ..., currentPage-1, currentPage, currentPage+1, ..., lastPage
            pages.push('...')
            for (let i = currentPage - 1; i <= currentPage + 1; i++) {
                pages.push(i)
            }
            pages.push('...')
        }

        // Always show last page if not already included
        if (!pages.includes(lastPage)) {
            pages.push(lastPage)
        }
    }

    return pages
}
</script>

<template>
    <nav id="Pagination">
        <ul class="flex items-center gap-1 md:gap-3">
            <li class="group">
                <button @click="serverOptions.page = meta.current_page - 1" :disabled="meta.current_page === 1"
                    class="flex size-9 md:size-11 shrink-0 rounded-full items-center justify-center bg-custom-blue/10 text-custom-blue group-[&.active]:bg-custom-blue group-[&.active]:text-white font-semibold">
                    <img src="@/assets/images/icons/arrow-right-no-tail-blue.svg"
                        class="size-5 md:size-6 group-has-[:disabled]:opacity-20 rotate-180" alt="icon">
                </button>
            </li>

            <li v-for="page in getPageNumbers(meta.current_page, meta.last_page)" :key="page" class="group"
                :class="{ 'active': page === meta.current_page }">
                <button v-if="page !== '...'" @click="serverOptions.page = page"
                    class="flex size-9 md:size-11 shrink-0 rounded-full items-center justify-center bg-custom-blue/10 text-custom-blue group-[&.active]:bg-custom-blue group-[&.active]:text-white font-semibold text-sm md:text-base">
                    {{ page }}
                </button>
                <span v-else
                    class="flex size-9 md:size-11 shrink-0 rounded-full items-center justify-center text-gray-500 font-semibold text-sm md:text-base">
                    {{ page }}
                </span>
            </li>

            <li class="group">
                <button @click="serverOptions.page = meta.current_page + 1"
                    :disabled="meta.current_page === meta.last_page"
                    class="flex size-9 md:size-11 shrink-0 rounded-full items-center justify-center bg-custom-blue/10 text-custom-blue group-[&.active]:bg-custom-blue group-[&.active]:text-white font-semibold">
                    <img src="@/assets/images/icons/arrow-right-no-tail-blue.svg"
                        class="size-5 md:size-6 group-has-[:disabled]:opacity-20" alt="icon">
                </button>
            </li>
        </ul>
    </nav>
</template>