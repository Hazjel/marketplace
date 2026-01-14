<template>
    <div class="flex flex-col rounded-[20px] border border-custom-stroke overflow-hidden h-full">
        <!-- Thumbnail -->
        <div class="w-full h-[130px] md:h-[202px] bg-gray-200 animate-pulse"></div>

        <!-- Content -->
        <div class="flex flex-col p-5 gap-4 bg-white flex-1">
            <!-- Logo & Store Name -->
            <div class="flex items-center gap-3">
                 <div class="size-[50px] bg-gray-200 rounded-full shrink-0 animate-pulse"></div>
                 <div class="flex flex-col gap-2 w-full">
                     <div class="h-5 w-3/4 bg-gray-200 rounded animate-pulse"></div>
                     <div class="h-4 w-1/2 bg-gray-200 rounded animate-pulse"></div>
                 </div>
            </div>

             <!-- Button -->
            <div class="h-[46px] w-full bg-gray-200 rounded-full mt-auto animate-pulse"></div>
        </div>
    </div>
</template>
