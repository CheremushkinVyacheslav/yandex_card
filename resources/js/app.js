import { createApp } from 'vue';
import Login from './components/Login.vue';
import Settings from './components/Settings.vue';
import Reviews from './components/Reviews.vue';
import History from './components/History.vue';

const app = createApp({
  data() {
    return {
      authUser: null,
      view: 'login'
    };
  },
  created() {
    const token = localStorage.getItem('token');
    if (token) {
      this.authUser = true;
      this.view = 'settings';
      this.fetchUser();
    }
  },
  methods: {
    async fetchUser() {
      try {
        const res = await fetch('/api/user', { headers: { Authorization: `Bearer ${localStorage.getItem('token')}` } });
        if (!res.ok) {
          this.authUser = null;
          this.view = 'login';
          localStorage.removeItem('token');
        } else {
          this.authUser = await res.json();
        }
      } catch {
        this.authUser = null;
        this.view = 'login';
      }
    },
    handleAuth(user) {
      this.authUser = user;
      this.view = 'settings';
    },
    logout() {
      localStorage.removeItem('token');
      this.authUser = null;
      this.view = 'login';
    }
  },
  template: `
    <div class="min-h-screen flex flex-col">
      <header v-if="authUser" class="bg-white text-stone-900 border-b border-stone-200 sticky top-0 z-10">
        <div class="w-full max-w-4xl mx-auto px-4 py-2 grid grid-cols-[1fr_auto] items-center gap-x-2 sm:flex">
          <span class="font-bold tracking-tight text-black whitespace-nowrap sm:mr-2">Яндекс отзывы</span>
          <nav class="row-start-2 col-span-2 flex items-center gap-1 sm:flex-1" aria-label="Разделы">
            <button @click="view='settings'" :aria-current="view==='settings' ? 'page' : null" :class="['px-2 sm:px-3 py-2.5 text-sm transition', view==='settings' ? 'text-yandex font-medium underline decoration-yandex decoration-2 underline-offset-8' : 'text-stone-600 hover:text-yandex']">Настройки</button>
            <button @click="view='reviews'" :aria-current="view==='reviews' ? 'page' : null" :class="['px-2 sm:px-3 py-2.5 text-sm transition', view==='reviews' ? 'text-yandex font-medium underline decoration-yandex decoration-2 underline-offset-8' : 'text-stone-600 hover:text-yandex']">Отзывы</button>
            <button @click="view='history'" :aria-current="view==='history' ? 'page' : null" :class="['px-2 sm:px-3 py-2.5 text-sm transition', view==='history' ? 'text-yandex font-medium underline decoration-yandex decoration-2 underline-offset-8' : 'text-stone-600 hover:text-yandex']">История</button>
          </nav>
          <button @click="logout" aria-label="Выйти из аккаунта" class="col-start-2 row-start-1 ml-auto inline-flex items-center justify-center w-11 h-11 sm:w-auto sm:h-auto sm:px-3 sm:py-1.5 rounded-lg text-sm text-stone-500 hover:text-yandex transition shrink-0">
            <span class="hidden sm:inline">Выйти</span>
            <svg class="sm:hidden w-5 h-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 3H4v14h4"/><path d="M13 6l4 4-4 4"/><path d="M17 10H8"/></svg>
          </button>
        </div>
      </header>
      <main class="flex-1">
        <Login v-if="view==='login'" @authenticated="handleAuth" />
        <Settings v-if="view==='settings'" />
        <Reviews v-if="view==='reviews'" />
        <History v-if="view==='history'" />
      </main>
    </div>
  `,
  components: { Login, Settings, Reviews, History }
});

app.mount('#app');
