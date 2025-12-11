<?php

require __DIR__ . '/../src/Constants.php';

use Swisseph\Constants;

if (Constants::SE_JUL_CAL !== 0) {
    fwrite(STDERR, "SE_JUL_CAL value mismatch\n");
    exit(1);
}
if (Constants::SE_GREG_CAL !== 1) {
    fwrite(STDERR, "SE_GREG_CAL value mismatch\n");
    exit(2);
}

echo "OK\n";
