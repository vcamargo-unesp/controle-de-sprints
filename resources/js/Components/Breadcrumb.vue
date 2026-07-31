<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
  items: {
    type: Array,
    default: () => []
  }
});
</script>

<template>
  <nav class="flex items-center text-xs text-slate-500 font-medium my-2 px-1" aria-label="Breadcrumb">
    <ol class="inline-flex items-center space-x-1.5 flex-wrap">
      <li class="inline-flex items-center">
        <Link href="/" class="hover:text-slate-800 transition-colors">
          Início
        </Link>
      </li>
      <template v-for="(item, index) in items" :key="index">
        <li class="flex items-center space-x-1.5">
          <span class="text-slate-400">/</span>
          <Link 
            v-if="item.href && index !== items.length - 1" 
            :href="item.href" 
            class="hover:text-slate-800 transition-colors"
          >
            {{ item.label }}
          </Link>
          <span v-else class="text-slate-900 font-semibold">
            {{ item.label }}
          </span>
        </li>
      </template>
    </ol>
  </nav>
</template>
