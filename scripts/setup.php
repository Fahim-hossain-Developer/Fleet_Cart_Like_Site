<?php

use Illuminate\Contracts\Console\Kernel;

// Preserve existing keys and database configuration on repeat setup.
if (! file_exists('.env')) {
    copy('.env.example', '.env');
}
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
if (! config('app.key')) {
    $kernel->call('key:generate', ['--force' => true]);
    echo $kernel->output();
}
if (config('database.default') === 'sqlite') {
    $path = config('database.connections.sqlite.database');
    if ($path !== ':memory:' && ! file_exists($path)) {
        touch($path);
    }
}
foreach ([['migrate', ['--force' => true]], ['db:seed', ['--force' => true]], ['storage:link', []], ['app:create-admin', []]] as [$command,$options]) {
    if ($command === 'storage:link' && is_link(public_path('storage'))) {
        continue;
    }
    $code = $kernel->call($command, $options);
    echo $kernel->output();
    if ($code !== 0) {
        exit($code);
    }
}
