<?php

declare(strict_types=1);

namespace Swisseph;

/**
 * Sidereal mode management (wrapper around State)
 */
final class SiderealMode
{
    /**
     * Set sidereal mode
     *
     * @param int $sidMode Sidereal mode constant (SE_SIDM_*)
     * @param float $t0 Reference Julian day for user mode
     * @param float $ayan User-defined ayanamsha offset (degrees) at t0
     */
    public static function set(int $sidMode, float $t0 = 0.0, float $ayan = 0.0): void
    {
        State::setSidMode($sidMode, 0, $t0, $ayan);
    }

    /**
     * Get current sidereal mode settings
     *
     * @return array [sidMode, sidOpts, t0, ayan]
     */
    public static function get(): array
    {
        return State::getSidMode();
    }

    /**
     * Check if sidereal mode is set (non-default)
     *
     * @return bool True if sidereal mode is configured
     */
    public static function isSet(): bool
    {
        [$sidMode, $sidOpts, $t0, $ayan] = State::getSidMode();
        // Consider it "set" if mode is not the default Fagan-Bradley
        // or if user-defined values are provided
        return $sidMode !== Constants::SE_SIDM_FAGAN_BRADLEY || $t0 !== 0.0 || $ayan !== 0.0;
    }
}
