# План: рефакторинг сервисного слоя — домены + единый контракт handle

## Цель

Привести `app/Services` к правилам проекта: разбить на домены (Slots/Holds), один контракт `handle()` на сервис, вынести кэширование в отдельные сервисы, убрать `Cache::`-фасад из сервисов. Поведение API и тест-лист не меняются — меняется структура и вызовы.

## Нарушения, которые чиним

| Код (сейчас) | Нарушение | Станет |
|---|---|---|
| `AvailabilityReader` — `Cache::flexible`/`Cache::lock` | «Никаких `Cache::`» | `AvailabilityCache` + `AvailabilityReader` (чистый запрос) |
| `InvalidateCacheSlotListener` — `Cache::forget` | то же | `InvalidateAvailabilityCache` (инжектится в слушателя) |
| `HoldCreator::create()` возвращает `Hold` | CQS (решено: вариант B — фабричный контракт) | `CreateHold::handle(): Hold` |
| `HoldLifecycle` — confirm + cancel | один сервис = один контракт | `ConfirmHold::handle()`, `CancelHold::handle()` |
| `CapacityArbiter` — ensureCapacity + reserve | то же | `CheckCapacity::handle()`, `ReserveCapacity::handle()` |

## Целевая структура

```
app/Services/
├── Slots/
│   ├── AvailabilityReader.php           handle(): array               — чистый запрос остатков
│   ├── AvailabilityCache.php            handle(): array               — кэш flexible+lock, владелец ключа
│   ├── InvalidateAvailabilityCache.php  handle(): void                — Cache::forget
│   ├── CheckCapacity.php                handle(Slot): void            — «есть ли место» (путь создания)
│   └── ReserveCapacity.php              handle(Slot, Hold): void      — атомарный переход held→confirmed
└── Holds/
    ├── CreateHold.php                   handle(Slot, string $key): Hold — идемпотентное создание
    ├── ConfirmHold.php                  handle(Hold): void            — confirm + SlotChanged после коммита
    └── CancelHold.php                   handle(Hold): void            — cancel + SlotChanged после коммита
```

## Задачи

1. Новые сервисы (8 классов) по структуре выше; удалить старые (AvailabilityReader, CapacityArbiter, HoldCreator, HoldLifecycle).
2. Контроллеры: SlotController → AvailabilityCache; SlotHoldController → CreateHold; HoldConfirmController → ConfirmHold; HoldController → CancelHold.
3. Слушатель: инжектит InvalidateAvailabilityCache.
4. Тест-файлы переезжают в `tests/Unit/Services/{Slots,Holds}/`; вызовы — `->handle()`; имена тестов из тест-листа сохраняются.
5. Ключ кэша — константа в `AvailabilityCache::CACHE_KEY`; `InvalidateAvailabilityCache` ссылается на неё; feature-тест инвалидации использует константу вместо строки.

## Решения (согласованы)

1. **CQS для создания** — вариант B: `CreateHold::handle(): Hold`, контроллер решает 201/200 по `wasRecentlyCreated`.
2. **Домен арбитража** — Slots (вместимость — свойство слота).
3. **findByKey** — оставляем `?Hold` (null — «не найдено», законный случай replay-проверки).
4. **holds:stress** — `Http::`-фасад остаётся (команда — точка входа).
5. **Тест-лист** — поведение и имена тестов не меняются.

## Схема

```
┌─ ПРЕДСТАВЛЕНИЕ ──────────────────────────────────────────────────────────┐
│  SlotController@index        → AvailabilityCache (не Reader напрямую)   │
│  SlotHoldController          → CreateHold                                │
│  HoldConfirmController       → ConfirmHold                               │
│  HoldController@destroy      → CancelHold                                │
└──────────────┬───────────────────────────────────────────────────────────┘
               │
               ▼
┌─ РАБОЧИЙ ПРОЦЕСС (app/Services) ─────────────────────────────────────────┐
│  Slots/                                                                  │
│  [AvailabilityCache] handle() ── Cache::flexible+lock ──► [AvailabilityReader] handle() → БД
│  [InvalidateAvailabilityCache] handle() ── Cache::forget (для слушателя)  │
│  [CheckCapacity] handle(slot) ── count(confirmed) >= capacity? → 409     │
│  [ReserveCapacity] handle(slot, hold) ── lockForUpdate + UPDATE status='held' → 409/409
│  Holds/                                                                  │
│  [CreateHold] handle(slot, key) ── replay-проверка → CheckCapacity → INSERT
│  [ConfirmHold] handle(hold) ── просрочка → ReserveCapacity → SlotChanged  │
│  [CancelHold] handle(hold) ── UPDATE → cancelled → SlotChanged            │
└──────────────┬───────────────────────────────────────────────────────────┘
               │
               ▼
┌─ ДАННЫЕ ─────────────────────────────────────────────────────────────────┐
│  MySQL: slots, holds · Redis: slots:availability (только 2 класса выше)   │
└───────────────────────────────────────────────────────────────────────────┘
```

## Тест-лист (поведение без изменений, меняются классы/вызовы)

| Было (tests/Unit/Services/) | Стало | Тесты |
|---|---|---|
| `HoldLifecycleTest` (confirm-сценарии) | `Holds/ConfirmHoldTest` | 1, 2, 5: confirm→confirmed+событие; повтор идемпотентен; просрочка бросает |
| `HoldLifecycleTest` (cancel-сценарии) | `Holds/CancelHoldTest` | 3, 4: cancel из held/confirmed; повтор no-op |
| `HoldCreatorTest` | `Holds/CreateHoldTest` | 6, 7, 8: создание+expires; replay-тот же id; исчерпание бросает |
| `CapacityArbiterTest` | `Slots/ReserveCapacityTest` | 9, 10: равенство==исчерпание; только held переходит |
| `HoldApiTest`, `AvailabilityApiTest` | без изменений (контракт API тот же); инвалидация — через `AvailabilityCache::CACHE_KEY` | 11–19 |

Красный прогон: новые тест-файлы падают по `Class not found` (новых сервисов ещё нет).

## Вынос кэша в инфраструктурный слой (дополнение, согласовано)

**Мотивация:** кэш — сквозная инфраструктура; приложению может понадобиться кэшировать другие данные, поэтому слой не живёт в предметном `SlotService`.

Целевая структура (дополнение):

```
app/Cache/                          # единственное место с Cache::-фасадом
├── FlexibleCache.php               handle(string $key, array $window, callable $loader): mixed
└── CacheInvalidator.php            handle(string $key): void
```

Решения (согласованы):
1. Место — `app/Cache/`.
2. Два класса: `FlexibleCache` (чтение: flexible+lock) + `CacheInvalidator` (сброс) — строгий один-контракт.
3. Ключ и окно — константы на `AvailabilityReader` (`CACHE_KEY`, `CACHE_WINDOW`); домен владеет настройками, инфраструктура применяет.
4. `holds:stress` не трогаем.

Изменения кода:
- `AvailabilityReader` += `CACHE_KEY = 'slots:availability'`, `CACHE_WINDOW = [5, 15]`.
- `SlotController`: инжектит `FlexibleCache` + `AvailabilityReader`, вызывает `handle(CACHE_KEY, CACHE_WINDOW, fn → reader)`.
- `InvalidateCacheSlotListener`: инжектит `CacheInvalidator`, `handle(AvailabilityReader::CACHE_KEY)`.
- Удалить `SlotService/Slots/AvailabilityCache` и `InvalidateAvailabilityCache`.
- `HoldApiTest`: `AvailabilityCache::CACHE_KEY` → `AvailabilityReader::CACHE_KEY`.

Тест-лист (добавления):

### `tests/Unit/Cache/FlexibleCacheTest.php`

**Тест 1** — первый вызов грузит через loader, второй в окне — из кэша; loader вызывается ровно один раз
Вход: ключ K, loader со счётчиком, два последовательных вызова
Ожидание: счётчик == 1, результаты одинаковы
Имя: `test_first_read_loads_then_cached_loader_once`

**Тест 2** — после инвалидации (CacheInvalidator) следующий вызов снова грузит через loader
Вход: ключ K, loader со счётчиком, вызов → инвалидация → вызов
Ожидание: счётчик == 2
Имя: `test_after_invalidate_loader_runs_again`

### `tests/Unit/Cache/CacheInvalidatorTest.php`

**Тест 1** — handle(key) удаляет ключ из кэша (страж-тест: значение отсутствует)
Вход: `Cache::put(K, 'value')`, затем handle(K)
Ожидание: `Cache::has(K) == false`
Имя: `test_handle_forgets_key`

## Прогоны

**Красный (до реализации):** 10 unit-тестов на новые классы падают по `Class "App\Services\{Slots,Holds}\..." not found`; feature-тесты зелёные (контракт API не менялся).

**Зелёный (после реализации):** 22 passed (53 assertions); phpstan level 6 — 0 ошибок; pint — PASS (59 файлов).

**Конкурентная проверка:** `holds:stress 14 200` — `200: 10, 409: 190, OK: confirmed = 10` — поведение не изменилось.
