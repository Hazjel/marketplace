<template>
    <div class="flex gap-5">
        <div class="flex flex-col w-[440px] shrink-0 rounded-[20px] p-5 gap-6 bg-white">
            <div class="flex flex-col gap-6">
                <div class="flex size-[56px] bg-custom-blue/10 items-center justify-center rounded-full">
                    <img src="@/assets/images/icons/wallet-2-blue-fill.svg" class="flex size-6 shrink-0" alt="icon">
                </div>
                <div class="flex flex-col gap-[6px]">
                    <p class="font-bold text-4xl">Rp 920.650.320</p>
                    <p class="font-medium text-lg text-custom-grey">
                        Total Revenue
                    </p>
                </div>
            </div>
        </div>
        <div class="flex flex-col w-full rounded-[20px] p-5 gap-6 bg-white">
            <div class="flex flex-col gap-6">
                <div class="flex size-[56px] bg-custom-blue/10 items-center justify-center rounded-full">
                    <img src="@/assets/images/icons/profile-tick-blue-fill.svg" class="flex size-6 shrink-0" alt="icon">
                </div>
                <div class="flex flex-col gap-[6px]">
                    <p class="font-bold text-4xl">6.400</p>
                    <p class="font-medium text-lg text-custom-grey">
                        Total Sellers
                    </p>
                </div>
            </div>
        </div>
        <div class="flex flex-col w-full rounded-[20px] p-5 gap-6 bg-white">
            <div class="flex flex-col gap-6">
                <div class="flex size-[56px] bg-custom-blue/10 items-center justify-center rounded-full">
                    <img src="@/assets/images/icons/profile-2user-blue-fill.svg" class="flex size-6 shrink-0" alt="icon">
                </div>
                <div class="flex flex-col gap-[6px]">
                    <p class="font-bold text-4xl">16.560</p>
                    <p class="font-medium text-lg text-custom-grey">
                        Total Buyers
                    </p>
                </div>
            </div>
        </div>
    </div>
    <div class="flex gap-5 flex-1">
        <div class="flex flex-col gap-5">
            <div class="flex flex-col w-[440px] shrink-0 rounded-[20px] p-5 gap-6 bg-white">
                <div class="flex flex-col gap-6">
                    <div class="flex size-[56px] bg-custom-blue/10 items-center justify-center rounded-full">
                        <img src="@/assets/images/icons/shopping-cart-blue-fill.svg" class="flex size-6 shrink-0" alt="icon">
                    </div>
                    <div class="flex flex-col gap-[6px]">
                        <p class="font-bold text-4xl">320.500</p>
                        <p class="font-medium text-lg text-custom-grey">
                            Total Products
                        </p>
                    </div>
                </div>
            </div>
            <div class="flex flex-col flex-1 w-[440px] shrink-0 rounded-[20px] p-5 gap-6 bg-white">
                <div class="flex flex-col gap-6">
                    <div class="flex size-[56px] bg-custom-blue/10 items-center justify-center rounded-full">
                        <img src="@/assets/images/icons/shop-blue-fill.svg" class="flex size-6 shrink-0" alt="icon">
                    </div>
                    <div class="flex flex-col gap-[6px]">
                        <p class="font-bold text-4xl">320</p>
                        <p class="font-medium text-lg text-custom-grey">
                            Total Stores
                        </p>
                    </div>
                </div>
                <hr class="border-custom-stroke">
                <div class="flex flex-col flex-1 gap-5">
                    <p class="font-bold text-xl">Latest Stores</p>
                    <div id="List-Stores" class="flex flex-col gap-5">
                        <div class="card flex flex-col rounded-[20px] border border-custom-stroke py-[18px] px-5 gap-5 bg-white">
                            <div class="flex items-center gap-[14px]">
                                <div class="flex size-16 shrink-0 rounded-[20px] bg-custom-background overflow-hidden">
                                    <img src="@/assets/images/thumbnails/th-1.svg" class="size-full object-cover" alt="photo">
                                </div>
                                <div class="flex flex-col gap-[6px] w-full overflow-hidden">
                                    <p class="font-bold text-lg leading-tight w-full truncate">
                                        Shayna Sports
                                    </p>
                                    <p class="flex items-center gap-1 font-semibold text-custom-grey leading-none">
                                        <img src="@/assets/images/icons/user-grey.svg" class="size-5" alt="icon"> 
                                        Princess Elsa
                                    </p>
                                </div>
                            </div>
                            <hr class="border-custom-stroke">
                            <div class="flex items-center justify-between">
                                <p class="flex items-center gap-2 font-semibold text-custom-grey leading-none">
                                    <img src="@/assets/images/icons/calendar-2-grey.svg" class="size-6 flex shrink-0" alt="icon"> 
                                    Created on 19/02/2020
                                </p>
                                <a href="#" class="font-semibold text-custom-blue hover:underline">
                                    View Details
                                </a>
                            </div>
                        </div>
                        <div class="card flex flex-col rounded-[20px] border border-custom-stroke py-[18px] px-5 gap-5 bg-white">
                            <div class="flex items-center gap-[14px]">
                                <div class="flex size-16 shrink-0 rounded-[20px] bg-custom-background overflow-hidden">
                                    <img src="@/assets/images/thumbnails/th-2.svg" class="size-full object-cover" alt="photo">
                                </div>
                                <div class="flex flex-col gap-[6px] w-full overflow-hidden">
                                    <p class="font-bold text-lg leading-tight w-full truncate">
                                        Raffly Bookstore
                                    </p>
                                    <p class="flex items-center gap-1 font-semibold text-custom-grey leading-none">
                                        <img src="@/assets/images/icons/user-grey.svg" class="size-5" alt="icon"> 
                                        Rizky Ichsan
                                    </p>
                                </div>
                            </div>
                            <hr class="border-custom-stroke">
                            <div class="flex items-center justify-between">
                                <p class="flex items-center gap-2 font-semibold text-custom-grey leading-none">
                                    <img src="@/assets/images/icons/calendar-2-grey.svg" class="size-6 flex shrink-0" alt="icon"> 
                                    Created on 19/02/2020
                                </p>
                                <a href="#" class="font-semibold text-custom-blue hover:underline">
                                    View Details
                                </a>
                            </div>
                        </div>
                        <div class="card flex flex-col rounded-[20px] border border-custom-stroke py-[18px] px-5 gap-5 bg-white">
                            <div class="flex items-center gap-[14px]">
                                <div class="flex size-16 shrink-0 rounded-[20px] bg-custom-background overflow-hidden">
                                    <img src="@/assets/images/thumbnails/th-3.svg" class="size-full object-cover" alt="photo">
                                </div>
                                <div class="flex flex-col gap-[6px] w-full overflow-hidden">
                                    <p class="font-bold text-lg leading-tight w-full truncate">
                                        Marcy Beauty
                                    </p>
                                    <p class="flex items-center gap-1 font-semibold text-custom-grey leading-none">
                                        <img src="@/assets/images/icons/user-grey.svg" class="size-5" alt="icon"> 
                                        Chessta B
                                    </p>
                                </div>
                            </div>
                            <hr class="border-custom-stroke">
                            <div class="flex items-center justify-between">
                                <p class="flex items-center gap-2 font-semibold text-custom-grey leading-none">
                                    <img src="@/assets/images/icons/calendar-2-grey.svg" class="size-6 flex shrink-0" alt="icon"> 
                                    Created on 19/02/2020
                                </p>
                                <a href="#" class="font-semibold text-custom-blue hover:underline">
                                    View Details
                                </a>
                            </div>
                        </div>
                    </div>
                    <div id="Empty-State" class="hidden flex flex-col flex-1 items-center justify-center gap-4">
                        <img src="@/assets/images/icons/note-remove-grey.svg" class="size-[52px]" alt="icon">
                        <div class="flex flex-col gap-1 items-center text-center">
                            <p class="font-semibold text-custom-grey">Oops, you don't have any data yet</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="flex flex-col w-full gap-5">
            <div class="flex flex-col flex-1 w-full shrink-0 rounded-[20px] p-5 gap-6 bg-white">
                <div class="flex flex-col gap-6">
                    <div class="flex size-[56px] bg-custom-blue/10 items-center justify-center rounded-full">
                        <img src="@/assets/images/icons/note-text-blue-fill.svg" class="flex size-6 shrink-0" alt="icon">
                    </div>
                    <div class="flex flex-col gap-[6px]">
                        <p class="font-bold text-4xl">163.200</p>
                        <p class="font-medium text-lg text-custom-grey">
                            Total Transaction
                        </p>
                    </div>
                </div>
                <hr class="border-custom-stroke">
                <div class="flex flex-col flex-1 gap-5">
                    <p class="font-bold text-xl">Latest Transactions</p>
                    <div id="List-Transactions" class="flex flex-col gap-5">
                         <div class="card flex flex-col rounded-[20px] border border-custom-stroke py-[18px] px-5 gap-5 bg-white">
                            <div class="flex items-center gap-[14px] w-full overflow-hidden">
                                <div class="flex size-16 shrink-0 rounded-[20px] bg-custom-background overflow-hidden">
                                    <img src="@/assets/images/thumbnails/th-4.svg" class="size-full object-cover" alt="photo">
                                </div>
                                <div class="flex flex-col gap-[6px] w-full flex-grow-0 overflow-hidden">
                                    <p class="font-bold text-lg leading-tight w-full">
                                        Rierru Fashion
                                    </p>
                                    <p class="flex items-center gap-1 font-semibold text-custom-grey leading-none">
                                        <img src="@/assets/images/icons/user-grey.svg" class="size-5" alt="icon"> 
                                        Feary Reyn
                                    </p>
                                </div>
                                <div class="flex flex-col gap-2 items-end">
                                    <p class="font-bold text-lg leading-tight text-custom-blue text-nowrap">
                                        Rp 384.000
                                    </p>
                                    <p class="flex items-center gap-1 font-semibold text-custom-grey leading-none text-nowrap">
                                        Grand Total
                                    </p>
                                </div>
                            </div>
                            <hr class="border-custom-stroke">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-[10px]">
                                    <div class="flex size-14 shrink-0 rounded-full bg-custom-icon-background overflow-hidden items-center justify-center">
                                        <img src="@/assets/images/icons/shopping-cart-black.svg" class="flex size-6 shrink-0" alt="icon">
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <p class="font-bold text-lg leading-none">12</p>
                                        <p class="font-semibold text-custom-grey">Total Products</p>
                                    </div>
                                </div>
                                <a href="#" class="flex w-[96px] h-[56px] shrink-0 rounded-2xl py-[18px] px-5 bg-custom-blue/10 gap-2 hover:ring-2 hover:ring-custom-blue transition-300">
                                    <span class="font-semibold text-custom-blue leading-none">
                                        Details
                                    </span>
                                </a>
                            </div>
                        </div>
                        <div class="card flex flex-col rounded-[20px] border border-custom-stroke py-[18px] px-5 gap-5 bg-white">
                            <div class="flex items-center gap-[14px] w-full overflow-hidden">
                                <div class="flex size-16 shrink-0 rounded-[20px] bg-custom-background overflow-hidden">
                                    <img src="@/assets/images/thumbnails/th-5.svg" class="size-full object-cover" alt="photo">
                                </div>
                                <div class="flex flex-col gap-[6px] w-full flex-grow-0 overflow-hidden">
                                    <p class="font-bold text-lg leading-tight w-full">
                                        Marcy Beauty
                                    </p>
                                    <p class="flex items-center gap-1 font-semibold text-custom-grey leading-none">
                                        <img src="@/assets/images/icons/user-grey.svg" class="size-5" alt="icon"> 
                                        Shadam B
                                    </p>
                                </div>
                                <div class="flex flex-col gap-2 items-end">
                                    <p class="font-bold text-lg leading-tight text-custom-blue text-nowrap">
                                        Rp 384.000
                                    </p>
                                    <p class="flex items-center gap-1 font-semibold text-custom-grey leading-none text-nowrap">
                                        Grand Total
                                    </p>
                                </div>
                            </div>
                            <hr class="border-custom-stroke">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-[10px]">
                                    <div class="flex size-14 shrink-0 rounded-full bg-custom-icon-background overflow-hidden items-center justify-center">
                                        <img src="@/assets/images/icons/shopping-cart-black.svg" class="flex size-6 shrink-0" alt="icon">
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <p class="font-bold text-lg leading-none">12</p>
                                        <p class="font-semibold text-custom-grey">Total Products</p>
                                    </div>
                                </div>
                                <a href="#" class="flex w-[96px] h-[56px] shrink-0 rounded-2xl py-[18px] px-5 bg-custom-blue/10 gap-2 hover:ring-2 hover:ring-custom-blue transition-300">
                                    <span class="font-semibold text-custom-blue leading-none">
                                        Details
                                    </span>
                                </a>
                            </div>
                        </div>
                        <div class="card flex flex-col rounded-[20px] border border-custom-stroke py-[18px] px-5 gap-5 bg-white">
                            <div class="flex items-center gap-[14px] w-full overflow-hidden">
                                <div class="flex size-16 shrink-0 rounded-[20px] bg-custom-background overflow-hidden">
                                    <img src="@/assets/images/thumbnails/th-6.svg" class="size-full object-cover" alt="photo">
                                </div>
                                <div class="flex flex-col gap-[6px] w-full flex-grow-0 overflow-hidden">
                                    <p class="font-bold text-lg leading-tight w-full">
                                        Raffly Bookstore
                                    </p>
                                    <p class="flex items-center gap-1 font-semibold text-custom-grey leading-none">
                                        <img src="@/assets/images/icons/user-grey.svg" class="size-5" alt="icon"> 
                                        M Raffly
                                    </p>
                                </div>
                                <div class="flex flex-col gap-2 items-end">
                                    <p class="font-bold text-lg leading-tight text-custom-blue text-nowrap">
                                        Rp 384.000
                                    </p>
                                    <p class="flex items-center gap-1 font-semibold text-custom-grey leading-none text-nowrap">
                                        Grand Total
                                    </p>
                                </div>
                            </div>
                            <hr class="border-custom-stroke">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-[10px]">
                                    <div class="flex size-14 shrink-0 rounded-full bg-custom-icon-background overflow-hidden items-center justify-center">
                                        <img src="@/assets/images/icons/shopping-cart-black.svg" class="flex size-6 shrink-0" alt="icon">
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <p class="font-bold text-lg leading-none">12</p>
                                        <p class="font-semibold text-custom-grey">Total Products</p>
                                    </div>
                                </div>
                                <a href="#" class="flex w-[96px] h-[56px] shrink-0 rounded-2xl py-[18px] px-5 bg-custom-blue/10 gap-2 hover:ring-2 hover:ring-custom-blue transition-300">
                                    <span class="font-semibold text-custom-blue leading-none">
                                        Details
                                    </span>
                                </a>
                            </div>
                        </div>
                    </div>
                    <div id="Empty-State" class="hidden flex flex-col flex-1 items-center justify-center gap-4">
                        <img src="@/assets/images/icons/note-remove-grey.svg" class="size-[52px]" alt="icon">
                        <div class="flex flex-col gap-1 items-center text-center">
                            <p class="font-semibold text-custom-grey">Oops, you don't have any data yet</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>