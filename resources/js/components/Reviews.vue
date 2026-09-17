<template>
  <div class="w-full max-w-4xl mx-auto mt-10 px-4 pb-10">
    <div class="mb-4 flex flex-wrap items-center gap-x-4 gap-y-2">
      <h2 class="text-2xl font-extrabold tracking-tight">Отзывы</h2>
      <div v-if="orgOptions.length > 1" class="w-full sm:w-72 sm:ml-auto">
        <label class="sr-only" for="reviews-org">Выбор организации</label>
        <CustomSelect
          id="reviews-org"
          v-model="selectedOrgId"
          :aria-label="'Выбор организации'"
          :options="orgOptions"
        />
      </div>
      <p v-else-if="orgInfo" class="w-full sm:w-auto text-sm text-stone-500 sm:ml-auto truncate">{{ orgInfo.name }}</p>
    </div>

    <div v-if="loading && !summary" class="bg-white rounded-xl border border-stone-200 p-8 text-stone-500 text-sm">Загрузка...</div>
    <div v-if="error" class="bg-red-50 border border-red-100 text-red-600 text-sm rounded-xl px-4 py-3 mb-4">{{ error }}</div>

    <div v-if="orgInfo && orgInfo.status !== 'success'" class="bg-stone-50 border border-stone-200 text-stone-600 text-sm rounded-xl px-4 py-3 mb-4 flex items-center gap-2 flex-wrap">
      <span :class="['inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full font-medium', pillClass(orgInfo.status)]">
        <span class="w-1.5 h-1.5 rounded-full bg-current motion-safe:animate-pulse"></span>{{ statusLabel(orgInfo.status) }}
      </span>
      <span v-if="orgInfo.last_error">{{ shortError(orgInfo.last_error) }}</span>
      <span v-else>данные появятся автоматически</span>
    </div>

    <div v-if="summary" class="bg-white rounded-xl border border-stone-200 p-6 mb-4 flex gap-6 items-center flex-wrap">
      <div class="text-center">
        <div class="text-5xl font-black tracking-tight tnum">{{ fmt(summary.average) }}</div>
        <div class="mt-1 inline-flex relative shrink-0" role="img" :aria-label="'Средняя оценка ' + fmt(summary.average) + ' из 5'">
          <div class="flex gap-0.5 text-stone-200" aria-hidden="true">
            <svg v-for="i in 5" :key="'sbg' + i" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10 1.5l2.6 5.6 6.1.7-4.5 4.2 1.2 6-5.4-3-5.4 3 1.2-6L1.3 7.8l6.1-.7L10 1.5z"/></svg>
          </div>
          <div class="absolute left-0 top-0 h-full overflow-hidden" :style="{ width: starPct(summary.average) + '%' }" aria-hidden="true">
            <div class="flex gap-0.5 text-yandex w-max">
              <svg v-for="i in 5" :key="'sfg' + i" class="w-4 h-4 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path d="M10 1.5l2.6 5.6 6.1.7-4.5 4.2 1.2 6-5.4-3-5.4 3 1.2-6L1.3 7.8l6.1-.7L10 1.5z"/></svg>
            </div>
          </div>
        </div>
        <div class="text-sm text-stone-500 mt-1 tnum">{{ summary.total_ratings }} оценок</div>
      </div>
      <div class="flex-1 min-w-[220px] space-y-1.5">
        <div v-for="s in [5,4,3,2,1]" :key="s" class="flex items-center gap-2 text-sm">
          <span class="w-3 text-stone-500 font-medium tnum">{{ s }}</span>
          <div class="flex-1 h-2 bg-stone-100 rounded-full overflow-hidden">
            <div class="h-full bg-yandex rounded-full transition-all" :style="{ width: barPct(s) + '%' }"></div>
          </div>
          <span class="w-10 text-right text-stone-500 tnum">{{ distCount(s) }}</span>
        </div>
        <div class="text-sm text-stone-500 pt-1 tnum">В базе: {{ distTotal }} · в карточке: {{ summary.total_reviews }}</div>
      </div>
    </div>

    <div class="bg-white rounded-xl border border-stone-200 p-4 mb-4 space-y-3">
      <div class="flex gap-4 items-center flex-wrap text-sm">
        <label :class="['inline-flex items-center gap-1.5 select-none', hasBaseline ? 'cursor-pointer' : 'cursor-not-allowed text-stone-500']">
          <input type="checkbox" v-model="onlyNew" :disabled="!hasBaseline" class="w-4 h-4 accent-yandex disabled:opacity-50" />
          <span>Только новые с последнего скана<span v-if="hasBaseline"> ({{ newCount }})</span></span>
          <span v-if="!hasBaseline" class="text-sm text-stone-500">- доступно после второго парсинга</span>
        </label>
        <label :class="['inline-flex items-center gap-1.5 select-none', hasBaseline ? 'cursor-pointer' : 'cursor-not-allowed text-stone-500']">
          <input type="checkbox" v-model="onlyChanged" :disabled="!hasBaseline" class="w-4 h-4 accent-yandex disabled:opacity-50" />
          <span>Только изменённые<span v-if="hasBaseline"> ({{ changedCount }})</span></span>
          <span v-if="!hasBaseline" class="text-sm text-stone-500">- доступно после второго парсинга</span>
        </label>
        <label class="inline-flex items-center gap-1.5 cursor-pointer select-none">
          <input type="checkbox" v-model="showDeleted" class="w-4 h-4 accent-yandex" /> Показать удалённые из источника
        </label>
      </div>
      <div class="flex gap-3 items-center flex-wrap">
      <div class="flex items-center gap-1" role="group" aria-label="Фильтр по оценке">
        <button v-for="s in [5,4,3,2,1]" :key="s" @click="toggleRating(s)" :aria-pressed="ratingFilter === s" :aria-label="'Показать отзывы с оценкой ' + s"
          :class="['w-11 h-11 rounded-lg text-lg leading-none transition inline-flex items-center justify-center', ratingFilter === s ? 'bg-yandex text-white font-bold shadow' : 'text-stone-500 hover:text-yandex hover:bg-stone-100']">★</button>
        <button v-if="ratingFilter" @click="toggleRating(null)" class="ml-1 min-h-11 px-2 text-sm text-stone-500 hover:text-yandex underline">сброс</button>
      </div>
      <div class="w-full sm:w-56">
        <label class="sr-only" for="reviews-sort">Сортировка отзывов</label>
        <CustomSelect
          id="reviews-sort"
          v-model="sortOrder"
          :aria-label="'Сортировка отзывов'"
          :options="sortOptions"
        />
      </div>
      <div class="flex-1 min-w-[180px] relative">
        <label class="sr-only" for="reviews-search">Поиск по тексту отзывов</label>
        <input id="reviews-search" v-model="searchQuery" type="search" placeholder="Поиск по тексту отзывов…" class="w-full px-3 py-2 pl-9 border border-stone-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-yandex focus:border-transparent" />
        <svg class="absolute left-3 top-2.5 w-4 h-4 text-stone-500" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="9" cy="9" r="6"/><path d="M13.5 13.5 17 17"/></svg>
      </div>
      </div>
    </div>

    <div ref="listTop" :aria-busy="loading" class="bg-white rounded-xl border border-stone-200 divide-y divide-stone-100 scroll-mt-20">
      <div v-if="!loading && reviews.length === 0" class="p-8 text-sm text-stone-500 text-center">{{ emptyMessage }}</div>
      <article v-for="r in reviews" :key="r.id" :aria-labelledby="'review-author-' + r.id" :class="['p-5', r.is_deleted ? 'bg-stone-50' : '']">
        <div class="flex items-center gap-3 mb-2">
          <img v-if="r.avatar_url" :src="r.avatar_url" alt="" loading="lazy" class="w-9 h-9 rounded-full object-cover shrink-0 bg-stone-100" @error="avatarFailed(r)" />
          <div v-else class="w-9 h-9 rounded-full bg-stone-200 text-stone-600 flex items-center justify-center font-bold text-sm shrink-0" aria-hidden="true">{{ initial(r.author_name || r.author) }}</div>
          <div class="min-w-0">
            <div :id="'review-author-' + r.id" class="font-semibold text-sm leading-tight truncate" :class="r.is_deleted ? 'text-stone-500' : ''">
              {{ r.author_name || r.author }}
              <span v-if="hasBaseline && r.created_in_run_id && r.created_in_run_id === lastRunId" class="ml-1.5 align-middle text-[10px] font-bold uppercase tracking-wide bg-green-100 text-green-700 rounded-full px-2 py-0.5">новый</span>
              <span v-else-if="hasBaseline && r.updated_in_run_id && r.updated_in_run_id === lastRunId" class="ml-1.5 align-middle text-[10px] font-bold uppercase tracking-wide bg-yandex/10 text-[#a82a0e] rounded-full px-2 py-0.5">изменён</span>
              <span v-if="r.is_deleted" class="ml-1.5 align-middle text-[10px] font-bold uppercase tracking-wide bg-stone-200 text-stone-600 rounded-full px-2 py-0.5">удалён из источника</span>
            </div>
            <div class="text-sm text-stone-600 tnum">{{ r.author_level ? r.author_level + ' · ' : '' }}{{ formatDate(r.review_date) }}</div>
          </div>
          <div class="ml-auto shrink-0 inline-flex relative" role="img" :aria-label="'Оценка ' + r.rating + ' из 5'">
            <div class="flex gap-0.5 text-stone-200" aria-hidden="true">
              <svg v-for="i in 5" :key="'rbg' + r.id + i" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10 1.5l2.6 5.6 6.1.7-4.5 4.2 1.2 6-5.4-3-5.4 3 1.2-6L1.3 7.8l6.1-.7L10 1.5z"/></svg>
            </div>
            <div class="absolute left-0 top-0 h-full overflow-hidden" :style="{ width: starPct(r.rating) + '%' }" aria-hidden="true">
              <div class="flex gap-0.5 text-yandex w-max">
                <svg v-for="i in 5" :key="'rfg' + r.id + i" class="w-4 h-4 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path d="M10 1.5l2.6 5.6 6.1.7-4.5 4.2 1.2 6-5.4-3-5.4 3 1.2-6L1.3 7.8l6.1-.7L10 1.5z"/></svg>
              </div>
            </div>
          </div>
        </div>
        <p class="text-sm leading-relaxed whitespace-pre-line" :class="r.is_deleted ? 'text-stone-500' : 'text-stone-800'">{{ r.text }}</p>
        <div v-if="r.business_reply" class="mt-3 p-3 bg-stone-50 border-l-2 border-yandex rounded-r-xl text-sm text-stone-600">
          <span class="font-semibold text-stone-700">Ответ бизнеса:</span> {{ r.business_reply }}
        </div>
      </article>
    </div>

    <nav v-if="pagination && pagination.last_page > 1" aria-label="Страницы отзывов" class="flex items-center justify-center gap-1.5 mt-6 flex-wrap">
      <button @click="loadPage(pagination.current_page - 1, true)" :disabled="pagination.current_page <= 1" aria-label="Предыдущая страница" class="min-w-11 min-h-11 px-3 py-1.5 rounded-lg text-sm bg-white border border-stone-200 hover:bg-stone-100 disabled:opacity-40 transition">←</button>
      <button v-for="p in pageWindow()" :key="p" @click="typeof p === 'number' && loadPage(p, true)" :disabled="typeof p !== 'number'" :aria-label="typeof p === 'number' ? 'Страница ' + p : null" :aria-current="p === pagination.current_page ? 'page' : null" :class="['min-w-11 min-h-11 px-3 py-1.5 rounded-lg text-sm tnum transition', p === pagination.current_page ? 'bg-yandex text-white font-semibold' : (typeof p === 'number' ? 'bg-white border border-stone-200 hover:bg-stone-100' : 'bg-transparent text-stone-500')]">
        {{ p }}
      </button>
      <button @click="loadPage(pagination.current_page + 1, true)" :disabled="pagination.current_page >= pagination.last_page" aria-label="Следующая страница" class="min-w-11 min-h-11 px-3 py-1.5 rounded-lg text-sm bg-white border border-stone-200 hover:bg-stone-100 disabled:opacity-40 transition">→</button>
    </nav>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { pillClass, statusLabel } from '../status.js';
import CustomSelect from './CustomSelect.vue';

const reviews = ref([]);
const loading = ref(true);
const error = ref('');
const pagination = ref(null);
const summary = ref(null);
const orgId = ref(null);
const orgInfo = ref(null);
const orgs = ref([]);
const selectedOrgId = ref(null);
const ratingFilter = ref(null);
const sortOrder = ref('date');
const searchQuery = ref('');
const onlyNew = ref(false);
const onlyChanged = ref(false);
const showDeleted = ref(false);
const lastRunId = ref(null);
const runsCount = ref(0);
const hasBaseline = ref(false);
const newCount = ref(0);
const changedCount = ref(0);
const listTop = ref(null);
let searchTimer = null;

const sortOptions = [
  { value: 'date', label: 'По дате (как в источнике)' },
  { value: 'new_first', label: 'Сначала новые' },
  { value: 'old', label: 'Сначала старые' },
  { value: 'rating', label: 'По оценке' },
];

const orgOptions = computed(() =>
  orgs.value.map(o => ({
    value: o.id,
    label: (o.status === 'success' ? '★ ' : '') + (o.name || 'Организация') + (o.total_reviews ? ' · ' + o.total_reviews : ''),
  }))
);

const distTotal = computed(() =>
  Object.values(summary.value?.distribution || {}).reduce((a, b) => a + Number(b), 0)
);
const emptyMessage = computed(() => {
  if (!hasBaseline.value && (onlyNew.value || onlyChanged.value))
    return 'Для сравнения нужно минимум два парсинга - запусти парсинг ещё раз';
  if (onlyChanged.value) return 'Изменений с прошлого скана нет';
  if (onlyNew.value) return 'Новых отзывов с последнего скана нет';
  if (showDeleted.value) return 'Удалённых из источника отзывов нет - база синхронна';
  if (searchQuery.value.trim() !== '') return `По запросу «${searchQuery.value.trim()}» ничего не найдено`;
  return 'Отзывов не найдено - попробуй сбросить фильтры';
});

function fmt(v) { return (v === null || v === undefined) ? '-' : Number(v).toFixed(1).replace('.', ','); }
function shortError(e) { return e && e.length > 140 ? e.slice(0, 140) + '…' : e; }
function initial(name) { return (name || '?').trim().charAt(0).toUpperCase(); }
function avatarFailed(r) { r.avatar_url = null; }
function toggleRating(s) { ratingFilter.value = (ratingFilter.value === s) ? null : s; }
function formatDate(dateStr) {
  if (!dateStr) return '';
  return new Date(dateStr).toLocaleDateString('ru-RU');
}
function starPct(rating) {
  const r = Number(rating) || 0;
  return Math.max(0, Math.min(100, (r / 5) * 100));
}
function distCount(s) {
  const d = summary.value?.distribution || {};
  return Number(d[s] ?? d[String(s)] ?? 0);
}
function barPct(s) {
  const total = Object.values(summary.value?.distribution || {}).reduce((a, b) => a + Number(b), 0);
  if (!total) return 0;
  return Math.round((distCount(s) / total) * 100);
}
function pageWindow() {
  const cur = pagination.value.current_page, last = pagination.value.last_page;
  const pages = new Set([1, last, cur - 1, cur, cur + 1]);
  const list = [...pages].filter(p => p >= 1 && p <= last).sort((a, b) => a - b);
  const out = [];
  list.forEach((p, i) => {
    if (i > 0 && p - list[i - 1] > 1) out.push('…');
    out.push(p);
  });
  return out;
}

async function loadPage(page = 1, scroll = false) {
  loading.value = true;
  error.value = '';
  try {
    const token = localStorage.getItem('token');
    if (!orgs.value.length) {
      const ores = await fetch('/api/organizations', { headers: { Authorization: `Bearer ${token}` } });
      if (ores.ok) {
        orgs.value = await ores.json();
        if (!orgId.value && orgs.value.length) orgId.value = Number(orgs.value[0].id);
      }
    }
    if (!orgId.value) {
      const sres = await fetch('/api/settings', { headers: { Authorization: `Bearer ${token}` } });
      if (sres.ok) {
        const sdata = await sres.json();
        if (sdata.organization?.id) orgId.value = sdata.organization.id;
      }
      if (!orgId.value) {
        error.value = 'Нет организаций - добавьте ссылку в настройках';
        return;
      }
    }
    const params = new URLSearchParams({ page: String(page) });
    if (orgId.value) params.set('organization_id', String(orgId.value));
    if (ratingFilter.value) params.set('rating', String(ratingFilter.value));
    if (sortOrder.value && sortOrder.value !== 'date') params.set('sort', sortOrder.value);
    if (searchQuery.value.trim() !== '') params.set('q', searchQuery.value.trim());
    if (onlyNew.value) params.set('only_new', '1');
    if (onlyChanged.value) params.set('only_changed', '1');
    if (showDeleted.value) params.set('show_deleted', '1');
    const res = await fetch(`/api/reviews?${params.toString()}`, {
      headers: { Authorization: `Bearer ${token}` }
    });
    const data = await res.json();
    if (!res.ok) {
      error.value = data.message || 'Ошибка загрузки';
      return;
    }
    reviews.value = data.data || [];
    pagination.value = data.meta || null;
    summary.value = data.rating_summary || null;
    orgInfo.value = data.organization || null;
    lastRunId.value = data.meta?.last_run_id ?? null;
    runsCount.value = data.meta?.runs_count ?? 0;
    hasBaseline.value = !!data.meta?.has_baseline;
    newCount.value = data.meta?.new_count ?? 0;
    changedCount.value = data.meta?.changed_count ?? 0;
    if (data.organization?.id && !orgId.value) orgId.value = data.organization.id;
  } catch (e) {
    error.value = 'Сетевая ошибка';
  } finally {
    loading.value = false;
    if (scroll && listTop.value) {
      const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      listTop.value.scrollIntoView({ behavior: reduced ? 'auto' : 'smooth', block: 'start' });
    }
  }
}

onMounted(() => {
  loadPage(1);
});

watch(selectedOrgId, (val) => {
  if (val && val !== orgId.value) {
    orgId.value = val;
    loadPage(1, true);
  }
});
watch([ratingFilter, sortOrder, onlyNew, onlyChanged, showDeleted], () => loadPage(1, true));
watch(searchQuery, () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => loadPage(1, true), 400);
});
</script>
