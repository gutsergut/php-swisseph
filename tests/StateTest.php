<?php

require __DIR__ . '/../src/State.php';
require __DIR__ . '/../src/functions.php';

use Swisseph\State;

swe_set_ephe_path('C:/ephe');
if (State::getEphePath() !== 'C:/ephe') {
    fwrite(STDERR, "ephe path failed\n");
    exit(1);
}

swe_set_jpl_file('de431.eph');
if (State::getJplFile() !== 'de431.eph') {
    fwrite(STDERR, "jpl file failed\n");
    exit(2);
}

swe_set_topo(10.5, 50.2, 123.0);
[$lon, $lat, $alt] = State::getTopo();
if (!($lon === 10.5 && $lat === 50.2 && $alt === 123.0)) {
    fwrite(STDERR, "topo failed\n");
    exit(3);
}

swe_set_tid_acc(0.1);
if (State::getTidAcc() !== 0.1) {
    fwrite(STDERR, "tid_acc failed\n");
    exit(4);
}

echo "OK\n";
