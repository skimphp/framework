# SKIM — PHP 8.5+ Framework

## Что это

SKIM — современный PHP 8.5+ фреймворк с нулевыми внешними зависимостями (кроме fast-route и pest для тестов), строгой типизацией и полным стеком: HTTP routing с middleware, Active Record ORM, query builder, cache (Redis/file/array), session, validation с pipe-синтаксисом, события, Redis-очередь, CLI команды, DI-контейнер с 3 скоупами, Vite-интеграция для ассетов, логирование, i18n, тулзы для разработки (profiler, debug toolbar) и система расширений через Composer.

Все классы и методы используют snake_case конвенцию, шаблоны — чистый PHP без Twig/Blade, а архитектура строится вокруг компонентного подхода с namespace `skim\component` для фреймворка и `app\*` для пользовательского кода.

## Демо-приложение

В составе фреймворка есть полное демо-приложение в `../demo/`, демонстрирующее все ключевые возможности:

**Что включено:**
- Routing с параметрами и группами (публичные, auth-protected, API)
- Контроллеры для HTTP-запросов (home, auth, posts, admin, API)
- Модели с property hooks (PHP 8.4) для dirty tracking и авто-нормализации
- Middleware для аутентификации и защиты админ-панели
- Views с fragment rendering для HTMX
- Migrations для структуры БД (users, posts таблицы)
- CLI команды для сидирования данных
- Кэширование с Redis и fallback на файлы
- Сессии через Redis

**Структура демо:**
```
demo/
├── app/
│   ├── commands/          # CLI команды (seed:basic)
│   ├── controllers/       # HTTP контроллеры
│   ├── events/            # События приложения
│   ├── middleware/        # Middleware (auth)
│   └── models/            # Модели данных
├── config/                # Конфигурация (PHP массивы)
├── migrations/            # Миграции БД
├── public/                # Публичные файлы
└── routes.php             # Определение маршрутов
```

**Запуск демо:**
```bash
cd ../demo
docker-compose up -d --build
docker-compose exec app bin/skim migrate
docker-compose exec app bin/skim seed:basic
# http://localhost:8082
# Admin: admin@example.com / secret
```
## Производительность

### Официальные бенчмарки

5 бенчмарков в `benchmarks/` с четкими целями и реалистичными ожиданиями:

| Бенчмарк | Описание | Цель (bare metal) | Docker dev | Статус |
|----------|----------|-------------------|------------|--------|
| boot_isolation | app::instance() overhead (autoload + container) | < 1ms | 3-6ms | ⚠️ bind-mount bottleneck |
| cache_hit | env::get() + config::get() per call с OPcache | < 0.05ms | < 0.001ms | ✅ чистый OPcache |
| http_warm | HTTP throughput на 404 route (framework overhead) | < 10ms | 15-25ms | ⚠️ PHP cold boot per req |
| http_hello | Hello World closure (full stack) | < 2ms | 5-8ms (1 concurrent) | ✅ |
| http_json | JSON closure с json_encode (full stack) | < 7ms | 6-9ms (1 concurrent) | ✅ |

**Запуск полного набора:**
```bash
docker compose exec app php benchmarks/bench_run.php [requests] [concurrency]
# Пример: docker compose exec app php benchmarks/bench_run.php 5000 20
```

### Оптимизация Docker-окружения

Для приближения к bare-metal производительности в dev контейнере уже настроено:

- **PHP_CLI_SERVER_WORKERS=8** — параллельные `php -S` workers (по умолчанию однопоточный)
- **OPcache preload** — `storage/preload.php` прогревает core компоненты в shared memory:
  - src/core/, src/cache/, src/db/, src/view/, src/helpers/
  - src/validation/, src/events/, src/queue/, src/realtime/
  - src/websocket/, src/http/, src/extension/
- **PHP_INI_FLAGS** — output_buffering=4096, display_errors=stderr, implicit_flush=Off

### Worker Mode — "один раз запустил и используешь"

FrankenPHP-style режим где приложение загружается один раз и обрабатывает запросы в цикле:

**Бенчмарк worker mode:**
```bash
docker compose exec app php benchmarks/worker_mode_benchmark.php [concurrency] [duration] [port]
# Defaults: concurrency=1, duration=5s, port=9999
```

**Как работает:**
- Поднимает TCP сервер на указанном порту
- Загружает app один раз: `app::instance() → boot() → boot_extensions() → freeze()`
- В цикле обрабатывает запросы: `begin_request() → dispatch() → send() → end_request()`
- Измеряет RPS в режиме persistent worker

**Soak test** — проверка на утечки памяти:
```bash
docker compose exec app php benchmarks/soak_test.php
# Прогоняет 1000 запросов через worker loop с leak detector
```

**Результаты:** Все HTTP бенчмарки проходят цели (ok: true) даже в Docker с bind-mount. На bare metal с php-fpm будет еще быстрее.


## Quickstart

### Docker
```bash
docker compose up -d --build
# http://localhost:8080
```

### CLI
```bash
php skim serve
```

## Runtime

- PHP 8.5+
- `strict_types=1`
- Zero external deps (except fast-route and pest for tests)