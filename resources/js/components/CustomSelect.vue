<template>
  <div ref="root" class="relative">
    <button
      ref="trigger"
      :id="id"
      type="button"
      :aria-label="ariaLabel"
      aria-haspopup="listbox"
      :aria-expanded="open"
      :aria-controls="open ? listId : null"
      @click="toggle"
      @keydown="onKeydown"
      class="w-full inline-flex items-center justify-between gap-2 min-h-11 px-3 py-2 border border-stone-200 rounded-xl text-sm bg-white hover:border-stone-300 transition"
    >
      <span class="truncate">{{ currentLabel }}</span>
      <svg :class="['w-4 h-4 text-stone-500 transition-transform shrink-0', open ? 'rotate-180' : '']" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 8 5 5 5-5"/></svg>
    </button>
    <ul
      v-if="open"
      :id="listId"
      role="listbox"
      :aria-label="ariaLabel"
      :aria-activedescendant="optionId(active)"
      class="absolute left-0 right-0 top-[calc(100%+4px)] z-20 bg-white border border-stone-200 rounded-xl py-1 shadow-lg max-h-72 overflow-auto"
    >
      <li
        v-for="(o, i) in options"
        :key="o.value"
        :id="optionId(i)"
        role="option"
        :aria-selected="o.value === modelValue"
        @click="choose(o)"
        @mousemove="active = i"
        :class="['flex items-center justify-between gap-2 min-h-11 px-3 py-2 text-sm cursor-pointer', i === active ? 'bg-stone-100' : '', o.value === modelValue ? 'text-yandex font-semibold' : 'text-stone-700']"
      >
        <span class="truncate">{{ o.label }}</span>
        <svg v-if="o.value === modelValue" class="w-4 h-4 text-yandex shrink-0" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m4 10 4 4 7-8"/></svg>
      </li>
    </ul>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';

const props = defineProps({
  modelValue: { type: [String, Number], default: null },
  options: { type: Array, required: true },
  ariaLabel: { type: String, required: true },
  id: { type: String, default: null },
});
const emit = defineEmits(['update:modelValue']);

const open = ref(false);
const active = ref(0);
const root = ref(null);
const trigger = ref(null);
const listId = 'cs-list-' + Math.random().toString(36).slice(2, 8);

const currentLabel = computed(() => props.options.find(o => o.value === props.modelValue)?.label ?? '');
function optionId(i) { return listId + '-opt-' + i; }

function openList() {
  open.value = true;
  const idx = props.options.findIndex(o => o.value === props.modelValue);
  active.value = idx >= 0 ? idx : 0;
}
function close(refocus = false) {
  open.value = false;
  if (refocus) nextTick(() => trigger.value?.focus());
}
function toggle() { open.value ? close(true) : openList(); }
function choose(o) {
  if (!o) return;
  if (o.value !== props.modelValue) emit('update:modelValue', o.value);
  close(true);
}
function onKeydown(e) {
  if (e.key === 'Escape') {
    if (open.value) { e.preventDefault(); close(true); }
    return;
  }
  if (e.key === 'Tab') { open.value = false; return; }
  if (!open.value) {
    if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(e.key)) { e.preventDefault(); openList(); }
    return;
  }
  if (e.key === 'ArrowDown') { e.preventDefault(); active.value = Math.min(active.value + 1, props.options.length - 1); }
  else if (e.key === 'ArrowUp') { e.preventDefault(); active.value = Math.max(active.value - 1, 0); }
  else if (e.key === 'Home') { e.preventDefault(); active.value = 0; }
  else if (e.key === 'End') { e.preventDefault(); active.value = props.options.length - 1; }
  else if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); choose(props.options[active.value]); }
}
function onDocPointerDown(e) {
  if (open.value && root.value && !root.value.contains(e.target)) open.value = false;
}
onMounted(() => document.addEventListener('pointerdown', onDocPointerDown));
onUnmounted(() => document.removeEventListener('pointerdown', onDocPointerDown));
</script>
