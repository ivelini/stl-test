<?php

return [
    // Домен Slots (Витрина доступности): идентификатор кэша и его окно (ADR 0007)
    'cache_key' => 'slots:availability',
    'cache_window' => [5, 15],

    // Домен Slots (Витрина): размер страницы доступности
    'per_page' => 100,

    // Домен Holds (Выдача): срок годности билета, секунды (ADR 0001)
    'expires_seconds' => 300,

    // Инфраструктура кэша (FlexibleCache): таймаут удержания и ожидания блокировки, секунды
    'cache_lock_seconds' => 10,
    'cache_wait_seconds' => 5,
];
