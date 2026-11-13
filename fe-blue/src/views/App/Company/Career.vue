<script setup>
import { onMounted } from 'vue'

const openRoles = [
  {
    title: 'Senior Product Designer',
    department: 'Desain',
    location: 'Kendal, ID',
    type: 'Penuh Waktu',
    tags: ['UI/UX', 'Figma', 'User Research']
  },
  {
    title: 'Backend Engineer',
    department: 'Engineering',
    location: 'Remote',
    type: 'Penuh Waktu',
    tags: ['PHP', 'Laravel', 'PostgreSQL']
  },
  {
    title: 'Frontend Developer',
    department: 'Engineering',
    location: 'Remote',
    type: 'Penuh Waktu',
    tags: ['Vue 3', 'TailwindCSS', 'TypeScript']
  },
  {
    title: 'Customer Success Spec.',
    department: 'Dukungan',
    location: 'Jakarta, ID',
    type: 'Paruh Waktu',
    tags: ['Komunikasi', 'Bahasa Inggris']
  }
]

onMounted(() => {
  window.scrollTo(0, 0)
})
</script>

<template>
  <main class="w-full bg-white dark:bg-gray-950 min-h-screen pb-20">
    <!-- Hero Section -->
    <section
      class="w-full h-[500px] bg-gray-950 relative overflow-hidden flex items-center justify-center p-5"
    >
      <div class="absolute inset-0 opacity-40">
        <img
          src="https://images.unsplash.com/photo-1522071820081-009f0129c71c?q=80&w=2070&auto=format&fit=crop"
          class="w-full h-full object-cover grayscale"
          alt="Banner Karier"
        />
      </div>
      <div
        class="absolute inset-0 bg-gradient-to-t from-gray-950 via-transparent to-gray-950/50"
      ></div>

      <div class="relative z-10 text-center max-w-4xl flex flex-col items-center gap-6">
        <span
          class="px-4 py-1.5 bg-[#024ad8]/20 backdrop-blur-md rounded-full text-blue-400 border border-[#024ad8]/30 font-medium text-xs uppercase tracking-widest"
        >
          Kami Sedang Merekrut
        </span>
        <h1 class="text-5xl md:text-7xl font-medium text-white tracking-tight leading-none">
          Berikan Karya Terbaikmu<br />
          <span class="text-transparent bg-clip-text bg-gradient-to-r from-[#024ad8] to-teal-400"
            >Di Blukios.</span
          >
        </h1>
        <p class="text-gray-400 text-lg md:text-xl max-w-2xl leading-relaxed">
          Bergabunglah dengan tim para pemimpi, pembangun, dan pelaku. Misi kami adalah membuat perdagangan bisa dijangkau semua orang, di mana saja.
        </p>
        <div class="flex gap-4 mt-4">
          <button
            class="px-8 py-3 bg-white text-gray-900 rounded-full font-medium hover:bg-gray-200 transition-colors"
            @click="$el.querySelector('#open-roles').scrollIntoView({ behavior: 'smooth' })"
          >
            Lihat Lowongan
          </button>
        </div>
      </div>
    </section>

    <!-- Culture / Gallery -->
    <section class="max-w-[1280px] mx-auto px-5 md:px-[52px] -mt-20 relative z-20">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white dark:bg-gray-900 p-8 rounded-2xl shadow-xl flex flex-col gap-4 border border-gray-100 dark:border-white/10">
          <div
            class="w-12 h-12 rounded-full bg-blue-50 dark:bg-[#024ad8]/20 flex items-center justify-center text-[#024ad8] text-xl"
          >
            <i class="fa-solid fa-heart"></i>
          </div>
          <h3 class="text-xl font-medium text-gray-900 dark:text-white">Keseimbangan Kerja dan Hidup</h3>
          <p class="text-gray-600 dark:text-gray-400 text-sm">
            Kami percaya bekerja cerdas, bukan sekadar keras. Jam kerja fleksibel dan budaya kerja yang mengutamakan remote.
          </p>
        </div>
        <div class="bg-white dark:bg-gray-900 p-8 rounded-2xl shadow-xl flex flex-col gap-4 border border-gray-100 dark:border-white/10">
          <div
            class="w-12 h-12 rounded-full bg-orange-50 dark:bg-orange-500/20 flex items-center justify-center text-orange-500 text-xl"
          >
            <i class="fa-solid fa-rocket"></i>
          </div>
          <h3 class="text-xl font-medium text-gray-900 dark:text-white">Pola Pikir Bertumbuh</h3>
          <p class="text-gray-600 dark:text-gray-400 text-sm">
            Tunjangan belajar, program mentoring, dan jalur pengembangan karier yang jelas.
          </p>
        </div>
        <div class="bg-white dark:bg-gray-900 p-8 rounded-2xl shadow-xl flex flex-col gap-4 border border-gray-100 dark:border-white/10">
          <div
            class="w-12 h-12 rounded-full bg-emerald-50 dark:bg-emerald-500/20 flex items-center justify-center text-emerald-500 text-xl"
          >
            <i class="fa-solid fa-users"></i>
          </div>
          <h3 class="text-xl font-medium text-gray-900 dark:text-white">Tim yang Inklusif</h3>
          <p class="text-gray-600 dark:text-gray-400 text-sm">
            Lingkungan yang beragam, tempat setiap suara didengar, dihargai, dan dihormati.
          </p>
        </div>
      </div>
    </section>

    <!-- Open Roles -->
    <section id="open-roles" class="max-w-[1280px] mx-auto px-5 md:px-[52px] py-24">
      <div class="flex justify-between items-end mb-12">
        <div>
          <h2 class="text-3xl font-medium text-gray-900 dark:text-white mb-2">Posisi Tersedia</h2>
          <p class="text-gray-600 dark:text-gray-400">Temukan tempat kamu di tim kami yang terus berkembang.</p>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div
          v-for="(role, index) in openRoles"
          :key="index"
          class="group bg-white dark:bg-gray-900 border border-gray-100 dark:border-white/10 p-8 rounded-2xl hover:border-[#024ad8] dark:hover:border-[#024ad8] hover:shadow-lg shadow-sm transition-all cursor-pointer flex flex-col gap-6 relative overflow-hidden"
        >
          <div class="flex justify-between items-start">
            <div>
              <span
                class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1 block"
                >{{ role.department }}</span
              >
              <h3
                class="text-2xl font-medium text-gray-900 dark:text-white group-hover:text-[#024ad8] transition-colors"
              >
                {{ role.title }}
              </h3>
            </div>
            <div
              class="w-10 h-10 rounded-full bg-gray-50 dark:bg-white/10 flex items-center justify-center group-hover:bg-[#024ad8] group-hover:text-white text-gray-500 transition-colors"
            >
              <i
                class="fa-solid fa-arrow-right -rotate-45 group-hover:rotate-0 transition-transform duration-300"
              ></i>
            </div>
          </div>

          <div class="flex flex-wrap gap-2 mt-auto">
            <span
              v-for="tag in role.tags"
              :key="tag"
              class="px-3 py-1 bg-gray-100 dark:bg-white/10 text-gray-600 dark:text-gray-300 text-xs rounded-full font-medium"
            >
              {{ tag }}
            </span>
          </div>

          <div
            class="pt-6 border-t border-gray-100 dark:border-white/10 flex items-center gap-4 text-sm text-gray-500 dark:text-gray-400 font-medium"
          >
            <span class="flex items-center gap-2"
              ><i class="fa-solid fa-location-dot"></i> {{ role.location }}</span
            >
            <span class="flex items-center gap-2"
              ><i class="fa-solid fa-clock"></i> {{ role.type }}</span
            >
          </div>
        </div>
      </div>
    </section>
  </main>
</template>
