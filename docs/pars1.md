## Нет, не с нуля! Ты просто добавляешь парсер в готовый каркас

Смотри, что у тебя уже есть (скорее всего):
- ✅ Laravel установлен
- ✅ БД создана (таблицы `organizations`, `reviews` через миграции)
- ✅ Модели `Organization`, `Review`
- ✅ Vue-фронтенд с экранами (форма URL, список организаций, отзывы)
- ✅ Контроллеры для CRUD
- ✅ Роуты

**Что нужно добавить:** ~6 файлов парсера поверх этого. Это как вставить двигатель в уже собранную машину.

---

## Пошаговый план (30 минут)

### Шаг 1. Проверь что уже есть в проекте

Открой терминал в корне Laravel и выполни:
```bash
ls app/Models/
ls app/Http/Controllers/
php artisan route:list
```

Ты должен увидеть:
- `Organization.php`, `Review.php` — модели
- Какой-то контроллер, например `OrganizationController.php`
- Роуты вида `GET /organizations`, `POST /organizations/parse`

### Шаг 2. Создай папки для парсера

```bash
mkdir -p app/Services
mkdir -p app/Clients
mkdir -p app/DTOs
mkdir -p app/Exceptions
```

### Шаг 3. Скопируй файлы из промпта

Возьми промпт, который я дал выше (большой текст с кодом PHP), и попроси ИИ сгенерировать файлы. Или попроси меня сгенерировать их прямо сейчас по одному.

**Порядок создания (зависимости):**
1. `Exceptions` (CaptchaDetectedException, SourceChangedException) — ни от кого не зависят
2. `DTOs` (OrganizationData, ReviewData) — ни от кого не зависят
3. `Clients/YandexMapsClient` — зависит от Exceptions
4. `Services/YandexMapsParser` — зависит от Client + DTOs + моделей
5. `Console/Commands/ParseOrganizationCommand` — зависит от Service

### Шаг 4. Привяжи парсер к существующему контроллеру

Открой свой `OrganizationController.php` (или как он у тебя называется). Найди метод, который отвечает за запуск парсинга (или создай), и вставь туда вызов сервиса:

```php
use App\Services\YandexMapsParser;
use App\Models\Organization;

public function parse(Request $request)
{
    $url = $request->input('url');
    
    // Найти или создать организацию
    $org = Organization::firstOrCreate(
        ['url' => $url],
        ['status' => 'pending']
    );

    // Запустить парсер
    try {
        app(YandexMapsParser::class)->parseOrganization($url, $org->id);
        return response()->json(['status' => 'success']);
    } catch (\Exception $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
}
```

### Шаг 5. Добавь artisan-команду в Kernel (если нужно по cron)

В `app/Console/Kernel.php`:
```php
protected function schedule(Schedule $schedule)
{
    // Опционально: парсить все pending-организации каждый час
    $schedule->command('parse:org --pending')->hourly();
}
```

### Шаг 6. Установи зависимости (если не стоят)

```bash
composer require guzzlehttp/guzzle
composer require symfony/dom-crawler
composer require symfony/css-selector
```

(В Laravel обычно уже стоят, но проверь)

---

## Что НЕ нужно переделывать

- ❌ Миграции БД — уже есть
- ❌ Модели — уже есть
- ❌ Vue-компоненты — уже есть
- ❌ Роуты API — уже есть
- ❌ Контроллеры — уже есть (просто добавь вызов парсера)

---

## Хочешь, я сейчас прямо сгенерирую?

Скажи **«генерируй файлы»** — и я выдам тебе готовый код каждого файла по очереди:
1. `CaptchaDetectedException.php`
2. `SourceChangedException.php`  
3. `OrganizationData.php`
4. `ReviewData.php`
5. `YandexMapsClient.php`
6. `YandexMapsParser.php`
7. `ParseOrganizationCommand.php`

Ты их просто скопируешь в свой проект по папкам — и парсер заработает. Останется только привязать к контроллеру (1 строка кода).

**Что выбираешь:**
- 🔵 «Генерируй файлы» → я выдаю код по порядку
- 🟢 «Покажи мой текущий проект» → пришли структуру папок, я скажу, что именно добавлять
- 🟡 «Объясни что такое SSR парсинг» → если хочешь понять концепцию глубже