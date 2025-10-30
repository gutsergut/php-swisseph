<?php
/**
 * Скрипт для запуска всех тестов проекта
 * Использование: php scripts/run_all_tests.php
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

$projectRoot = dirname(__DIR__);
$scriptsDir = __DIR__;

echo "=== SWISS EPHEMERIS PHP PORT - ПОЛНОЕ ТЕСТИРОВАНИЕ ===\n\n";

$errors = [];
$warnings = [];

// 1. Проверка PHPUnit
echo "1. Запуск PHPUnit тестов...\n";
echo str_repeat('-', 70) . "\n";

$phpunitCmd = $projectRoot . '/vendor/bin/phpunit';
if (PHP_OS_FAMILY === 'Windows') {
    $phpunitCmd .= '.bat';
}

if (file_exists($phpunitCmd)) {
    $phpunitConfig = $projectRoot . '/phpunit.xml.dist';
    $cmd = sprintf('"%s" -c "%s" --testdox', $phpunitCmd, $phpunitConfig);

    passthru($cmd, $phpunitResult);

    if ($phpunitResult !== 0) {
        $errors[] = "PHPUnit тесты завершились с ошибками (код: $phpunitResult)";
    }
} else {
    $warnings[] = "PHPUnit не найден. Установите зависимости: composer install";
}

echo "\n";

// 2. Скриптовые тесты
$scriptTests = [
    'basic_houses.php' => 'Базовые системы домов',
    'house_position.php' => 'Позиции в домах',
    'date_time.php' => 'Дата и время',
    'azalt.php' => 'Горизонтальные координаты',
    'rise_trans.php' => 'Восход/заход/транзит',
    'ayanamsa.php' => 'Аянамша',
    'planetary_calc.php' => 'Планетарные вычисления',
    'pheno.php' => 'Планетарные явления',
];

echo "2. Запуск скриптовых тестов...\n";
echo str_repeat('-', 70) . "\n";

foreach ($scriptTests as $script => $description) {
    $scriptPath = $scriptsDir . '/' . $script;

    if (!file_exists($scriptPath)) {
        $warnings[] = "Скрипт $script не найден";
        continue;
    }

    echo "  ► $description ($script)...\n";

    $output = [];
    $returnCode = 0;
    exec("php \"$scriptPath\" 2>&1", $output, $returnCode);

    if ($returnCode !== 0) {
        $errors[] = "Скрипт $script завершился с ошибкой (код: $returnCode)";
        echo "    ✗ ОШИБКА\n";
        echo "    Вывод:\n";
        foreach ($output as $line) {
            echo "      $line\n";
        }
    } else {
        // Проверяем на ключевые слова ошибок в выводе
        $errorFound = false;
        foreach ($output as $line) {
            if (stripos($line, 'error') !== false ||
                stripos($line, 'fatal') !== false ||
                stripos($line, 'exception') !== false) {
                $errorFound = true;
                break;
            }
        }

        if ($errorFound) {
            $errors[] = "Скрипт $script выдал ошибки в выводе";
            echo "    ✗ ОШИБКА В ВЫВОДЕ\n";
            foreach ($output as $line) {
                echo "      $line\n";
            }
        } else {
            echo "    ✓ OK\n";
        }
    }
}

echo "\n";

// 3. Отчёт
echo str_repeat('=', 70) . "\n";
echo "ИТОГОВЫЙ ОТЧЁТ\n";
echo str_repeat('=', 70) . "\n\n";

if (count($warnings) > 0) {
    echo "⚠ ПРЕДУПРЕЖДЕНИЯ (" . count($warnings) . "):\n";
    foreach ($warnings as $i => $warning) {
        echo "  " . ($i + 1) . ". $warning\n";
    }
    echo "\n";
}

if (count($errors) > 0) {
    echo "✗ ОШИБКИ (" . count($errors) . "):\n";
    foreach ($errors as $i => $error) {
        echo "  " . ($i + 1) . ". $error\n";
    }
    echo "\n";
    echo "СТАТУС: ТЕСТЫ НЕ ПРОЙДЕНЫ\n";
    exit(1);
} else {
    echo "✓ ВСЕ ТЕСТЫ ПРОЙДЕНЫ УСПЕШНО\n";
    exit(0);
}
