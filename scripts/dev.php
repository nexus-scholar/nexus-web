<?php

declare(strict_types=1);

function shell_arg(string $value): string
{
    return escapeshellarg($value);
}

$commands = [
    'server' => 'php artisan serve --host=localhost',
    'queue' => 'php artisan queue:listen --tries=1 --timeout=0',
];

$colors = [
    'server' => '#93c5fd',
    'queue' => '#c4b5fd',
];

if (function_exists('pcntl_fork')) {
    $commands['logs'] = 'php artisan pail --timeout=0';
    $colors['logs'] = '#fb7185';
} else {
    fwrite(STDERR, "Skipping Laravel Pail: the pcntl extension is not available in this PHP runtime.\n");
}

$commands['vite'] = 'npm run dev';
$colors['vite'] = '#fdba74';

$arguments = [
    'npx',
    'concurrently',
    '-c',
    implode(',', array_values($colors)),
    '--names='.implode(',', array_keys($commands)),
    '--kill-others',
];

foreach ($commands as $command) {
    $arguments[] = $command;
}

passthru(implode(' ', array_map('shell_arg', $arguments)), $exitCode);

exit($exitCode);
