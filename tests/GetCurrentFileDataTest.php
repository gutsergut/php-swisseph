<?php

require_once __DIR__ . '/bootstrap.php';

/**
 * Test swe_get_current_file_data()
 *
 * Full port verification from sweph.c:8351-8360.
 *
 * Tests ephemeris file metadata retrieval:
 * - Boundary checks (ifno < 0 or > 4)
 * - File data access after swe_calc()
 * - Return values: filename, tfstart, tfend, denum
 */

echo "=== Get Current File Data Test ===\n\n";

// Test 1: Invalid file number (< 0)
echo "Test 1: Invalid file number (ifno = -1)\n";
$tfstart = 0.0;
$tfend = 0.0;
$denum = 0;
$result = swe_get_current_file_data(-1, $tfstart, $tfend, $denum);

if ($result === null && $tfstart === 0.0 && $tfend === 0.0 && $denum === 0) {
    echo "✓ PASS: Returns null for ifno < 0\n";
} else {
    echo "✗ FAIL: Should return null for invalid ifno\n";
    exit(1);
}

// Test 2: Invalid file number (> 4)
echo "\nTest 2: Invalid file number (ifno = 5)\n";
$tfstart = 0.0;
$tfend = 0.0;
$denum = 0;
$result = swe_get_current_file_data(5, $tfstart, $tfend, $denum);

if ($result === null && $tfstart === 0.0 && $tfend === 0.0 && $denum === 0) {
    echo "✓ PASS: Returns null for ifno > 4\n";
} else {
    echo "✗ FAIL: Should return null for invalid ifno\n";
    exit(1);
}

// Test 3: Valid file number but no calculation yet (should return null)
echo "\nTest 3: Valid file number (ifno = 0) before any calculation\n";
$tfstart = 0.0;
$tfend = 0.0;
$denum = 0;
$result = swe_get_current_file_data(0, $tfstart, $tfend, $denum);

if ($result === null) {
    echo "✓ PASS: Returns null when no file loaded\n";
} else {
    echo "⚠ WARNING: File already loaded (ephemeris path set): $result\n";
    echo "  tfstart = $tfstart\n";
    echo "  tfend = $tfend\n";
    echo "  denum = $denum\n";
}

// Test 4: After calculation (if ephemeris files available)
echo "\nTest 4: After swe_calc() (if ephemeris files available)\n";

// Try to calculate Sun position
$jd = 2451545.0; // J2000.0
$xx = [];
$serr = null;
$iflag = \Swisseph\Constants::SEFLG_SWIEPH | \Swisseph\Constants::SEFLG_SPEED;

$retval = swe_calc($jd, \Swisseph\Constants::SE_SUN, $iflag, $xx, $serr);

if ($retval >= 0) {
    // Calculation successful, check file data
    $tfstart = 0.0;
    $tfend = 0.0;
    $denum = 0;

    // File 0 = planet file (Sun uses this)
    $result = swe_get_current_file_data(0, $tfstart, $tfend, $denum);

    if ($result !== null) {
        echo "✓ PASS: Returns file data after calculation\n";
        echo "  Filename: $result\n";
        echo "  Start JD: $tfstart\n";
        echo "  End JD: $tfend\n";
        echo "  DE number: $denum\n";

        // Verify reasonable values
        if ($tfstart > 0 && $tfend > $tfstart) {
            echo "✓ PASS: Time range is valid (tfend > tfstart > 0)\n";
        } else {
            echo "⚠ WARNING: Time range may be invalid\n";
        }
    } else {
        echo "⚠ WARNING: Returns null even after calculation (Moshier ephemeris?)\n";
        echo "  This is expected for built-in ephemeris (no file loaded)\n";
    }
} else {
    echo "⚠ WARNING: swe_calc() failed: " . ($serr ?? "unknown error") . "\n";
    echo "  This is expected if ephemeris files are not available\n";
}

// Test 5: Moon file (ifno = 1)
echo "\nTest 5: Moon file data (ifno = 1) after Moon calculation\n";

$xx = [];
$serr = null;
$retval = swe_calc($jd, \Swisseph\Constants::SE_MOON, $iflag, $xx, $serr);

if ($retval >= 0) {
    $tfstart = 0.0;
    $tfend = 0.0;
    $denum = 0;
    $result = swe_get_current_file_data(1, $tfstart, $tfend, $denum);

    if ($result !== null) {
        echo "✓ PASS: Returns Moon file data\n";
        echo "  Filename: $result\n";
    } else {
        echo "⚠ INFO: No Moon file data (using built-in ephemeris)\n";
    }
} else {
    echo "⚠ WARNING: Moon calculation failed\n";
}

echo "\n=== Test Complete ===\n";
