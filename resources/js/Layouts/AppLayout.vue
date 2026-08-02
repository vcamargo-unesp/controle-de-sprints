<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { 
  LayoutDashboard, 
  Kanban, 
  ListTodo, 
  History, 
  GraduationCap, 
  UserCheck, 
  LogOut,
  ShieldAlert,
  Calculator,
  Award
} from 'lucide-vue-next';

const props = defineProps({
  userRole: String,
  userName: String,
  userEmail: String
});

const page = usePage();

const currentUserName = computed(() => {
  return page.props.auth?.user_name || props.userName || 'Usuário CTI';
});

const currentUserEmail = computed(() => {
  return page.props.auth?.user_email || props.userEmail || '';
});

const currentUserRole = computed(() => {
  return page.props.auth?.user_type || props.userRole || 'aluno';
});
</script>

<template>
  <div class="min-h-screen bg-slate-100 flex flex-col font-sans">
    <!-- Header Institucional CTI Bauru -->
    <header class="bg-[#0F2537] text-white shadow-md border-b-4 border-[#9B2C2C] sticky top-0 z-30">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-14">
          <!-- Logo & Marca -->
          <div class="flex items-center space-x-3">
            <div class="bg-white/10 p-1.5 rounded border border-white/20">
              <GraduationCap class="w-6 h-6 text-red-400" />
            </div>
            <div>
              <div class="flex items-center space-x-2">
                <span class="font-bold tracking-tight text-white text-base">Controle de Sprints</span>
                <span class="bg-[#9B2C2C] text-[10px] uppercase font-bold tracking-wider px-1.5 py-0.5 rounded text-white">
                  CTI Bauru
                </span>
              </div>
              <p class="text-[10px] text-slate-300 tracking-wide font-light hidden sm:block">
                Colégio Técnico Industrial "Prof. Isaac Portal Roldán" &bull; UNESP
              </p>
            </div>
          </div>

          <!-- Links de Navegação Superior -->
          <nav class="hidden md:flex items-center space-x-1 text-sm font-medium">
            <Link 
              href="/equipes" 
              class="px-3 py-1.5 rounded text-slate-200 hover:text-white hover:bg-white/10 transition flex items-center space-x-1.5"
            >
              <LayoutDashboard class="w-4 h-4" />
              <span>Painel de Equipes</span>
            </Link>

            <Link 
              v-if="currentUserRole === 'professor'"
              href="/notas" 
              class="px-3 py-1.5 rounded text-slate-200 hover:text-white hover:bg-white/10 transition flex items-center space-x-1.5"
            >
              <Calculator class="w-4 h-4" />
              <span>Painel de Notas</span>
            </Link>

            <Link 
              v-else
              href="/minhas-notas" 
              class="px-3 py-1.5 rounded text-slate-200 hover:text-white hover:bg-white/10 transition flex items-center space-x-1.5"
            >
              <Award class="w-4 h-4" />
              <span>Minhas Notas</span>
            </Link>
          </nav>

          <!-- Perfil do Usuário & Role Badge -->
          <div class="flex items-center space-x-3">
            <div class="text-right hidden sm:block">
              <div class="text-xs font-semibold text-white leading-tight">
                {{ currentUserName }}
              </div>
              <div class="text-[10px] text-slate-300 flex items-center justify-end space-x-1">
                <span class="capitalize font-mono text-[9px] px-1 bg-slate-800 rounded border border-slate-700 text-slate-300">
                  {{ currentUserRole }}
                </span>
                <span class="truncate max-w-[140px]">{{ currentUserEmail }}</span>
              </div>
            </div>

            <div class="w-8 h-8 rounded bg-slate-700 border border-slate-600 flex items-center justify-center text-xs font-bold text-white uppercase">
              {{ currentUserName.charAt(0) }}
            </div>
          </div>
        </div>
      </div>
    </header>

    <!-- Sub-bar de Navegação para telas menores -->
    <div class="md:hidden bg-[#1A365D] border-b border-slate-700 px-4 py-2 flex items-center justify-around text-xs text-white">
      <Link href="/equipes" class="flex flex-col items-center">
        <LayoutDashboard class="w-4 h-4" />
        <span>Equipes</span>
      </Link>
      <Link v-if="currentUserRole === 'professor'" href="/notas" class="flex flex-col items-center">
        <Calculator class="w-4 h-4" />
        <span>Notas</span>
      </Link>
      <Link v-else href="/minhas-notas" class="flex flex-col items-center">
        <Award class="w-4 h-4" />
        <span>Minhas Notas</span>
      </Link>
    </div>

    <!-- Conteúdo Principal com Alta Densidade SIEP -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-2 sm:px-4 lg:px-6 py-3">
      <slot />
    </main>

    <!-- Rodapé Institucional Rodapé SIEP -->
    <footer class="bg-white border-t border-slate-200 py-2.5 text-center text-xs text-slate-500 mt-auto">
      <div class="max-w-7xl mx-auto px-4 flex flex-col sm:flex-row items-center justify-between gap-1">
        <div>
          <strong>Colégio Técnico Industrial "Prof. Isaac Portal Roldán" - CTI Bauru</strong> &bull; UNESP
        </div>
        <div class="text-[11px] text-slate-400">
          Controle de Sprints &bull; Alta Densidade de Dados
        </div>
      </div>
    </footer>
  </div>
</template>
