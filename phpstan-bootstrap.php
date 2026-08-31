<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;

/**
 * Стабилизатор для larastan: константа LARAVEL_VERSION должна существовать
 * до построения DI-контейнера phpstan (иначе LarastanStubFilesExtension
 * падает «Undefined constant»). Larastan определяет её в своём bootstrap.php,
 * который грузится не во всех фазах phpstan, — страхуем сами.
 */
if (! defined('LARAVEL_VERSION')) {
    if (class_exists(Application::class)) {
        define('LARAVEL_VERSION', Application::VERSION);
    } else {
        define('LARAVEL_VERSION', '12.0.0');
    }
}
