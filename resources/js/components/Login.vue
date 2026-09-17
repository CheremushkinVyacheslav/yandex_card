<template>
  <div class="min-h-[80vh] flex items-center justify-center px-4">
    <div class="w-full max-w-md">
      <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-yandex text-white text-2xl font-black mb-4">Я</div>
        <h1 class="text-2xl font-extrabold tracking-tight">Карточки и отзывы</h1>
        <p class="text-sm text-stone-500 mt-1">Парсинг организаций с Яндекс Карт</p>
      </div>
      <form @submit.prevent="login" class="bg-white rounded-xl border border-stone-200 p-8 space-y-4">
        <h2 class="text-lg font-bold">Вход</h2>
        <div>
          <label class="block text-xs font-medium text-stone-500 mb-1">Email</label>
          <input v-model="email" type="email" autocomplete="email" placeholder="admin@local.test" :aria-invalid="!!error" aria-describedby="login-error" class="w-full px-4 py-2.5 border border-stone-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-yandex focus:border-transparent transition" required />
        </div>
        <div>
          <label class="block text-xs font-medium text-stone-500 mb-1">Пароль</label>
          <input v-model="password" type="password" autocomplete="current-password" placeholder="••••••••" :aria-invalid="!!error" aria-describedby="login-error" class="w-full px-4 py-2.5 border border-stone-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-yandex focus:border-transparent transition" required />
        </div>
        <button type="submit" class="w-full bg-yandex text-white font-semibold py-2.5 rounded-xl hover:bg-[#d93817] active:scale-[0.99] transition">Войти</button>
        <p v-if="error" id="login-error" role="alert" class="text-red-600 text-sm bg-red-50 border border-red-100 rounded-xl px-3 py-2">{{ error }}</p>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';

const email = ref('admin@local.test');
const password = ref('password');
const error = ref('');

const emit = defineEmits(['authenticated']);

async function login() {
  error.value = '';
  try {
    const res = await fetch('/api/login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email: email.value, password: password.value })
    });
    const data = await res.json();
    if (!res.ok) {
      error.value = data.message || 'Ошибка входа';
      return;
    }
    localStorage.setItem('token', data.token);
    emit('authenticated', data.user);
  } catch (e) {
    error.value = 'Сетевая ошибка';
  }
}
</script>
