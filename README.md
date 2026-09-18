Yandex Maps Reviews Parser

Приложение для сбора и анализа отзывов организаций с Яндекс Карт: Laravel (API + парсер) + Vue 3 (SPA). Тестовое задание: подключить карточку организации, вытянуть все доступные отзывы (~600), рейтинг и счётчики.

1. Что умеет

- Авторизация (Sanctum, SPA-токены); сид-пользователь создаётся сидером (admin@local.test / password).
- Экран настроек: вставка ссылки на организацию, валидация и нормализация URL (canonical, ссылки с карты poi[uri], схема ymapsbm1://), запуск парсинга в очередь.
- Парсинг карточки: название, адрес, средний рейтинг, число оценок и число отзывов отдельно, рубрики.
- Парсинг всех отзывов (~600 на тестовой организации): автор, уровень автора, оценка, дата, текст, ответ бизнеса, лайки/дизлайки, аватар (хотлинк).
- Лента отзывов: пагинация по 50 без перезагрузки, сортировка (дата / сначала новые / оценка), фильтры (оценка 1-5, только новые, только изменённые, удалённые), поиск по тексту, распределение оценок.
- История парсингов (parse_runs): найдено / новых / изменено / исчезло, детали было-стало, бейджи на карточках.
- Идемпотентность: повторный парсинг не создаёт дубли (совпадение по organization_id + external_id); исчезнувшие из источника помечаются is_deleted, физически не удаляются.
- Устойчивость к проблемам источника: капча и смена разметки детектируются явно (статусы captcha / failed, last_error, лог).
- Очередь: парсинг в job с ретраями (tries = 3, фиксированный backoff = 10 c).
- Тесты: php artisan test - 17 тестов, 44 assertions (см. раздел Тесты).

2. Стек (установленные версии)

- Backend: PHP 8.4, Laravel 13, Guzzle 8, Symfony DomCrawler 8
- Frontend: Vue 3 (script setup, Composition API), Vite 8, Tailwind CSS 4, нативный fetch
- Auth: Laravel Sanctum 4 (SPA-токены, HasApiTokens)
- БД: MySQL 8 (рабочая); SQLite подходит для тестов (DB_CONNECTION=sqlite)
- Очередь: Laravel Queue, driver database
- Деплой: php artisan serve / shared-хостинг; docker-compose.yml в репозитории (не тестировался - см. ограничения)

3. Архитектура парсинга

URL из формы
  -> UrlNormalizer: любой формат к canonical https://yandex.ru/maps/org/{id}/reviews/
     (canonical со/без slug, ссылки с карты ?mode=poi&poi[uri]=ymapsbm1://org?oid={id}, голая схема ymapsbm1://)
     мусор -> InvalidArgumentException (400/ошибка формы, команда, job)
  -> SettingsController / parse:org: резолвинг записи по external_id
     (разные URL одной карточки = одна строка в БД)
  -> ParseYandexReviewsJob: фон, tries=3, backoff=10 c
  -> YandexMapsClient (Guzzle): GET страниц, пустой cookie-jar,
     случайный User-Agent на каждый запрос, пауза 1.5 c между страницами (фиксированная, в парсере)
     - state-view JSON (script type="application/json" class="state-view"):
       сводка stack[0].results|response -> items[0]:
       title, fullAddress, ratingData(ratingValue, ratingCount, reviewCount), categories
     - SSR-разметка отзывов (Schema.org):
       [itemType="http://schema.org/Review"]
         [itemProp="name"] -> автор
         .business-review-view__author-caption -> уровень автора
         [itemProp="ratingValue"] (content) / звёзды _full -> оценка
         [itemProp="datePublished"] (content) / .business-review-view__date -> дата
         [itemProp="reviewBody"] -> текст
         [itemProp="author"] [itemProp="image"] (content) -> avatar_url (хотлинк)
       пагинация источника: ?page=N (~50 отзывов на страницу, стоп по дублям/пустой, кап 40 страниц)
  -> YandexMapsParser::applyDiff: new / changed / deleted за прогон:
     новый -> insert + created_in_run_id;
     текст/оценка/автор изменились -> строки review_changes + updated_in_run_id;
     нет в выборке -> is_deleted + deleted_in_run_id, но только при покрытии
     выборки от 90% reviewCount (иначе старые отзывы за SSR-окном ~600
     ложно станут удалёнными);
     org-метрики + review_snapshots (сводка прогона, status 'ok')
  -> БД: organizations, reviews, review_changes, parse_runs, review_snapshots

Ключевые классы:

- App\Services\Yandex\UrlNormalizer - нормализация ссылок, извлечение external_id
- App\Clients\YandexMapsClient - HTTP-слой: запросы, заголовки, ротация UA, детект капчи/поломки разметки
- App\Services\YandexMapsParser - извлечение (DomCrawler), пагинация, diff, run lifecycle
- App\DTOs\OrganizationData, ReviewData - типизированный перенос данных между слоями
- App\Exceptions\CaptchaDetectedException - HTTP 403/429 или showcaptcha в HTML
- App\Exceptions\SourceChangedException - нет state-view / нет Review-блоков / нет данных организации
- App\Jobs\ParseYandexReviewsJob - фон, tries = 3, backoff = 10
- App\Console\Commands\ParseOrganizationCommand - php artisan parse:org {url} [--org-id=ID]
- App\Services\YandexParserService - legacy-адаптер поверх YandexMapsParser (возвращает массив)

4. Обоснование выбора подхода к парсингу

Рассматривались два варианта:

Вариант 1. Прямой JSON-API карточки (/maps/api/business/fetchReviews).
Удобная структура с пагинацией, но защищён: ротация csrfToken (challenge-response), клиентская подпись параметра s из минифицированного JS-чанка; без валидной подписи - 400 Bad Request.

Вариант 2. SSR-рендеринг canonical-страницы /maps/org/{slug}/{id}/reviews/?page=N - выбран.
Причины:
- отзывы в готовом виде в HTML с разметкой Schema.org - стандарт, который Яндекс не убирает по SEO-соображениям;
- сводка дублируется в state-view JSON (ratingData: ratingValue, ratingCount, reviewCount);
- не требует авторизации, реверса JS и headless-браузера; проще деплой.

Детект поломок источника:
- нет script class="state-view", нет [itemType="...Review"], нет данных организации -> SourceChangedException;
- HTTP 403/429 или showcaptcha в HTML -> CaptchaDetectedException (одиночное слово captcha в JS-конфиге игнорим - даёт ложные срабатывания);
- во всех случаях: статус организации failed/captcha, текст в last_error, запись в лог, run помечается failed. Пустой массив молча не возвращается.

5. Анти-бан

Реализовано:
- фиксированная пауза 1.5 c между страницами (usleep в парсере);
- ротация User-Agent: YandexMapsClient::getRandomUserAgent() - пул из 6 десктопных сигнатур (Chrome/Edge/Firefox/Safari под Windows, macOS, Linux), случайный UA в заголовках каждого запроса;
- пустой cookie-jar на клиент;
- при 403/429 - исключение -> ретраи очереди (tries = 3, backoff = 10 c), затем статус failed/captcha.

Для продакшена (тысячи карточек): ротация прокси, распределённая очередь, парсинг в офф-пик - см. Запланировано.

6. База данных

- organizations: id, external_id (nullable, unique), name, address (nullable), rating decimal(2,1) (nullable), rating_count, review_count, rubrics (json, nullable), yandex_url, average_rating float (nullable, дубль rating для фронта), total_ratings, total_reviews, raw_data (json, nullable), status (default pending), parsed_at (nullable), last_error (nullable), timestamps, softDeletes
- reviews: id, organization_id (FK, cascade), external_id (nullable, unique в паре с organization_id), author (nullable), author_level (nullable), author_name, text, rating, review_date, source_id (nullable, дубль external_id), business_reply (nullable), business_reply_date (nullable), likes, dislikes, avatar_url (nullable), is_deleted (default false), created_in_run_id / updated_in_run_id / deleted_in_run_id (nullable FK -> parse_runs); индексы (organization_id, rating), (organization_id, is_deleted)
- parse_runs: id, organization_id (FK, cascade), status (default parsing), parser_version (default v2.0-ssr), found_total, new_count, changed_count, deleted_count, started_at/finished_at (nullable), error (nullable)
- review_changes: id, review_id (FK, cascade), parse_run_id (FK, cascade), field (text/rating/author), old_value/new_value (nullable)
- review_snapshots: id, organization_id (FK), total_reviews, average_rating, metrics_snapshot (json), parser_version, status (на практике пишется 'ok')
- users, personal_access_tokens: Sanctum-авторизация, сид admin@local.test / password
- jobs, cache и др.: стандартные таблицы Laravel (очередь database)

Идемпотентность: совпадение по organization_id + external_id; у SSR-разметки нет стабильного ID отзыва, поэтому external_id = sha1(author + text + rating). Следствие: правка отзыва источником (смена текста/оценки) меняет хеш - такой отзыв проявится как пара исчез + новый, а не как review_changes. Полевые правки было-стало фиксируются только для отзывов, опознанных по неизменному хешу.

7. API

Все /api/*, кроме POST /api/login, - под auth:sanctum.

- POST /api/login - выдача Sanctum-токена (email, password)
- POST /api/register - алиас login (регистрации нет по ТЗ)
- GET /api/user - текущий пользователь
- POST /api/logout - отзыв текущего токена
- POST /api/settings - {yandex_url} -> валидация + нормализация URL, сохранение (дедуп по external_id), dispatch job
- GET /api/settings - последняя организация + её отзывы (50, по дате)
- GET /api/reviews - отзывы: organization_id, page, rating, q, sort=date|new_first|old|rating, only_new, only_changed, show_deleted, show_deleted_only; пагинация по 50; meta.last_run_id для бейджей
- GET /api/organizations - список организаций со статусами
- GET /api/parse-runs - история прогонов (?organization_id=)
- GET /api/parse-runs/{id} - детали рана: changes (было-стало) + deleted

Фронтенд (resources/js: app.js, components/{Login,Settings,Reviews,History}.vue, вьюха app.blade.php): нативный fetch, токен в localStorage, в vite.config.js алиас vue -> vue.esm-bundler (нужен runtime-компилятор для template корневого компонента).

8. Запуск локально

Требования: PHP 8.4, Composer, Node 20+, MySQL 8 (для тестов хватает встроенного SQLite).

```
composer install
cp .env.example .env
php artisan key:generate
# в .env: по умолчанию DB_CONNECTION=sqlite работает сразу;
# для MySQL раскомментируйте DB_HOST/DB_PORT/DB_DATABASE/DB_USERNAME/DB_PASSWORD
# и создайте базу (например yandex_cards)
php artisan migrate --seed      # сидер создаст admin@local.test / password
npm install && npm run build
php artisan serve               # http://localhost:8000
php artisan queue:work          # в отдельном терминале (иначе job'ы парсинга лежат в очереди)
```

Разовый парсинг без очереди:

```
php artisan parse:org "https://yandex.ru/maps/org/vkusno_i_tochka/32369324367/reviews/" [--org-id=ID]
```

Docker (альтернатива, не тестировалась - docker в окружении отсутствует):

```
docker-compose up -d
docker-compose exec app php artisan migrate --seed
```

Фактический состав docker-compose.yml: app (сборка из Dockerfile, php artisan serve на :8000), db (mysql:8.0, проброшен на :3307), redis. Отдельных сервисов nginx и worker в файле нет; Dockerfile ставит PHP 8.4 + Composer-зависимости, собирает фронт и генерирует ключ.

9. Деплой на shared-хостинг (проверено на Timeweb)

Shared-хостинг с фиксированным docroot (public_html) требует адаптаций, которых нет на VPS/Docker. Ниже — проверенная схема.

Структура на хостинге:
- Весь проект в public_html (корень сайта не меняется)
- index.php в корне public_html с путями без `/../`:
  ```php
  require __DIR__.'/vendor/autoload.php';
  $app = require_once __DIR__.'/bootstrap/app.php';
  ```
  (стандартный Laravel-шаблон использует `/../vendor/...`, что не работает при docroot=public_html)
- Ассеты Vite в `public/build` (не в корне), в .env добавить `ASSET_URL=https://domain.ru/public` — иначе ссылки на CSS/JS вернут 404

Установка:

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force --seed    # сидер создаст admin@local.test / password
```

Frontend: `npm run build` выполнять локально, на сервер заливать готовый `public/build` (включая `manifest.json`).

Очередь через cron (без демона):

Shared-хостинг не поддерживает долгоживущие процессы (`queue:work` без флагов будет убит). Решение — cron поднимает воркер раз в минуту, тот выгребает очередь и завершается.

1. Создать в home-папке файл `~/runner_worker.sh`:
   ```bash
   #!/bin/bash
   cd /home/c/cz549031/public_html || exit 1
   /opt/php84/bin/php artisan queue:work --stop-when-empty --tries=3 --timeout=300 >> /home/c/cz549031/public_html/cron_worker.log 2>&1
   ```
2. `chmod +x ~/runner_worker.sh`
3. В панели хостинга создать cron-задачу:
   - Интерпретатор: Сценарий SH (не PHP)
   - Путь до файла: выбрать `~/runner_worker.sh` через кнопку «Настроить» (не вводить руками — иначе путь сломается)
   - Расписание: каждую минуту

Важно: на Timeweb консольный php по умолчанию = 8.2, а Laravel 13 требует 8.4+. Бинарник нужной версии: `/opt/php84/bin/php`. Проверяй `php -v` в SSH перед командами artisan; если показывает 8.2 — используй полный путь `/opt/php84/bin/php artisan ...` явно (или добавь в PATH: `export PATH=/opt/php84/bin:$PATH`).

HTTPS: выпустить Let's Encrypt в панели хостинга.

Локальная разработка: для мгновенного выполнения джоб достаточно `php artisan queue:work` в отдельном терминале, либо поставь в локальном `.env` `QUEUE_CONNECTION=sync` — тогда джоба выполняется синхронно при сохранении ссылки, воркер не нужен.

Отличия от VPS/Docker: на полноценном сервере используется стандартный Laravel-layout (проект в подпапке, корень сайта → public, планировщик через `schedule:run`). Адаптации выше специфичны для shared с фиксированным docroot.

10. Тесты

```
php artisan test   # sqlite :memory:, сейчас: 17 тестов, 44 assertions - все зелёные
```

- tests/Unit/UrlNormalizerTest.php (7): canonical со/без slug, map-ссылка с poi[uri], голая схема ymapsbm1://, битая ссылка и поиск без ID (исключения), один ID из всех форматов
- tests/Unit/YandexMapsParserTest.php (1): HTML-фикстура отзыва: Schema.org-селекторы, parseRussianDate
- tests/Feature/ReviewFiltersTest.php (7): скрытие удалённых по умолчанию, only_new, only_changed, show_deleted_only, сортировка new_first, история прогонов, доступность фильтров изменений только со второго прогона
- tests/{Unit,Feature}/ExampleTest.php: дефолтные заглушки Laravel

11. Структура проекта

```
app/
  Clients/YandexMapsClient.php
  Console/Commands/ParseOrganizationCommand.php
  DTOs/{OrganizationData,ReviewData}.php
  Exceptions/{CaptchaDetectedException,SourceChangedException}.php
  Http/Controllers/Api/{AuthController,SettingsController,ReviewsController,ParseRunsController}.php
  Http/Requests/StoreOrganizationRequest.php
  Jobs/ParseYandexReviewsJob.php
  Models/{Organization,Review,ReviewChange,ReviewSnapshot,ParseRun,User}.php
  Services/{YandexMapsParser.php, YandexParserService.php (legacy-адаптер), Yandex/UrlNormalizer.php}
resources/js/{app.js, components/{Login,Settings,Reviews,History}.vue}
resources/views/{app,welcome}.blade.php
routes/{api,web,console}.php
database/migrations/{0001_01_01_*, 2026_09_15_00000{1,2,3}_*, 2026_09_15_205800_*, 2026_09_16_00000{1,2,3}_*}
tests/{Unit/{YandexMapsParserTest,UrlNormalizerTest}, Feature/ReviewFiltersTest}.php
docker-compose.yml, Dockerfile
```

12. Известные ограничения и запланировано

1. Прямой JSON-API: реверс подписи s из JS-чанка отзывов -> переход с HTML на JSON (быстрее и меньше трафика). Запланировано.
2. Фильтры по аспектам (Еда, Персонал): в SSR доступны только агрегированные счётчики, по каждому отзыву аспектов нет - требуется API/headless. Запланировано (не входит в текущий объём).
3. Прогресс в %: сейчас статусы pending/parsing/success/failed/captcha + счётчики ранов; точный процент - запланировано.
4. Аватары: сейчас хотлинк на avatars.mds.yandex.net; для прода - скачивание в storage через очередь. Запланировано.
5. Прокси-ротация и распределённая очередь для тысяч карточек. Запланировано.
6. Docker-compose: состав описан выше, в текущем окружении не тестировался (docker отсутствует). Требует проверки.
7. Пометка исчез ставится только при покрытии выборки от 90% reviewCount - иначе ложные срабатывания на старых отзывах за SSR-окном. Осознанное ограничение, см. код applyDiff().
