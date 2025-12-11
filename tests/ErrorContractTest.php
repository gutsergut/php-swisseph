<?php

require __DIR__ . '/../src/Julian.php';
require __DIR__ . '/../src/Constants.php';
require __DIR__ . '/../src/DeltaT.php';
require __DIR__ . '/../src/Utc.php';
require __DIR__ . '/../src/Error.php';
require __DIR__ . '/../src/functions.php';

use Swisseph\ErrorCodes;

// Invalid gregflag should set $serr but still proceed
$serr = null;
$res = swe_utc_to_jd(2000, 1, 1, 0, 0, 0.0, 42, $serr);
if (!is_array($res) || count($res) !== 2) {
    fwrite(STDERR, "swe_utc_to_jd return shape failed\n");
    exit(1);
}
if ($serr === null) {
    fwrite(STDERR, "Expected serr for invalid gregflag in swe_utc_to_jd\n");
    exit(2);
}
if (strpos($serr, (string)ErrorCodes::INVALID_CALENDAR) === false) {
    fwrite(STDERR, "serr does not contain error code INVALID_CALENDAR: $serr\n");
    exit(3);
}

$serr = null;
$utc = swe_jd_to_utc($res[1], 99, $serr);
if (!is_array($utc) || count($utc) !== 6) {
    fwrite(STDERR, "swe_jd_to_utc return shape failed\n");
    exit(4);
}
if ($serr === null) {
    fwrite(STDERR, "Expected serr for invalid gregflag in swe_jd_to_utc\n");
    exit(5);
}

echo "OK\n";
