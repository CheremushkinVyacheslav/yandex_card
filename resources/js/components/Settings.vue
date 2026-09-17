<template>
  <div class="w-full max-w-4xl mx-auto mt-10 px-4 pb-10">
    <h2 class="text-2xl font-extrabold tracking-tight mb-1">Настройки организации</h2>
    <p class="text-sm text-stone-500 mb-6">Вставьте ссылку на карточку в Яндекс Картах - отзывы подтянутся в фоне.</p>

    <form @submit.prevent="saveLink" class="bg-white rounded-xl border border-stone-200 p-6 space-y-4">
      <div>
        <label class="block text-xs font-medium text-stone-500 mb-1">Ссылка на Яндекс Карты</label>
        <input v-model="url" type="url" placeholder="https://yandex.ru/maps/org/..." class="w-full px-4 py-3 border border-stone-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-yandex focus:border-transparent transition" required />
      </div>
      <button type="submit" :disabled="loading" class="bg-yandex text-white font-semibold px-6 py-2.5 rounded-xl hover:bg-[#d93817] active:scale-[0.99] transition disabled:opacity-50">
        <span v-if="loading">Сохранение...</span>
        <span v-else>Сохранить и запустить парсинг</span>
      </button>
      <p v-if="message" role="status" :class="['text-sm rounded-xl px-3 py-2', messageClass]">{{ message }}</p>
    </form>

    <div class="bg-white rounded-xl border border-stone-200 p-6 mt-6">
      <h3 class="text-lg font-bold mb-3">Подключённые организации</h3>
      <div v-if="orgsLoading" class="text-stone-500 text-sm">Загрузка...</div>
      <div v-for="o in orgs" :key="o.id" class="flex items-center gap-3 py-3 border-b border-stone-100 last:border-0">
        <div class="flex-1 min-w-0">
          <div class="font-semibold truncate">{{ o.name }}</div>
          <div class="text-sm text-stone-500 truncate">{{ o.yandex_url }}</div>
          <div class="text-xs mt-1.5 flex items-center gap-2 flex-wrap">
            <span :class="['inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full font-medium', pillClass(o.status)]">
              <span class="w-1.5 h-1.5 rounded-full bg-current"></span>{{ statusLabel(o.status) }}
            </span>
            <span class="text-stone-500 tnum">★ {{ fmt(o.average_rating) }} · оценок {{ o.total_ratings }} · отзывов {{ o.total_reviews }}</span>
          </div>
          <div v-if="o.last_error" class="text-sm text-red-600 mt-1">{{ shortError(o.last_error) }}</div>
        </div>
      </div>
    </div>

    <div v-if="org" class="bg-white rounded-xl border border-stone-200 p-6 mt-6">
      <h3 class="text-lg font-bold mb-3">Организация</h3>
      <div class="grid grid-cols-3 gap-3">
        <div class="p-4 bg-yandex/5 rounded-xl text-center">
          <div class="text-xs text-stone-500 mb-1">Рейтинг</div>
          <div class="text-2xl font-extrabold text-yandex tnum">{{ fmt(org.average_rating) }}</div>
        </div>
        <div class="p-4 bg-stone-50 rounded-xl text-center">
          <div class="text-xs text-stone-500 mb-1">Оценок</div>
          <div class="text-2xl font-extrabold tnum">{{ org.total_ratings }}</div>
        </div>
        <div class="p-4 bg-stone-50 rounded-xl text-center">
          <div class="text-xs text-stone-500 mb-1">Отзывов</div>
          <div class="text-2xl font-extrabold tnum">{{ org.total_reviews }}</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { pillClass, statusLabel } from '../status.js';
const url = ref('');
const loading = ref(false);
const message = ref('');
const messageClass = ref('text-stone-600 bg-stone-100 border border-stone-200');
const org = ref(null);
const orgs = ref([]);
const orgsLoading = ref(true);

function fmt(v) { return (v === null || v === undefined) ? '-' : Number(v).toFixed(1).replace('.', ','); }
function shortError(e) { return e && e.length > 140 ? e.slice(0, 140) + '…' : e; }

async function loadOrgs() {
  orgsLoading.value = true;
  try {
    const token = localStorage.getItem('token');
    const res = await fetch('/api/organizations', { headers: { Authorization: `Bearer ${token}` } });
    orgs.value = await res.json();
  } finally {
    orgsLoading.value = false;
  }
}

async function saveLink() {
  loading.value = true;
  message.value = '';
  try {
    const token = localStorage.getItem('token');
    const res = await fetch('/api/settings', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${token}` },
      body: JSON.stringify({ yandex_url: url.value })
    });
    const data = await res.json();
    if (!res.ok) {
      message.value = data.message || 'Ошибка';
      messageClass.value = 'text-red-600 bg-red-50 border border-red-100';
      return;
    }
    message.value = data.message;
    messageClass.value = 'text-green-700 bg-green-50 border border-green-100';
    org.value = data.organization;
    await loadOrgs();
  } catch (e) {
    message.value = 'Ошибка сети';
    messageClass.value = 'text-red-600 bg-red-50 border border-red-100';
  } finally {
    loading.value = false;
  }
}

onMounted(loadOrgs);
</script>
