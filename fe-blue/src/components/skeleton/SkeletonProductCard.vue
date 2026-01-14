<template>
    <div class="flex flex-col rounded-t-[20px] overflow-hidden h-full">
        <!-- Thumbnail -->
        <div class="w-full aspect-square bg-gray-200 animate-pulse"></div>

        <!-- Content -->
        <div class="flex flex-col flex-1 p-[14px] pt-4 gap-[14px] bg-white border border-custom-stroke border-t-0 rounded-b-[20px]">
             <!-- Category & Rating -->
            <div class="flex items-center justify-between">
                <div class="h-4 w-16 bg-gray-200 rounded animate-pulse"></div>
                <div class="flex items-center gap-[2px]">
                   <div class="h-4 w-4 bg-gray-200 rounded-full animate-pulse"></div>
                   <div class="h-4 w-6 bg-gray-200 rounded animate-pulse"></div>
                </div>
            </div>

            <!-- Title -->
            <div class="h-5 w-full bg-gray-200 rounded animate-pulse"></div>
            <div class="h-5 w-3/4 bg-gray-200 rounded animate-pulse"></div>

             <!-- Price -->
            <div class="h-6 w-1/2 bg-gray-200 rounded mt-1 animate-pulse"></div>

            <!-- Footer -->
            <div class="flex items-center justify-between mt-auto">
                 <div class="h-10 w-10 bg-gray-200 rounded-full animate-pulse"></div>
                 <div class="h-10 w-24 bg-gray-200 rounded-full animate-pulse"></div>
            </div>
        </div>
    </div>
</template>
