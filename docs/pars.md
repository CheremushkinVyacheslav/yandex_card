# 🎯 Промпт для ИИ-ассистента (копируй целиком)

---

## КОНТЕКСТ

Я выполняю тестовое задание: Laravel-приложение с Vue-фронтендом для парсинга отзывов организаций с Яндекс Карт. Уже готова БД со следующими таблицами:

### Таблица `organizations`
```sql
CREATE TABLE organizations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    external_id VARCHAR(50) NOT NULL,
    name VARCHAR(255) NOT NULL,
    address VARCHAR(500),
    rating DECIMAL(2,1),
    rating_count INT DEFAULT 0,
    review_count INT DEFAULT 0,
    rubrics JSON,
    url VARCHAR(500),
    status ENUM('pending','parsing','success','failed','captcha') DEFAULT 'pending',
    parsed_at TIMESTAMP NULL,
    last_error TEXT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE KEY (external_id)
);
```

### Таблица `reviews`
```sql
CREATE TABLE reviews (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NOT NULL,
    external_id VARCHAR(100) NOT NULL,
    author VARCHAR(255),
    author_level VARCHAR(100),
    rating TINYINT,
    text TEXT,
    review_date TIMESTAMP NULL,
    business_reply TEXT,
    business_reply_date TIMESTAMP NULL,
    likes INT DEFAULT 0,
    dislikes INT DEFAULT 0,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE KEY (organization_id, external_id),
    FOREIGN KEY (organization_id) REFERENCES organizations(id)
);
```

---

## ПОДХОД К ПАРСИНГУ (ИССЛЕДОВАНИЕ ЗАВЕРШЕНО)

После исследования API Яндекс Карт было установлено, что **JSON-API `/maps/api/business/fetchReviews` требует сложной подписи запроса (параметр `s`, rotация `csrfToken`, привязка к `sessionId`)**, поэтому выбран **более устойчивый путь — парсинг SSR-рендеренного HTML** страниц `/org/{slug}/{id}/reviews/?page=N`.

### Преимущества выбранного подхода:
- Нет необходимости в авторизации (куки не нужны)
- Не нужна ротация токенов и подписей
- Отзывы приходят в готовом виде с разметкой **Schema.org** (устойчивость к редизайну)
- Один обычный GET-запрос = одна страница с ~25 отзывами
- Для 686 отзывов требуется ~28 запросов

### URL-схема:
```
Базовый URL (canonical):
https://yandex.ru/maps/org/vkusno_i_tochka/32369324367/reviews/

Пагинация:
https://yandex.ru/maps/org/vkusno_i_tochka/32369324367/reviews/?page=2
https://yandex.ru/maps/org/vkusno_i_tochka/32369324367/reviews/?page=3
... до последней страницы
```

На каждой странице ~25 отзывов. Когда `?page=N` возвращает 0 отзывов или меньше 25 — достигнут конец.

---

## СТРУКТУРА HTML-СТРАНИЦЫ

### 1. Блок state-view (содержит все метаданные организации в JSON)

В `<head>` страницы находится скрипт:
```html
<script type="application/json" class="state-view">
{
  "config": {
    "csrfToken": "...",
    "sessionId": "..."
  },
  "stack": [
    {
      "response": {
        "items": [
          {
            "id": "32369324367",
            "title": "Вкусно — и точка",
            "fullAddress": "Вологодская область, Череповец, проспект Победы, 100А",
            "ratingData": {
              "ratingCount": 1868,
              "ratingValue": 4.199999809265137,
              "reviewCount": 686
            },
            "categories": [
              {"id": "184106386", "name": "Быстрое питание"}
            ],
            "phones": [
              {"number": "+7 (8202) 20-21-39"}
            ],
            "workingTimeText": "ежедневно, 07:00–23:00"
          }
        ]
      }
    }
  ]
}
</script>
```

### 2. Разметка отзывов (Schema.org, в body страницы)

Каждый отзыв — это блок:
```html
<div class="business-review-view" itemProp="review" itemType="http://schema.org/Review" itemScope="">
  <div class="business-review-view__info">
    <div class="business-review-view__author-container">
      <div class="business-review-view__author-image">...</div>
      <div class="business-review-view__author-info">
        <div class="business-review-view__author-name" itemProp="author" itemType="http://schema.org/Person" itemScope="">
          <span itemProp="name" dir="auto">Евгения</span>
        </div>
        <div class="business-review-view__author-caption">Знаток города 12 уровня</div>
      </div>
    </div>
    <div class="business-review-view__header">
      <div class="business-review-view__rating">
        <div class="business-rating-badge-view">
          <div class="business-rating-badge-view__stars">
            <!-- Звёзды: _full = заполненная, _empty = пустая -->
            <span class="inline-image icon business-rating-badge-view__star _full"></span>
            <span class="inline-image icon business-rating-badge-view__star _full"></span>
            <span class="inline-image icon business-rating-badge-view__star _full"></span>
            <span class="inline-image icon business-rating-badge-view__star _full"></span>
            <span class="inline-image icon business-rating-badge-view__star _full"></span>
          </div>
        </div>
      </div>
      <div class="business-review-view__date">27 февраля</div>
    </div>
    <div class="business-review-view__body" itemProp="reviewBody">
      Посещаем этот ресторан довольно часто...
    </div>
    
    <!-- Ответ бизнеса (опционально) -->
    <div class="business-review-view__business-comment">
      <div class="business-review-comment-content">
        <div class="business-review-comment-content__bubble">
          Евгения, здравствуйте, очень рады...
        </div>
        <div class="business-review-comment-content__date">4 марта</div>
      </div>
    </div>
    
    <!-- Реакции -->
    <div class="business-reactions-view">
      <div class="business-reactions-view__container">
        <span class="business-reactions-view__counter">1</span> <!-- likes -->
      </div>
      <div class="business-reactions-view__container">
        <span class="business-reactions-view__counter">1</span> <!-- dislikes -->
      </div>
    </div>
  </div>
</div>
```

---

## МАППИНГ ПОЛЕЙ

### Организация (из state-view JSON):
| JSON-путь | Поле БД |
|---|---|
| `stack[0].response.items[0].id` | `external_id` |
| `stack[0].response.items[0].title` | `name` |
| `stack[0].response.items[0].fullAddress` | `address` |
| `stack[0].response.items[0].ratingData.ratingValue` | `rating` (DECIMAL) |
| `stack[0].response.items[0].ratingData.ratingCount` | `rating_count` |
| `stack[0].response.items[0].ratingData.reviewCount` | `review_count` |
| `stack[0].response.items[0].categories[].name` | `rubrics` (JSON массив) |
| Исходный URL | `url` |

### Отзыв (из HTML-разметки через DomCrawler):
| CSS/XPath-селектор | Поле БД |
|---|---|
| Контейнер `[itemType="http://schema.org/Review"]` | один отзыв |
| `[itemProp="name"]` внутри автора | `author` |
| `.business-review-view__author-caption` | `author_level` |
| Количество `.business-rating-badge-view__star._full` | `rating` (1-5) |
| `[itemProp="reviewBody"]` или `.business-review-view__body` | `text` |
| `.business-review-view__date` | `review_date` (нужно парсить строку "27 февраля" / "3 дня назад") |
| `.business-review-view__business-comment .business-review-comment-content__bubble` | `business_reply` |
| `.business-review-view__business-comment .business-review-comment-content__date` | `business_reply_date` |
| Первый `.business-reactions-view__counter` | `likes` |
| Второй `.business-reactions-view__counter` | `dislikes` |
| Хешированный ID контейнера или ссылка на автора | `external_id` |

---

## ТРЕБОВАНИЯ К АРХИТЕКТУРЕ КОДА

### 1. Структура файлов Laravel:
```
app/
├── Services/
│   └── YandexMapsParser.php           # Главный сервис парсинга
├── Clients/
│   └── YandexMapsClient.php           # HTTP-клиент (Guzzle)
├── DTOs/
│   ├── OrganizationData.php
│   └── ReviewData.php
├── Exceptions/
│   ├── CaptchaDetectedException.php   # Вернулась капча/403
│   ├── SourceChangedException.php     # Изменилась разметка (нет ожидаемых селекторов)
│   └── ParserException.php            # Базовый класс
├── Console/
│   └── Commands/
│       └── ParseOrganizationCommand.php  # php artisan parse:org {url}
└── Jobs/
    └── ParseOrganizationJob.php       # Для queue (опционально)
```

### 2. `YandexMapsClient` — HTTP-клиент:
- Использует Guzzle с таймаутом 15 сек
- Устанавливает заголовки: `User-Agent` (актуальный Edge/Chrome), `Accept: text/html`
- CookieJar — пустой (анонимная сессия)
- Пауза между запросами: 1.5-2 секунды (sleep)
- Методы:
  - `fetchPage(string $url): string` — возвращает HTML
  - Автоматически детектит капчу (HTML содержит "captcha", "showcaptcha", 403)
  - Детектит поломку разметки (нет `state-view` или отзывов)

### 3. `YandexMapsParser` — главный сервис:
```php
public function parseOrganization(string $url, int $organizationId): void
{
    // 1. Загружаем первую страницу
    // 2. Парсим state-view → обновляем organization (rating, counts, rubrics)
    // 3. Парсим отзывы с текущей страницы
    // 4. Upsert в reviews по (organization_id, external_id)
    // 5. Если отзывов 25 — идём на page+1
    // 6. Если < 25 — последняя страница, стоп
    // 7. UPDATE organizations SET status='success', parsed_at=NOW()
    // 8. При ошибке: UPDATE organizations SET status='failed', last_error=...
}
```

### 4. Логика upsert:
```php
Review::updateOrCreate(
    ['organization_id' => $orgId, 'external_id' => $extId],
    $reviewData
);
```

### 5. Генерация `external_id` для отзыва:
Поскольку в HTML нет явного ID отзыва, использовать:
```php
hash('sha1', $authorName . $reviewText . $rating)
```
или XPath-индекс контейнера на странице.

### 6. Парсинг даты отзыва:
Строки вида "27 февраля", "3 дня назад", "вчера", "2 часа назад" нужно конвертировать в timestamp. Создать приватный метод `parseRussianDate(string $text): ?DateTime`.

---

## ОБРАБОТКА ОШИБОК

### `CaptchaDetectedException` — кидается когда:
- HTTP-статус 403/429
- В HTML есть "captcha", "showcaptcha"
- Возвращается HTML-форма вместо ожидаемой разметки

### `SourceChangedException` — кидается когда:
- Нет `<script class="state-view">`
- Нет ни одного `[itemType="http://schema.org/Review"]` на странице (при ненулевом `reviewCount` в state)
- Исчезли ключевые селекторы (`[itemProp="name"]`, `.business-review-view__body`)

### Обёртка в сервисе:
```php
try {
    $this->client->fetchPage($url);
    // ...
} catch (CaptchaDetectedException $e) {
    $organization->update(['status' => 'captcha', 'last_error' => $e->getMessage()]);
    throw $e;
} catch (SourceChangedException $e) {
    $organization->update(['status' => 'failed', 'last_error' => 'Разметка изменилась: ' . $e->getMessage()]);
    throw $e;
}
```

---

## Artisan-команда `parse:org`

```
php artisan parse:org "https://yandex.ru/maps/org/vkusno_i_tochka/32369324367/reviews/"
```

Аргументы:
- `url` (обязательный) — URL страницы отзывов организации

Опции:
- `--org-id=1` — ID существующей записи в organizations (если не указан — ищет по URL или создаёт новую)

Логика:
1. Найти/создать запись в organizations со статусом `pending`
2. Обновить статус в `parsing`
3. Вызвать YandexMapsParser
4. По результату обновить статус в `success` или `failed`
5. Вывести статистику: "Спарсено N отзывов, обновлена организация"

---

## ТЕХНИЧЕСКИЕ ТРЕБОВАНИЯ

1. **PHP 8.2+**, Laravel 11, строгая типизация везде
2. **Guzzle 7** для HTTP-запросов
3. **Symfony DomCrawler** для парсинга HTML (уже есть в Laravel через `symfony/css-selector`)
4. Все DTO — `readonly class` с именованными конструкторами или `fromArray()`
5. Unit-тесты: хотя бы один тест на маппинг отзыва с фикстурами HTML
6. Логирование через `Log::info()` / `Log::error()` с контекстом (URL, номер страницы)
7. Не использовать headless-браузер (symfony/panther) — только Guzzle + DomCrawler

---

## ЧТО СГЕНЕРИРОВАТЬ

Сгенерируй полный код следующих файлов:
1. `app/Exceptions/CaptchaDetectedException.php`
2. `app/Exceptions/SourceChangedException.php`
3. `app/DTOs/OrganizationData.php`
4. `app/DTOs/ReviewData.php`
5. `app/Clients/YandexMapsClient.php`
6. `app/Services/YandexMapsParser.php`
7. `app/Console/Commands/ParseOrganizationCommand.php`
8. `tests/Unit/YandexMapsParserTest.php` (минимальный тест на парсинг HTML-фрагмента отзыва)

Для каждого файла — полный код с комментариями на русском. В конце — пример использования и краткое описание в стиле README (как это вписать в раздел "Обоснование подхода").

---

**Используй этот промпт целиком** — он содержит всю необходимую информацию для генерации работающего парсера без необходимости дополнительных исследований.