<template>
    <div class="flex gap-5">
        <div class="flex flex-col w-[360px] shrink-0 rounded-[20px] p-5 gap-6 bg-white">
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
                    <img src="@/assets/images/icons/shopping-cart-blue-fill.svg" class="flex size-6 shrink-0"
                        alt="icon">
                </div>
                <div class="flex flex-col gap-[6px]">
                    <p class="font-bold text-4xl">16.200</p>
                    <p class="font-medium text-lg text-custom-grey">
                        Total Products
                    </p>
                </div>
            </div>
        </div>
        <div class="flex flex-col w-full rounded-[20px] p-5 gap-6 bg-white">
            <div class="flex flex-col gap-6">
                <div class="flex size-[56px] bg-custom-blue/10 items-center justify-center rounded-full">
                    <img src="@/assets/images/icons/profile-2user-blue-fill.svg" class="flex size-6 shrink-0"
                        alt="icon">
                </div>
                <div class="flex flex-col gap-[6px]">
                    <p class="font-bold text-4xl">3.200</p>
                    <p class="font-medium text-lg text-custom-grey">
                        Total Buyers
                    </p>
                </div>
            </div>
        </div>
    </div>
    <div class="flex gap-5 flex-1">
        <div class="flex flex-col gap-5 w-[470px] shrink-0">
            <div class="flex flex-col flex-1 rounded-[20px] p-5 gap-6 bg-white">
                <div class="flex flex-col gap-6">
                    <div class="flex size-[56px] bg-custom-blue/10 items-center justify-center rounded-full">
                        <img src="@/assets/images/icons/stickynote-blue-fill.svg" class="flex size-6 shrink-0"
                            alt="icon">
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
                    <div id="List-Stores" class="flex flex-col gap-5">
                        <div
                            class="card flex flex-col rounded-[20px] border border-custom-stroke py-[18px] px-5 gap-5 bg-white">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-[10px] min-w-0">
                                    <div
                                        class="flex size-14 shrink-0 rounded-full bg-custom-background overflow-hidden">
                                        <img src="@/assets/images/photos/photo-1.png" class="size-full object-cover"
                                            alt="photo">
                                    </div>
                                    <div class="flex flex-col gap-[6px] w-full overflow-hidden">
                                        <p class="font-bold text-lg leading-tight w-full truncate">
                                            Uciha Shayna
                                        </p>
                                        <p class="flex items-center gap-1 font-semibold text-custom-grey leading-none">
                                            <img src="@/assets/images/icons/call-grey.svg" class="size-5" alt="icon">
                                            081255553005
                                        </p>
                                    </div>
                                </div>
                                <div class="flex flex-col gap-2 items-end">
                                    <p class="font-bold text-lg leading-tight text-custom-blue text-nowrap">
                                        Rp 384.000
                                    </p>
                                    <p
                                        class="flex items-center gap-1 font-semibold text-custom-grey leading-none text-nowrap">
                                        Grand Total
                                    </p>
                                </div>
                            </div>
                            <hr class="border-custom-stroke">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-[10px] w-[260px]">
                                    <div
                                        class="flex size-14 shrink-0 rounded-full bg-custom-icon-background overflow-hidden items-center justify-center">
                                        <img src="@/assets/images/icons/shopping-cart-black.svg"
                                            class="flex size-6 shrink-0" alt="icon">
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <p class="font-bold text-lg leading-none">240</p>
                                        <p class="font-semibold text-custom-grey">Total Products</p>
                                    </div>
                                </div>
                                <a href="#"
                                    class="flex w-[96px] h-[56px] shrink-0 rounded-2xl py-[18px] px-5 bg-custom-blue/10 gap-2 hover:ring-2 hover:ring-custom-blue transition-300">
                                    <span class="font-semibold text-custom-blue leading-none">
                                        Details
                                    </span>
                                </a>
                            </div>
                        </div>
                        <div
                            class="card flex flex-col rounded-[20px] border border-custom-stroke py-[18px] px-5 gap-5 bg-white">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-[10px] min-w-0">
                                    <div
                                        class="flex size-14 shrink-0 rounded-full bg-custom-background overflow-hidden">
                                        <img src="@/assets/images/photos/photo-2.png" class="size-full object-cover"
                                            alt="photo">
                                    </div>
                                    <div class="flex flex-col gap-[6px] w-full overflow-hidden">
                                        <p class="font-bold text-lg leading-tight w-full truncate">
                                            Masayoshi Mazdo
                                        </p>
                                        <p class="flex items-center gap-1 font-semibold text-custom-grey leading-none">
                                            <img src="@/assets/images/icons/call-grey.svg" class="size-5" alt="icon">
                                            081255553005
                                        </p>
                                    </div>
                                </div>
                                <div class="flex flex-col gap-2 items-end">
                                    <p class="font-bold text-lg leading-tight text-custom-blue text-nowrap">
                                        Rp 384.000
                                    </p>
                                    <p
                                        class="flex items-center gap-1 font-semibold text-custom-grey leading-none text-nowrap">
                                        Grand Total
                                    </p>
                                </div>
                            </div>
                            <hr class="border-custom-stroke">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-[10px] w-[260px]">
                                    <div
                                        class="flex size-14 shrink-0 rounded-full bg-custom-icon-background overflow-hidden items-center justify-center">
                                        <img src="@/assets/images/icons/shopping-cart-black.svg"
                                            class="flex size-6 shrink-0" alt="icon">
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <p class="font-bold text-lg leading-none">240</p>
                                        <p class="font-semibold text-custom-grey">Total Products</p>
                                    </div>
                                </div>
                                <a href="#"
                                    class="flex w-[96px] h-[56px] shrink-0 rounded-2xl py-[18px] px-5 bg-custom-blue/10 gap-2 hover:ring-2 hover:ring-custom-blue transition-300">
                                    <span class="font-semibold text-custom-blue leading-none">
                                        Details
                                    </span>
                                </a>
                            </div>
                        </div>
                        <div
                            class="card flex flex-col rounded-[20px] border border-custom-stroke py-[18px] px-5 gap-5 bg-white">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-[10px] min-w-0">
                                    <div
                                        class="flex size-14 shrink-0 rounded-full bg-custom-background overflow-hidden">
                                        <img src="@/assets/images/photos/photo-3.png" class="size-full object-cover"
                                            alt="photo">
                                    </div>
                                    <div class="flex flex-col gap-[6px] w-full overflow-hidden">
                                        <p class="font-bold text-lg leading-tight w-full truncate">
                                            Rafly Ackerman
                                        </p>
                                        <p class="flex items-center gap-1 font-semibold text-custom-grey leading-none">
                                            <img src="@/assets/images/icons/call-grey.svg" class="size-5" alt="icon">
                                            081255553005
                                        </p>
                                    </div>
                                </div>
                                <div class="flex flex-col gap-2 items-end">
                                    <p class="font-bold text-lg leading-tight text-custom-blue text-nowrap">
                                        Rp 384.000
                                    </p>
                                    <p
                                        class="flex items-center gap-1 font-semibold text-custom-grey leading-none text-nowrap">
                                        Grand Total
                                    </p>
                                </div>
                            </div>
                            <hr class="border-custom-stroke">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-[10px] w-[260px]">
                                    <div
                                        class="flex size-14 shrink-0 rounded-full bg-custom-icon-background overflow-hidden items-center justify-center">
                                        <img src="@/assets/images/icons/shopping-cart-black.svg"
                                            class="flex size-6 shrink-0" alt="icon">
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <p class="font-bold text-lg leading-none">240</p>
                                        <p class="font-semibold text-custom-grey">Total Products</p>
                                    </div>
                                </div>
                                <a href="#"
                                    class="flex w-[96px] h-[56px] shrink-0 rounded-2xl py-[18px] px-5 bg-custom-blue/10 gap-2 hover:ring-2 hover:ring-custom-blue transition-300">
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
        <div class="flex flex-col flex-1 w-full rounded-[20px] p-5 gap-6 bg-white">
            <div class="flex flex-col gap-6">
                <div class="flex size-[56px] bg-custom-blue/10 items-center justify-center rounded-full">
                    <img src="@/assets/images/icons/message-text-blue-fill.svg" class="flex size-6 shrink-0" alt="icon">
                </div>
                <div class="flex flex-col gap-[6px]">
                    <p class="font-bold text-4xl">22.500</p>
                    <p class="font-medium text-lg text-custom-grey">
                        Total Reviews
                    </p>
                </div>
            </div>
            <hr class="border-custom-stroke">
            <div class="flex flex-col flex-1 gap-5">
                <p class="font-bold text-xl">Latest Reviews</p>
                <div id="List-Reviews" class="flex flex-col gap-5">
                    <div
                        class="card flex flex-col rounded-[20px] border border-custom-stroke py-[18px] px-5 gap-5 bg-white">
                        <div class="flex items-center gap-[10px] min-w-0">
                            <div class="flex size-14 shrink-0 rounded-full bg-custom-background overflow-hidden">
                                <img src="@/assets/images/photos/photo-8.png" class="size-full object-cover"
                                    alt="photo">
                            </div>
                            <div class="flex flex-col gap-[6px] w-full overflow-hidden">
                                <p class="font-bold text-lg leading-tight w-full truncate">
                                    Echa Marica
                                </p>
                                <p class="flex items-center gap-1 font-semibold text-custom-grey leading-none">
                                    <img src="@/assets/images/icons/call-grey.svg" class="size-5" alt="icon">
                                    081255553005
                                </p>
                            </div>
                        </div>
                        <hr class="border-custom-stroke">
                        <div class="flex flex-col gap-2">
                            <p class="font-medium text-custom-grey">“The iPhone is super fast, the MacBook
                                is perfect for work, and the AirPods sound crystal clear. Plus, the store's
                                service was amazing—fast delivery and great support!”</p>
                            <div class="flex">
                                <img src="@/assets/images/icons/Star-pointy.svg" class="flex size-6 shrink-0 p-0.5"
                                    alt="star">
                                <img src="@/assets/images/icons/Star-pointy.svg" class="flex size-6 shrink-0 p-0.5"
                                    alt="star">
                                <img src="@/assets/images/icons/Star-pointy.svg" class="flex size-6 shrink-0 p-0.5"
                                    alt="star">
                                <img src="@/assets/images/icons/Star-pointy.svg" class="flex size-6 shrink-0 p-0.5"
                                    alt="star">
                                <img src="@/assets/images/icons/Star-pointy-outline.svg"
                                    class="flex size-6 shrink-0 p-0.5" alt="star">
                            </div>
                        </div>
                    </div>
                    <div
                        class="card flex flex-col rounded-[20px] border border-custom-stroke py-[18px] px-5 gap-5 bg-white">
                        <div class="flex items-center gap-[10px] min-w-0">
                            <div class="flex size-14 shrink-0 rounded-full bg-custom-background overflow-hidden">
                                <img src="@/assets/images/photos/photo-7.png" class="size-full object-cover"
                                    alt="photo">
                            </div>
                            <div class="flex flex-col gap-[6px] w-full overflow-hidden">
                                <p class="font-bold text-lg leading-tight w-full truncate">
                                    Souma Yeager
                                </p>
                                <p class="flex items-center gap-1 font-semibold text-custom-grey leading-none">
                                    <img src="@/assets/images/icons/call-grey.svg" class="size-5" alt="icon">
                                    081255553005
                                </p>
                            </div>
                        </div>
                        <hr class="border-custom-stroke">
                        <div class="flex flex-col gap-2">
                            <p class="font-medium text-custom-grey">“The iPhone is super fast, the MacBook
                                is perfect for work, and the AirPods sound crystal clear. Plus, the store's
                                service was amazing—fast delivery and great support!”</p>
                            <div class="flex">
                                <img src="@/assets/images/icons/Star-pointy.svg" class="flex size-6 shrink-0 p-0.5"
                                    alt="star">
                                <img src="@/assets/images/icons/Star-pointy.svg" class="flex size-6 shrink-0 p-0.5"
                                    alt="star">
                                <img src="@/assets/images/icons/Star-pointy.svg" class="flex size-6 shrink-0 p-0.5"
                                    alt="star">
                                <img src="@/assets/images/icons/Star-pointy.svg" class="flex size-6 shrink-0 p-0.5"
                                    alt="star">
                                <img src="@/assets/images/icons/Star-pointy-outline.svg"
                                    class="flex size-6 shrink-0 p-0.5" alt="star">
                            </div>
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
</template>