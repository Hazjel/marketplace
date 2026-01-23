<script setup>
import { onMounted, ref } from 'vue';

const activeSection = ref('agreement');

const scrollTo = (id) => {
    activeSection.value = id;
    const element = document.getElementById(id);
    if (element) {
        const offset = 100; // Header height + padding
        const bodyRect = document.body.getBoundingClientRect().top;
        const elementRect = element.getBoundingClientRect().top;
        const elementPosition = elementRect - bodyRect;
        const offsetPosition = elementPosition - offset;

        window.scrollTo({
            top: offsetPosition,
            behavior: "smooth"
        });
    }
};

onMounted(() => {
    window.scrollTo(0, 0);
});
</script>

<template>
    <main class="w-full bg-white pb-20">
        <!-- Header -->
        <header class="bg-gray-50 border-b border-gray-200 py-16 md:py-20 px-5 md:px-[52px]">
            <div class="max-w-[1280px] mx-auto">
                <span class="text-custom-blue font-bold tracking-widest uppercase text-sm mb-4 block">Legal</span>
                <h1 class="text-4xl md:text-5xl font-bold text-custom-black mb-4">Terms & Conditions</h1>
                <p class="text-custom-grey text-lg max-w-2xl">
                    Please read these terms and conditions carefully before using our service.
                </p>
                <div
                    class="mt-8 flex items-center gap-2 text-sm text-custom-grey bg-white px-4 py-2 rounded-full w-fit border border-gray-200 shadow-sm">
                    <i class="fa-regular fa-clock"></i>
                    <span>Last Updated: January 01, 2026</span>
                </div>
            </div>
        </header>

        <div class="max-w-[1280px] mx-auto px-5 md:px-[52px] py-12 flex flex-col md:flex-row gap-12 relative">
            <!-- Sidebar Navigation -->
            <aside class="w-full md:w-1/4 hidden md:block">
                <div class="sticky top-32 flex flex-col gap-2">
                    <p class="font-bold text-custom-black mb-2 px-4 uppercase text-xs tracking-wider">Contents</p>
                    <button @click="scrollTo('agreement')"
                        :class="activeSection === 'agreement' ? 'bg-blue-50 text-custom-blue font-bold border-custom-blue' : 'text-custom-grey hover:bg-gray-50 border-transparent'"
                        class="text-left px-4 py-3 rounded-lg text-sm transition-all border-l-4">
                        1. Agreement
                    </button>
                    <button @click="scrollTo('representations')"
                        :class="activeSection === 'representations' ? 'bg-blue-50 text-custom-blue font-bold border-custom-blue' : 'text-custom-grey hover:bg-gray-50 border-transparent'"
                        class="text-left px-4 py-3 rounded-lg text-sm transition-all border-l-4">
                        2. User Representations
                    </button>
                    <button @click="scrollTo('products')"
                        :class="activeSection === 'products' ? 'bg-blue-50 text-custom-blue font-bold border-custom-blue' : 'text-custom-grey hover:bg-gray-50 border-transparent'"
                        class="text-left px-4 py-3 rounded-lg text-sm transition-all border-l-4">
                        3. Products & Purchases
                    </button>
                    <button @click="scrollTo('prohibited')"
                        :class="activeSection === 'prohibited' ? 'bg-blue-50 text-custom-blue font-bold border-custom-blue' : 'text-custom-grey hover:bg-gray-50 border-transparent'"
                        class="text-left px-4 py-3 rounded-lg text-sm transition-all border-l-4">
                        4. Prohibited Activities
                    </button>
                    <button @click="scrollTo('contact')"
                        :class="activeSection === 'contact' ? 'bg-blue-50 text-custom-blue font-bold border-custom-blue' : 'text-custom-grey hover:bg-gray-50 border-transparent'"
                        class="text-left px-4 py-3 rounded-lg text-sm transition-all border-l-4">
                        5. Contact Us
                    </button>
                </div>
            </aside>

            <!-- Content -->
            <div class="w-full md:w-3/4 max-w-3xl">
                <div
                    class="prose prose-lg prose-headings:text-custom-black prose-p:text-custom-grey prose-li:text-custom-grey max-w-none flex flex-col gap-12">

                    <section id="agreement" class="scroll-mt-32">
                        <h2 class="text-2xl font-bold mb-4">1. Agreement to Terms</h2>
                        <p class="text-base leading-relaxed">
                            These Terms and Conditions constitute a legally binding agreement made between you, whether
                            personally or on behalf of an entity (“you”) and Blukios (“we,” “us” or “our”), concerning
                            your access to and use of the Blukios website as well as any other media form, media
                            channel, mobile website or mobile application related, linked, or otherwise connected
                            thereto (collectively, the “Site”).
                        </p>
                    </section>

                    <section id="representations" class="scroll-mt-32">
                        <h2 class="text-2xl font-bold mb-4">2. User Representations</h2>
                        <p class="text-base leading-relaxed mb-4">
                            By using the Site, you represent and warrant that:
                        </p>
                        <div class="bg-gray-50 p-6 rounded-2xl border border-gray-100">
                            <ul class="space-y-3 list-none p-0 m-0">
                                <li class="flex gap-3">
                                    <div class="w-1.5 h-1.5 rounded-full bg-custom-blue mt-2 shrink-0"></div>
                                    <span class="text-sm">All registration information you submit will be true,
                                        accurate, current, and complete.</span>
                                </li>
                                <li class="flex gap-3">
                                    <div class="w-1.5 h-1.5 rounded-full bg-custom-blue mt-2 shrink-0"></div>
                                    <span class="text-sm">You will maintain the accuracy of such information and
                                        promptly update such registration information as necessary.</span>
                                </li>
                                <li class="flex gap-3">
                                    <div class="w-1.5 h-1.5 rounded-full bg-custom-blue mt-2 shrink-0"></div>
                                    <span class="text-sm">You have the legal capacity and you agree to comply with these
                                        Terms and Conditions.</span>
                                </li>
                            </ul>
                        </div>
                    </section>

                    <section id="products" class="scroll-mt-32">
                        <h2 class="text-2xl font-bold mb-4">3. Products and Purchases</h2>
                        <p class="text-base leading-relaxed">
                            We make every effort to display as accurately as possible the colors, features,
                            specifications, and details of the products available on the Site. However, we do not
                            guarantee that the colors, features, specifications, and details of the products will be
                            accurate, complete, reliable, current, or free of other errors.
                        </p>
                    </section>

                    <section id="prohibited" class="scroll-mt-32">
                        <h2 class="text-2xl font-bold mb-4">4. Prohibited Activities</h2>
                        <p class="text-base leading-relaxed mb-4">
                            You may not access or use the Site for any purpose other than that for which we make the
                            Site available. As a user of the Site, you agree not to:
                        </p>
                        <ul class="space-y-2 list-disc pl-5">
                            <li class="pl-2">Systematically retrieve data or other content from the Site to create or
                                compile, directly or indirectly, a collection, compilation, database, or directory
                                without written permission from us.</li>
                            <li class="pl-2">Make any unauthorized use of the Site, including collecting usernames
                                and/or email addresses of users by electronic or other means for the purpose of sending
                                unsolicited email.</li>
                        </ul>
                    </section>

                    <section id="contact" class="scroll-mt-32">
                        <h2 class="text-2xl font-bold mb-4">5. Contact Us</h2>
                        <div
                            class="bg-blue-50 border border-blue-100 rounded-2xl p-6 flex flex-col md:flex-row items-center gap-6">
                            <div
                                class="w-12 h-12 rounded-full bg-white flex items-center justify-center text-custom-blue shadow-sm shrink-0">
                                <i class="fa-solid fa-scale-balanced"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-custom-black text-lg">Legal Concerns?</h3>
                                <p class="text-sm text-custom-grey mb-1">Our legal team is here to assist you.</p>
                                <a href="mailto:legal@blukios.com"
                                    class="text-custom-blue font-semibold hover:underline">legal@blukios.com</a>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </main>
</template>
