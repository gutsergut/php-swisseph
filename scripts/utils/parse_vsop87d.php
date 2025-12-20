#!/usr/bin/env php
<?php
/**
 * VSOP87D Parser - конвертирует оригинальные VSOP87D файлы в JSON формат
 *
 * Без упрощений - сохраняет все коэффициенты с полной точностью.
 * Формат VSOP87D: каждая строка содержит планетарные аргументы и коэффициенты A, B, C
 * для формулы: Σ A*cos(B + C*t), где t = (JD_TT - 2451545.0) / 365250.0
 */

if ($argc < 2) {
    echo "Usage: php parse_vsop87d.php <planet_file.ven|mar|jup|sat|ura|nep>\n";
    echo "Example: php parse_vsop87d.php data/vsop87/raw/VSOP87D.ven\n";
    exit(1);
}

$inputFile = $argv[1];
if (!file_exists($inputFile)) {
    echo "Error: file not found: $inputFile\n";
    exit(1);
}

// Определяем планету по имени файла
$planet = '';
if (str_contains($inputFile, '.mer')) {
    $planet = 'mercury';
} elseif (str_contains($inputFile, '.ven')) {
    $planet = 'venus';
} elseif (str_contains($inputFile, '.mar')) {
    $planet = 'mars';
} elseif (str_contains($inputFile, '.jup')) {
    $planet = 'jupiter';
} elseif (str_contains($inputFile, '.sat')) {
    $planet = 'saturn';
} elseif (str_contains($inputFile, '.ura')) {
    $planet = 'uranus';
} elseif (str_contains($inputFile, '.nep')) {
    $planet = 'neptune';
} else {
    echo "Error: unknown planet in filename\n";
    exit(1);
}

$outputDir = __DIR__ . '/../data/vsop87/' . $planet;
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

echo "Parsing VSOP87D for $planet...\n";

$lines = file($inputFile, FILE_IGNORE_NEW_LINES);
$currentVariable = 0; // 1=L, 2=B, 3=R
$currentPower = 0;    // 0..5 для t^0..t^5
$currentTerms = [];

$stats = [
    'L' => array_fill(0, 6, 0),
    'B' => array_fill(0, 6, 0),
    'R' => array_fill(0, 6, 0),
];

foreach ($lines as $lineNum => $line) {
    $line = trim($line);

    // Пропускаем пустые строки
    if (empty($line)) {
        continue;
    }

    // Проверяем заголовки секций
    if (str_contains($line, 'VARIABLE 1')) {
        // VARIABLE 1 = L (longitude)
        if ($currentVariable !== 0 && !empty($currentTerms)) {
            saveTerms($outputDir, $currentVariable, $currentPower, $currentTerms);
        }
        $currentVariable = 1;
        $currentTerms = [];

        // Извлекаем степень t из "T**N"
        if (preg_match('/\*T\*\*(\d+)/', $line, $m)) {
            $currentPower = (int)$m[1];
        }
        continue;
    }

    if (str_contains($line, 'VARIABLE 2')) {
        // VARIABLE 2 = B (latitude)
        if ($currentVariable !== 0 && !empty($currentTerms)) {
            saveTerms($outputDir, $currentVariable, $currentPower, $currentTerms);
        }
        $currentVariable = 2;
        $currentTerms = [];

        if (preg_match('/\*T\*\*(\d+)/', $line, $m)) {
            $currentPower = (int)$m[1];
        }
        continue;
    }

    if (str_contains($line, 'VARIABLE 3')) {
        // VARIABLE 3 = R (distance)
        if ($currentVariable !== 0 && !empty($currentTerms)) {
            saveTerms($outputDir, $currentVariable, $currentPower, $currentTerms);
        }
        $currentVariable = 3;
        $currentTerms = [];

        if (preg_match('/\*T\*\*(\d+)/', $line, $m)) {
            $currentPower = (int)$m[1];
        }
        continue;
    }

    // Парсим строки с данными (начинаются с числа)
    if (preg_match('/^\s*\d+/', $line)) {
        // Формат VSOP87D: разделённые пробелами колонки
        // Последние 4 колонки: S (игнор), A (amplitude), B (phase), C (frequency)
        $cols = preg_split('/\s+/', trim($line));
        if (count($cols) < 19) {
            continue; // неполная строка
        }

        // Берём последние 4 колонки
        $S = (float)$cols[count($cols) - 4]; // игнорируем
        $A = (float)$cols[count($cols) - 3]; // amplitude
        $B = (float)$cols[count($cols) - 2]; // phase
        $C = (float)$cols[count($cols) - 1]; // frequency

        $currentTerms[] = [
            'A' => $A,
            'B' => $B,
            'C' => $C,
        ];        // Статистика
        $varName = ['', 'L', 'B', 'R'][$currentVariable];
        if ($varName) {
            $stats[$varName][$currentPower]++;
        }
    }
}

// Сохраняем последнюю секцию
if ($currentVariable !== 0 && !empty($currentTerms)) {
    saveTerms($outputDir, $currentVariable, $currentPower, $currentTerms);
}

echo "\nParsing complete!\n";
echo "Statistics:\n";
foreach ($stats as $var => $powers) {
    echo "$var: ";
    foreach ($powers as $p => $count) {
        if ($count > 0) {
            echo "{$var}{$p}=$count ";
        }
    }
    echo "\n";
}
echo "\nOutput directory: $outputDir\n";

/**
 * Сохраняет термы в JSON файл
 */
function saveTerms(string $dir, int $variable, int $power, array $terms): void
{
    $varName = ['', 'L', 'B', 'R'][$variable];
    $filename = "{$dir}/{$varName}{$power}.json";

    $json = json_encode($terms, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    file_put_contents($filename, $json);

    $count = count($terms);
    echo "Saved {$varName}{$power}.json ($count terms)\n";
}
