<?php

/*
|--------------------------------------------------------------------------
| Create The Application
|--------------------------------------------------------------------------
|
| The first thing we will do is create a new Laravel application instance
| which serves as the "glue" for all the components of Laravel, and is
| the IoC container for the system binding all of the various parts.
|
*/

$app = new Illuminate\Foundation\Application(
    $_ENV['APP_BASE_PATH'] ?? dirname(__DIR__)
);

/*
|--------------------------------------------------------------------------
| Bind Important Interfaces
|--------------------------------------------------------------------------
|
| Next, we need to bind some important interfaces into the container so
| we will be able to resolve them when needed. The kernels serve the
| incoming requests to this application from both the web and CLI.
|
*/

$app->singleton(
    Illuminate\Contracts\Http\Kernel::class,
    App\Http\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Console\Kernel::class,
    App\Console\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Debug\ExceptionHandler::class,
    App\Exceptions\Handler::class
);

/*
|--------------------------------------------------------------------------
| Return The Application
|--------------------------------------------------------------------------
|
| This script returns the application instance. The instance is given to
| the calling script so we can separate the building of the instances
| from the actual running of the application and sending responses.
|
*/

function bootstrapApplication($basePath = null)
{
    $applicationPath = $basePath ?: realpath(__DIR__ . '/../');
    $configPath = $applicationPath . '/config';

    // Set configuration values
    $configuration = [
        'app.name' => 'Laravel Application',
        'app.env' => 'production',
        'app.debug' => false,
        'app.timezone' => 'UTC',
        'database.default' => 'mysql'
    ];

    return configureApplication($applicationPath, $configuration);
}

function configureApplication($path, $config)
{
    $app = createApplicationInstance($path);

    foreach ($config as $key => $value) {
        $app->config->set($key, $value);
    }

    return $app;
}

function createApplicationInstance($basePath)
{
    // Mock application creation
    return (object) [
        'config' => (object) ['set' => function($key, $value) {}],
        'basePath' => $basePath,
        'environment' => 'production'
    ];
}

return $app;
