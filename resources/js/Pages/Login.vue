<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { LogIn, GraduationCap, ShieldCheck } from 'lucide-vue-next';

defineProps({
  error: String
});
</script>

<template>
  <Head title="Login - Controle de Sprints CTI" />

  <div class="min-h-screen bg-slate-900 flex flex-col justify-center items-center p-4 antialiased font-sans">
    <!-- Card Central de Login Unificado -->
    <div class="max-w-md w-full bg-white rounded-xl shadow-2xl overflow-hidden border border-slate-200">
      <!-- Header do Card -->
      <div class="bg-[#0F2537] p-6 text-center border-b-4 border-[#9B2C2C]">
        <div class="inline-flex p-3 bg-white/10 rounded-full border border-white/20 mb-3">
          <GraduationCap class="w-10 h-10 text-red-400" />
        </div>
        <h1 class="text-xl font-extrabold text-white tracking-tight">Controle de Sprints</h1>
        <p class="text-xs text-slate-300 mt-1 font-medium">
          Colégio Técnico Industrial "Prof. Isaac Portal Roldán" &bull; UNESP
        </p>
      </div>

      <!-- Corpo com Ação Única Google -->
      <div class="p-8 space-y-6 text-center">
        <div>
          <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wider">Acesso Institucional</h2>
          <p class="text-xs text-slate-500 mt-1">
            Entre com a sua conta Google cadastrada no CTI Bauru para acessar seu ambiente.
          </p>
        </div>

        <div v-if="error" class="bg-red-50 text-red-700 text-xs p-3 rounded border border-red-200 font-medium">
          {{ error }}
        </div>

        <!-- Botão Único Google Auth -->
        <a 
          href="/auth/google" 
          class="w-full inline-flex items-center justify-center space-x-3 bg-white border-2 border-slate-300 hover:border-slate-400 text-slate-700 font-bold py-3 px-4 rounded-lg shadow-sm hover:bg-slate-50 transition cursor-pointer group"
        >
          <svg class="w-5 h-5" viewBox="0 0 24 24">
            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
          </svg>
          <span class="text-sm">Entrar com Google</span>
        </a>
      </div>

      <!-- Rodapé do Card -->
      <div class="bg-slate-50 p-3 text-center text-[10px] text-slate-400 border-t border-slate-100">
        <ShieldCheck class="w-3.5 h-3.5 inline mr-1 text-slate-400" />
        Sistema restrito aos membros do CTI Bauru - UNESP
      </div>
    </div>
  </div>
</template>
