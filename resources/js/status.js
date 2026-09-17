// Единый словарь статусов парсинга (переиспользуется в Settings / Reviews / History).
// Семантические цвета успеха/ошибки/капчи сохранены, «в процессе» — в фирменном акценте.

export const statusNames = {
  pending: 'в очереди',
  parsing: 'парсится',
  success: 'готово',
  failed: 'ошибка',
  captcha: 'капча',
};

export const pillColors = {
  pending: 'bg-stone-100 text-stone-600',
  parsing: 'bg-yandex/10 text-[#a82a0e]',
  success: 'bg-green-100 text-green-700',
  failed: 'bg-red-100 text-red-700',
  captcha: 'bg-orange-100 text-orange-700',
};

export function statusLabel(s) {
  return statusNames[s] || s;
}

export function pillClass(s) {
  return pillColors[s] || 'bg-stone-100 text-stone-600';
}
