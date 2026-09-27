<?php

namespace Modules\Records;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class RecordsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Internal/Database/Migrations');
        Route::middleware('api')->prefix('api/v1')->group(__DIR__.'/Public/Http/routes.php');
    }
}
