# SKIM — PHP 8.5 фреймворк, который оставляет Laravel, Slim и CodeIgniter позади

SKIM — это PHP 8.5 фреймворк с нуля, нативно заменяющий Node.js встроенным realtime-стеком, ставший мостом для ИИ-агентов через собственный MCP-сервер и работающий в sub-ms латентности под FrankenPHP — без грамма легаси, тянущегося из эпохи PHP 5.x.

Laravel, CodeIgniter, Slim и прочие несут архитектурный груз двадцатилетней давности: слои обратной совместимости, дефекты из-за старых зависимостей и «старые/новые» способы сделать одно и то же. SKIM идёт другим путём. `strict_types=1`, property hooks, enum'ы и единообразный `snake_case` — это базис, а не патч. В продакшене всего одна зависимость (`nikic/fast-route`), никакого тяжелого мусора из симфони-экосистемы и предсказуемая навигация, идеальная как для человека, так и для AI-агента.

## Конкуренция с Node.js — нативный realtime
PHP-FPM живёт в модели «запрос пришёл, процесс умер», превращая любой live-UI в костыли. SKIM решает это в ядре: из коробки идут SSE, Datastar, HTMX-фрагменты, собственный WebSocket-сервер и FrankenPHP worker mode. Дашборды с живыми графиками, чаты и коллаборативные редакторы — теперь легитимный и быстрый PHP-юзкейс, а не повод ставить отдельный Node-сервис.

## AI-first: контракты вместо галлюцинаций
SKIM спроектирован так, чтобы ИИ работал с проектом по реальным контрактам. В основе — детальные `#AI*`-аннотации, которые не мешают коду, но дают нейросети исчерпывающий контекст:

```php
#AI:class
#AI symbol: skim\db\migration
#AI description: Abstract base class for SQL-first database migrations with up/down contract.
#AI lifecycle: instantiated by migrator::load_all() via require, filename assigned from basename
#AI invariants: [up() and down() must return valid SQL strings; filename is set by migrator, never manually; down() must exactly reverse up()]
#AI core_behaviors: [SQL-first approach — no schema builder abstraction; multi-statement SQL supported via semicolon splitting]
#AI flow: migrator requires file -> migration instance -> up()/down() returns SQL -> migrator executes
#AI architectural_notes: SQL-first design avoids the impedance mismatch of fluent schema builders. What you write is exactly what runs on the database.

...
 * Example:
 *   return new class extends migration {
 *       public function up(): string {
 *           return "CREATE TABLE...
 *       public function down(): string { return "DROP TABLE ...
```

AST-сканер парсит эти блоки, а команда `php skim mcp:serve` поднимает MCP-сервер, который скармливает Claude Code, Cursor или Claude Desktop **вашу** кодовую базу: методы, схемы, lifecycle, инварианты и побочные эффекты. ИИ не пишет код заново и не тратит тысячи токенов на grep-экспедиции по репо — он получает структурированные данные и выдает готовый проект за одну сессию.

## Производительность — несправедливое преимущество
Бенчмарки прогнаны против Slim и Fat-Free Framework — двух самых быстрых PHP-микрофреймворков [ссылка]. Это звучит как ошибка тестов, но SKIM не уступает им на холодных метриках и обгоняет на разогретых, при том что несёт в себе ORM, cache, queue, validation, events, CLI, realtime и dev-тулзы — то, что у конкурентов ставится отдельными пакетами. А если включить FrankenPHP worker mode, где приложение загружается один раз, SKIM выдает ~1600+ RPS с латентностью ~0.55 мс на один воркер, уничтожая PHP-FPM пулы по скорости отклика.

## Инструменты разработчика и CLI
Встроенные dev-тулзы не требуют установки десятков пакетов: умные error-pages с автоподсказками методов (Levenshtein), IDE-ссылками, автоматическим затиранием секретов и N+1 детектором в БД.

Собственный CLI `php skim` включает 10+ команд: от интерактивного установщика, миграций, управления очередями и параллельного запуска Vite HMR до генерации AI-документации и подготовки конфигурации для worker mode.

---
Для тех, кто устал воевать с фреймворком ради современного PHP, хочет нативный realtime без Node.js и ищет полноценный production-ready стек, который сможет вывезти ваш успех даже на маленьком VPS. PHP 8.5+, 1 production-зависимость, Docker из коробки.