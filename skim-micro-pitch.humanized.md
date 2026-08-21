# SKIM: PHP 8.5 фреймворк вместо Laravel, Slim и CodeIgniter

SKIM это PHP 8.5 фреймворк, написанный с нуля. Он нативно заменяет Node.js встроенным realtime-стеком, работает как мост для ИИ-агентов через собственный MCP-сервер и держит sub-ms латентность под FrankenPHP. Никакого легаси из эпохи PHP 5.x.

Laravel, CodeIgniter, Slim и прочие несут архитектурный груз двадцатилетней давности: слои обратной совместимости, дефекты из-за старых зависимостей, "старые/новые" способы сделать одно и то же. SKIM идёт другим путём. `strict_types=1`, property hooks, enum'ы и единообразный `snake_case` заложены в сам базис фреймворка. В продакшене всего одна зависимость (`nikic/fast-route`), никакого тяжелого мусора из симфони-экосистемы, предсказуемая навигация для человека и AI-агента.

## Конкуренция с Node.js: нативный realtime

PHP-FPM живёт в модели "запрос пришёл, процесс умер", и любой live-UI превращается в костыли. SKIM решает это в ядре: из коробки идут SSE, Datastar, HTMX-фрагменты, собственный WebSocket-сервер и FrankenPHP worker mode. Дашборды с живыми графиками, чаты и коллаборативные редакторы становятся нормальным PHP-юзкейсом, без отдельного Node-сервиса.

## AI-first: контракты вместо галлюцинаций

SKIM спроектирован так, чтобы ИИ работал с проектом по реальным контрактам. В основе лежат детальные `#AI*`-аннотации: они дают нейросети исчерпывающий контекст и при этом не мешают коду.

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

AST-сканер парсит эти блоки, а команда `php skim mcp:serve` поднимает MCP-сервер, который передаёт Claude Code, Cursor или Claude Desktop вашу кодовую базу: методы, схемы, lifecycle, инварианты и побочные эффекты. ИИ не пишет код заново и не тратит тысячи токенов на grep-экспедиции по репо. Он получает структурированные данные и выдает готовый проект за одну сессию.

## Производительность

Бенчмарки прогнаны против Slim и Fat-Free Framework, двух самых быстрых PHP-микрофреймворков [ссылка]. SKIM не уступает им на холодных метриках и обгоняет на разогретых, при том что несёт в себе ORM, cache, queue, validation, events, CLI, realtime и dev-тулзы. У конкурентов всё это ставится отдельными пакетами. С включённым FrankenPHP worker mode, где приложение загружается один раз, SKIM выдает ~1600+ RPS с латентностью ~0.55 мс на один воркер и обгоняет PHP-FPM пулы по скорости отклика.

## Инструменты разработчика и CLI

Встроенные dev-тулзы не требуют установки десятков пакетов: умные error-pages с автоподсказками методов (Levenshtein), IDE-ссылками, автоматическим затиранием секретов и N+1 детектором в БД.

CLI `php skim` включает 10+ команд: интерактивный установщик, миграции, управление очередями, параллельный запуск Vite HMR, генерация AI-документации и подготовка конфигурации для worker mode.

---
Для тех, кто устал воевать с фреймворком ради современного PHP и хочет нативный realtime без Node.js. Production-ready стек, который вывезет нагрузку даже на маленьком VPS. PHP 8.5+, 1 production-зависимость, Docker из коробки.
