<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Breadcrumb from '@/Components/Breadcrumb.vue';
import { Link } from '@inertiajs/vue3';
import { Users, Kanban, CheckCircle, Clock } from 'lucide-vue-next';

defineProps({
  equipes: {
    type: Array,
    default: () => [
      { id: 1, nome: 'Equipe Alpha', projeto: 'Sistema SIEP Controle de Sprints', integrantes: 4, sprintAtiva: 'Sprint 3', progresso: 65 },
      { id: 2, nome: 'Equipe Beta', projeto: 'Gestão da Biblioteca CTI', integrantes: 5, sprintAtiva: 'Sprint 2', progresso: 40 },
      { id: 3, nome: 'Equipe Gamma', projeto: 'Portal de Estágios e Convênios', integrantes: 3, sprintAtiva: 'Sprint 4', progresso: 90 },
    ]
  }
});
</script>

<template>
  <AppLayout userRole="professor" userName="Prof. Isaac - CTI Bauru">
    <Breadcrumb :items="[{ label: 'Painel do Professor / Visão Geral' }]" />

    <div class="mt-2 space-y-3">
      <div class="flex items-center justify-between bg-white p-3 rounded border border-slate-200 shadow-sm">
        <div>
          <h1 class="text-base font-bold text-slate-800 tracking-tight">Painel de Acompanhamento das Equipes</h1>
          <p class="text-xs text-slate-500">Visão utilitária do professor para monitorar o andamento das Sprints.</p>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <div v-for="equipe in equipes" :key="equipe.id" class="bg-white border border-slate-200 rounded-md p-3 shadow-sm flex flex-col justify-between">
          <div>
            <div class="flex items-center justify-between mb-2">
              <span class="font-mono text-[10px] bg-slate-100 text-slate-700 font-bold px-1.5 py-0.5 rounded border">ID #{{ equipe.id }}</span>
              <span class="text-xs font-semibold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">{{ equipe.sprintAtiva }}</span>
            </div>

            <h2 class="text-sm font-bold text-slate-900 leading-tight mb-1">{{ equipe.nome }}</h2>
            <p class="text-xs text-slate-600 mb-3">{{ equipe.projeto }}</p>

            <div class="space-y-1 mb-3">
              <div class="flex justify-between text-[11px] font-semibold text-slate-600">
                <span>Progresso da Sprint</span>
                <span>{{ equipe.progresso }}%</span>
              </div>
              <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden border border-slate-200">
                <div class="bg-[#0F2537] h-full" :style="{ width: equipe.progresso + '%' }"></div>
              </div>
            </div>
          </div>

          <div class="pt-2 border-t border-slate-100 flex items-center justify-between">
            <span class="text-xs text-slate-500 flex items-center space-x-1">
              <Users class="w-3.5 h-3.5 text-slate-400" />
              <span>{{ equipe.integrantes }} Integrantes</span>
            </span>

            <Link 
              href="/kanban" 
              class="text-xs font-bold text-[#0F2537] hover:text-[#9B2C2C] flex items-center space-x-1"
            >
              <span>Ver Kanban</span>
              <Kanban class="w-3.5 h-3.5" />
            </Link>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
