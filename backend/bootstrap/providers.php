<?php

use App\Providers\AppServiceProvider;

/*
| Application providers, plus every module's ServiceProvider auto-discovered
| from modules/<Name>/<Name>ServiceProvider.php. Dropping in a new module
| directory with its provider is enough — there is no central list to edit
| (ADR-0007). deptrac still governs what those modules may import.
*/

$modulesPath = dirname(__DIR__).DIRECTORY_SEPARATOR.'modules';

$moduleProviders = [];
foreach (glob($modulesPath.DIRECTORY_SEPARATOR.'*'.DIRECTORY_SEPARATOR.'*ServiceProvider.php') ?: [] as $providerFile) {
    $relative = substr($providerFile, strlen($modulesPath) + 1, -strlen('.php'));
    $moduleProviders[] = 'Modules\\'.str_replace(['/', '\\'], '\\', $relative);
}

return array_merge([
    AppServiceProvider::class,
], $moduleProviders);
