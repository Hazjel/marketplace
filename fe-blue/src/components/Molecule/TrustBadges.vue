<script setup></script>

<template>
  <div
    class="flex flex-col gap-3 p-4 bg-gray-50 dark:bg-white/5 rounded-2xl border border-gray-100 dark:border-white/10">
    <div class="flex items-center gap-3">
      <div class="size-8 rounded-full bg-blue-100 flex items-center justify-center shrink-0">
        <i class="fa-solid fa-shield-halved text-custom-blue text-sm"></i>
      </div>
      <div class="flex flex-col">
        <p class="text-xs font-bold text-custom-black dark:text-white">Secure Payment</p>
        <p class="text-[10px] text-custom-grey dark:text-gray-400">
          100% secure transaction with Midtrans
        </p>
      </div>
    </div>
    <div class="flex items-center gap-3">
      <div class="size-8 rounded-full bg-green-100 flex items-center justify-center shrink-0">
        <i class="fa-solid fa-check-circle text-custom-green text-sm"></i>
      </div>
      <div class="flex flex-col">
        <p class="text-xs font-bold text-custom-black dark:text-white">Official Warranty</p>
        <p class="text-[10px] text-custom-grey dark:text-gray-400">All products are 100% original</p>
      </div>
    </div>
    <div class="flex items-center gap-3">
      <div class="size-8 rounded-full bg-orange-100 flex items-center justify-center shrink-0">
        <i class="fa-solid fa-rotate-left text-custom-orange text-sm"></i>
      </div>
      <div class="flex flex-col">
        <p class="text-xs font-bold text-custom-black dark:text-white">Money Back Guarantee</p>
        <p class="text-[10px] text-custom-grey dark:text-gray-400">
          If item doesn't arrive or damaged
        </p>
      </div>
    </div>
  </div>
</template>
