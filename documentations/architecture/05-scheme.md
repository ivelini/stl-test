# Шаг 5. Схема архитектуры

- **Дата:** 2026-08-28 (актуализировано 2026-08-31 под реализацию, ADR 0008–0011)
- **Статус:** согласовано
- **Метод:** «Head First. Архитектура ПО», гл. 9 (шаг 5 плана действий: физическое представление)

Физическое «приземление» логических компонентов (шаг 2) на уровни (шаг 3). Появляются БД, кэш, заданные из ТЗ классы и механизм инвалидации кэша. Логические компоненты (Витрина, Выдача, Арбитраж, Жизненный цикл) не изменились — изменилось их физическое воплощение (ADR 0008–0011).

---

## 1. Физическая схема

```
┌─ ВНЕШНИЙ МИР ───────────────────────────────────────────────────────────┐
│  Клиент API: curl, фронтенд                                             │
│  GET /slots/availability?page= · POST /slots/{id}/hold (Idempotency-Key)│
│  POST /holds/{id}/confirm · DELETE /holds/{id}                          │
└───────────────────────────┬─────────────────────────────────────────────┘
                            │ HTTP
                            ▼
┌─ ПРЕДСТАВЛЕНИЕ ─────────────────────────────────────────────────────────┐
│  routes/api.php                                                         │
│  SlotRequest (uuid Idempotency-Key, 422) · SlotAvailabilityRequest (page)│
│  SlotController@index · SlotHoldController ·                            │
│  HoldConfirmController · HoldController@destroy                         │
│  — тонкие: валидация + JSON-обёртка (ADR 0010)                          │
└──────────────┬──────────────────────────────────────────────────────────┘
               │ вызов
               ▼
┌─ РАБОЧИЙ ПРОЦЕСС ───────────────────────────────────────────────────────┐
│  app/Services/SlotService — слой «SlotService» из ТЗ (ADR 0005, 0008)   │
│  Slots/ (Витрина + Арбитраж)                                            │
│    AvailabilityReader::handlePage — SQL-страница + total, остаток в БД  │
│    ReadAvailabilityPage — версия → ключ → FlexibleCache → meta          │
│    CheckCapacity — проверка места (путь создания, 409)                  │
│    ReserveCapacity — FOR UPDATE на слот + условный UPDATE (арбитраж)    │
│  Holds/ (Выдача + Жизненный цикл)                                       │
│    CreateHold — идемпотентно, expires_at = now + 5 мин                  │
│    ConfirmHold — просрочка → ReserveCapacity → SlotChanged              │
│    CancelHold — условный UPDATE → cancelled → SlotChanged               │
│                                                                          │
│  Событие SlotChanged (после коммита)                                    │
│    └─► InvalidateCacheSlotListener → BumpCacheVersion (инкремент, O(1))│
└──────────────┬──────────────────────────────────────────────────────────┘
               │ чтение / запись
               ▼
┌─ ДАННЫЕ ────────────────────────────────────────────────────────────────┐
│  MySQL 8                                                               │
│    slots — id, capacity (неизменяемая конфигурация)                     │
│    holds — slot_id, status, idempotency_key, expires_at NOT NULL;      │
│            UNIQUE(slot_id, idempotency_key)                            │
│  Кэш (Redis) — только путь чтения (ADR 0004)                           │
│    slots:availability:version — счётчик версий (инвалидация, ADR 0011) │
│    slots:availability:v{N}:p{M} — страницы остатков (ADR 0011)         │
└─────────────────────────────────────────────────────────────────────────┘
```

## 2. То же в Mermaid

```mermaid
flowchart TD
    Client[Клиент API] -->|HTTP, Idempotency-Key| Routes[routes/api.php]
    Routes --> SlotController
    Routes --> SlotHoldController
    Routes --> HoldConfirmController
    Routes --> HoldController
    SlotController -->|page| ReadAvailabilityPage
    ReadAvailabilityPage -->|ключ v{ver}:p{page}| FlexibleCache[FlexibleCache]
    ReadAvailabilityPage --> ReadCacheVersion[ReadCacheVersion]
    FlexibleCache -->|промах| AvailabilityReader
    AvailabilityReader --> DB[(MySQL: slots, holds)]
    FlexibleCache --> Cache[(Redis: страницы остатков)]
    SlotHoldController --> CreateHold
    CreateHold --> CheckCapacity
    HoldConfirmController --> ConfirmHold
    ConfirmHold --> ReserveCapacity
    HoldController --> CancelHold
    ConfirmHold -->|после коммита| SlotChanged[Событие SlotChanged]
    CancelHold -->|после коммита| SlotChanged
    SlotChanged --> Listener[InvalidateCacheSlotListener]
    Listener --> BumpCacheVersion[BumpCacheVersion]
    BumpCacheVersion --> Cache
```

## 3. Потоки через уровни

| Сценарий | Цепочка | Данные |
|---|---|---|
| Показать доступность | SlotController → ReadAvailabilityPage → FlexibleCache → AvailabilityReader | кэш-страница при попадании, БД при промахе; версия — счётчик |
| Создать удержание | SlotHoldController → CreateHold → CheckCapacity | replay по ключу; INSERT; 409 при исчерпании; 201/200 |
| Подтвердить | HoldConfirmController → ConfirmHold → ReserveCapacity | FOR UPDATE на слот + условный UPDATE; после коммита → SlotChanged → инкремент версии |
| Отменить | HoldController@destroy → CancelHold | условный UPDATE → cancelled; после коммита → SlotChanged → инкремент версии |
| Фон: сроки вышли | **не реализовано** — просрочка производная от `expires_at` (ADR 0001), фоновая очистка не нужна | — |

## 4. Соответствия и ссылки

| Пункт схемы | Источник решения |
|---|---|
| Сервисный слой — `app/Services/SlotService`, класса `SlotService` нет | ADR 0005 |
| Один сервис = один контракт `handle()`, домены `Slots`/`Holds` | ADR 0008 |
| Кэш только на пути чтения | ADR 0004 |
| Сброс кэша событием после коммита, знание о кэше у Витрины | ADR 0006 |
| Кэш — инфраструктурный слой `App\Cache` (единственное место с `Cache::`) | ADR 0009 |
| Рабочий процесс витрины в сервисе, контроллер тонкий | ADR 0010 |
| Постраничный кэш, инвалидация версией | ADR 0011 |
| Остаток вычисляется, в `slots` нет счётчика | ADR 0002 |
| Просрочка возвращает ничего — удержание не резервировало | ADR 0001 |
| Окно кэша 5–15 с: две фазы, защита от лавины в момент инвалидации | ADR 0007 |
