<?php

namespace Swisseph;

final class State
{
    private static string $ephePath = '';
    private static ?string $jplFile = null;
    private static float $topoLon = 0.0;   // degrees east
    private static float $topoLat = 0.0;   // degrees north
    private static float $topoAlt = 0.0;   // meters
    private static float $tidAcc = -25.80;    // SE_TIDAL_DEFAULT (DE431) in arcsec/cy^2
    // Sidereal/Ayanamsha settings
    private static int $sidMode = \Swisseph\Constants::SE_SIDM_FAGAN_BRADLEY;
    private static int $sidOpts = 0; // option bits
    private static float $sidT0 = 0.0; // reference JD for user mode
    private static float $sidAYan = 0.0; // user-defined ayanamsha offset (deg) at t0

    public static function setEphePath(string $path): void
    {
        self::$ephePath = $path;
    }
    public static function getEphePath(): string
    {
        return self::$ephePath;
    }

    public static function setJplFile(?string $fname): void
    {
        self::$jplFile = $fname;
    }
    public static function getJplFile(): ?string
    {
        return self::$jplFile;
    }

    public static function setTopo(float $lon, float $lat, float $alt): void
    {
        self::$topoLon = $lon;
        self::$topoLat = $lat;
        self::$topoAlt = $alt;
    }
    public static function getTopo(): array
    {
        return [self::$topoLon, self::$topoLat, self::$topoAlt];
    }

    public static function setTidAcc(float $tacc): void
    {
        self::$tidAcc = $tacc;
    }
    public static function getTidAcc(): float
    {
        return self::$tidAcc;
    }

    // Sidereal/Ayanamsha getters/setters
    public static function setSidMode(int $sidMode, int $sidOpts, float $t0, float $ayan): void
    {
        self::$sidMode = $sidMode;
        self::$sidOpts = $sidOpts;
        self::$sidT0 = $t0;
        self::$sidAYan = $ayan;
    }
    public static function getSidMode(): array
    {
        return [self::$sidMode, self::$sidOpts, self::$sidT0, self::$sidAYan];
    }
}
