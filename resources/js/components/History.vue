<template>
  <div class="w-full max-w-4xl mx-auto mt-10 px-4 pb-10">
    <div class="mb-4">
      <h2 class="text-2xl font-extrabold tracking-tight">История парсингов</h2>
      <p v-if="orgName" class="text-sm text-stone-500 mt-0.5">{{ orgName }}</p>
    </div>

    <div v-if="loading" class="bg-white rounded-xl border border-stone-200 p-8 text-stone-500 text-sm">Загрузка...</div>
    <div v-if="error" class="bg-red-50 border border-red-100 text-red-600 text-sm rounded-xl px-4 py-3 mb-4">{{ error }}</div>

    <div v-if="!loading && runs.length === 0" class="bg-white rounded-xl border border-stone-200 p-8 text-sm text-stone-500 text-center">Парсингов пока не было — сохраните ссылку в Настройках, чтобы запустить первый парсинг.</div>

    <div v-if="!loading && runs.length > 0 && runs.length < 2" class="bg-stone-50 border border-stone-200 text-stone-600 text-sm rounded-xl px-4 py-3 mb-4">Пока только один прогон — запустите второй парсинг, чтобы видеть дельту (новые, изменённые, исчезнувшие).</div>

    <div v-for="run in runs" :key="run.id" class="bg-white rounded-xl border border-stone-200 mb-3 overflow-hidden">
      <button @click="toggleRun(run.id)" :aria-expanded="expandedId === run.id" :aria-controls="'run-' + run.id" class="w-full text-left px-5 py-4 flex items-center gap-3 hover:bg-stone-50 transition">
        <span :class="['w-2.5 h-2.5 rounded-full shrink-0', run.status === 'success' ? 'bg-green-500' : (run.status === 'parsing' ? 'bg-yandex motion-safe:animate-pulse' : 'bg-red-500')]" aria-hidden="true"></span>
        <div class="flex-1 min-w-0">
          <div class="font-semibold text-sm">#{{ run.id }} · {{ formatDateTime(run.started_at) }} · {{ statusLabel(run.status) }}</div>
          <div class="text-sm text-stone-600 tnum">найдено {{ run.found_total }} · новых {{ run.new_count }} · изменено {{ run.changed_count }} · исчезло {{ run.deleted_count }}</div>
        </div>
        <span class="text-stone-500 text-sm" aria-hidden="true">{{ expandedId === run.id ? '▲' : '▼' }}</span>
      </button>
      <div v-if="expandedId === run.id" :id="'run-' + run.id" class="border-t border-stone-100 px-5 py-4">
        <div v-if="detailLoading" class="text-sm text-stone-500">Загрузка деталей...</div>
        <template v-else>
          <div v-if="detail && detail.changes && detail.changes.length">
            <div class="text-xs font-bold uppercase tracking-wide text-stone-500 mb-2">Изменения</div>
            <div v-for="c in detail.changes" :key="c.id" class="mb-3 p-3 bg-stone-50 rounded-xl text-sm">
              <div class="font-semibold">{{ c.review?.author || ('Отзыв #' + c.review_id) }}</div>
              <div v-if="c.field === 'rating'" class="mt-1">оценка: <b>{{ c.old_value }} → {{ c.new_value }}</b></div>
              <div v-else class="mt-1">
                <div class="text-sm text-stone-500 mb-0.5">было:</div>
                <div class="line-through text-stone-500">{{ c.old_value }}</div>
                <div class="text-sm text-stone-500 mt-1.5 mb-0.5">стало:</div>
                <div>{{ c.new_value }}</div>
              </div>
            </div>
          </div>
          <div v-if="detail && detail.deleted && detail.deleted.length" class="mt-2">
            <div class="text-xs font-bold uppercase tracking-wide text-stone-500 mb-2">Исчезли из источника</div>
            <div v-for="d in detail.deleted" :key="d.id" class="mb-2 p-3 bg-stone-100 rounded-xl text-sm text-stone-500">
              <span class="font-semibold text-stone-600">{{ d.author }}</span>
              <span class="text-sm text-stone-500 tnum"> · {{ d.rating }}/5 · {{ formatDate(d.review_date) }}</span>
              <p class="mt-1 line-clamp-3">{{ d.text }}</p>
            </div>
          </div>
          <div v-if="detail && !(detail.changes?.length) && !(detail.deleted?.length)" class="text-sm text-stone-500">Изменений в этом прогоне нет - источник не менялся.</div>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { statusLabel } from '../status.js';

const runs = ref([]);
const orgId = ref(null);
const orgName = ref('');
const loading = ref(true);
const error = ref('');
const expandedId = ref(null);
const detail = ref(null);
const detailLoading = ref(false);

function formatDateTime(s) {
  if (!s) return '';
  return new Date(s).toLocaleString('ru-RU', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
}
function formatDate(s) {
  if (!s) return '';
  return new Date(s).toLocaleDateString('ru-RU');
}
function auth() {
  return { Authorization: `Bearer ${localStorage.getItem('token')}` };
}

async function loadRuns() {
  loading.value = true;
  error.value = '';
  expandedId.value = null;
  detail.value = null;
  try {
    if (!orgId.value) {
      const sres = await fetch('/api/settings', { headers: auth() });
      if (sres.ok) {
        const sdata = await sres.json();
        if (sdata.organization?.id) orgId.value = sdata.organization.id;
      }
      if (!orgId.value) {
        error.value = 'Нет организаций - добавьте ссылку в настройках';
        return;
      }
    }
    const rr = await fetch(`/api/parse-runs?organization_id=${orgId.value}`, { headers: auth() });
    const data = await rr.json();
    if (!rr.ok) {
      error.value = data.message || 'Ошибка загрузки';
      return;
    }
    runs.value = data.data || [];
    orgName.value = data.organization?.name || '';
  } catch (e) {
    error.value = 'Сетевая ошибка';
  } finally {
    loading.value = false;
  }
}

async function toggleRun(id) {
  if (expandedId.value === id) {
    expandedId.value = null;
    return;
  }
  expandedId.value = id;
  detail.value = null;
  detailLoading.value = true;
  try {
    const res = await fetch(`/api/parse-runs/${id}`, { headers: auth() });
    detail.value = await res.json();
  } finally {
    detailLoading.value = false;
  }
}

onMounted(loadRuns);
</script>
