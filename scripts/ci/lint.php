<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$directories = ['src', 'tests', 'scripts', 'config'];
$files = [];

foreach ($directories as $directory) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root . DIRECTORY_SEPARATOR . $directory)
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }
}

sort($files);
$failed = false;

foreach ($files as $file) {
    $output = [];
    $command = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file);
    exec($command, $output, $exitCode);

    if ($exitCode !== 0) {
        fwrite(STDERR, implode(PHP_EOL, $output) . PHP_EOL);
        $failed = true;
    }

}

if ($failed) {
    exit(1);
}

printf("Syntax OK: %d PHP files.%s", count($files), PHP_EOL);
