<?php

declare(strict_types=1);

/**
 * Test for StarRegistry functionality
 *
 * Tests in-memory star catalog loading, indexing, and search
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;
use Swisseph\State;
use Swisseph\Swe\FixedStars\StarRegistry;

// Set ephemeris path
$ephePath = __DIR__ . '/../../eph/ephe';
if (!is_dir($ephePath)) {
    echo "❌ Ephemeris path not found: {$ephePath}\n";
    exit(1);
}

State::setEphePath($ephePath);

echo "=== StarRegistry Tests ===\n\n";

// Test 1: Load catalog
echo "Test 1: Load star catalog\n";
StarRegistry::reset();
$serr = null;
$result = StarRegistry::loadAll($serr);

if ($result === Constants::SE_OK) {
    echo "✅ Catalog loaded successfully\n";
    echo "   Real stars: " . StarRegistry::getRealCount() . "\n";
    echo "   Named stars: " . StarRegistry::getNamedCount() . "\n";
    echo "   Total records: " . StarRegistry::getRecordsCount() . "\n";
    echo "   Old format: " . (StarRegistry::isOldFormat() ? 'yes' : 'no') . "\n";
} else {
    echo "❌ Failed to load catalog: {$serr}\n";
    exit(1);
}

// Verify counts
if (StarRegistry::getRealCount() === 0) {
    echo "❌ No stars loaded\n";
    exit(1);
}

echo "\n";

// Test 2: Sequential search
echo "Test 2: Sequential search (by number)\n";
$testNumbers = [1, 2, 10];
foreach ($testNumbers as $num) {
    $serr = null;
    $star = StarRegistry::search((string) $num, $serr);

    if ($star !== null) {
        echo "✅ Star #{$num}: {$star->getFullName()} (mag={$star->mag})\n";
    } else {
        echo "❌ Failed to find star #{$num}: {$serr}\n";
        exit(1);
    }
}

echo "\n";

// Test 3: Wildcard search (traditional names)
echo "Test 3: Wildcard search\n";
$wildcards = ['sirius%', 'aldeb%', 'rigel%'];
foreach ($wildcards as $pattern) {
    $serr = null;
    $star = StarRegistry::search($pattern, $serr);

    if ($star !== null) {
        echo "✅ Pattern '{$pattern}': {$star->getFullName()} (mag={$star->mag})\n";
    } else {
        echo "❌ Failed to match pattern '{$pattern}': {$serr}\n";
        exit(1);
    }
}

echo "\n";

// Test 4: Exact Bayer designation
echo "Test 4: Exact Bayer designation search\n";
$bayerNames = [',alCMa', ',alTau', ',beCar', ',alOri'];
foreach ($bayerNames as $bayer) {
    $serr = null;
    $star = StarRegistry::search($bayer, $serr);

    if ($star !== null) {
        echo "✅ Bayer '{$bayer}': {$star->getFullName()} (mag={$star->mag})\n";
    } else {
        echo "❌ Failed to find Bayer '{$bayer}': {$serr}\n";
        exit(1);
    }
}

echo "\n";

// Test 5: Exact traditional name
echo "Test 5: Exact traditional name search\n";
$traditionalNames = ['sirius', 'aldebaran', 'canopus', 'rigel'];
foreach ($traditionalNames as $name) {
    $serr = null;
    $star = StarRegistry::search($name, $serr);

    if ($star !== null) {
        echo "✅ Name '{$name}': {$star->getFullName()} (mag={$star->mag})\n";
    } else {
        echo "❌ Failed to find name '{$name}': {$serr}\n";
        exit(1);
    }
}

echo "\n";

// Test 6: Invalid searches
echo "Test 6: Invalid searches (should fail gracefully)\n";
$invalidSearches = [
    ['9999', 'sequential number too large'],
    ['nonexistent%', 'wildcard that doesn\'t match'],
    ['xyz', 'name that doesn\'t exist'],
    ['sirius%%', 'invalid wildcard position'],
];

foreach ($invalidSearches as [$search, $description]) {
    $serr = null;
    $star = StarRegistry::search($search, $serr);

    if ($star === null && $serr !== '') {
        echo "✅ {$description}: correctly failed with error\n";
    } else {
        echo "❌ {$description}: should have failed but didn't\n";
        exit(1);
    }
}

echo "\n";

// Test 7: Verify data integrity
echo "Test 7: Verify star data integrity\n";
$serr = null;
$sirius = StarRegistry::search('sirius', $serr);

if ($sirius === null) {
    echo "❌ Cannot find Sirius for integrity check\n";
    exit(1);
}

// Check that basic fields are populated
$checks = [
    ['starname', $sirius->starname !== '', "Star name: {$sirius->starname}"],
    ['starbayer', $sirius->starbayer !== '', "Bayer: {$sirius->starbayer}"],
    ['ra', $sirius->ra !== 0.0, "RA: {$sirius->ra} rad"],
    ['de', $sirius->de !== 0.0, "Dec: {$sirius->de} rad"],
    ['mag', $sirius->mag < 0.0, "Magnitude: {$sirius->mag} (Sirius is very bright)"],
];

foreach ($checks as [$field, $condition, $message]) {
    if ($condition) {
        echo "✅ {$message}\n";
    } else {
        echo "❌ Failed check for {$field}\n";
        exit(1);
    }
}

echo "\n";

// Test 8: Load already loaded (should return -2)
echo "Test 8: Re-load catalog (should detect already loaded)\n";
$serr = null;
$result = StarRegistry::loadAll($serr);

if ($result === -2) {
    echo "✅ Correctly detected catalog already loaded\n";
} else {
    echo "❌ Should have returned -2 for already loaded, got {$result}\n";
    exit(1);
}

echo "\n=== All StarRegistry tests passed ✅ ===\n";
