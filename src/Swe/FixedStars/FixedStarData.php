<?php

declare(strict_types=1);

namespace Swisseph\Swe\FixedStars;

/**
 * Fixed star data structure
 *
 * Port of struct fixed_star from sweph.h:773-781
 *
 * Represents a single fixed star with its astronomical parameters:
 * - Position (RA/Dec) at catalog epoch
 * - Proper motion (ra_mot, de_mot)
 * - Parallax
 * - Radial velocity
 * - Visual magnitude
 */
final class FixedStarData
{
    /** Maximum length for star names (from SWI_STAR_LENGTH in sweph.h:773) */
    public const STAR_LENGTH = 40;

    /**
     * Search key (may be prefixed with comma for Bayer designation)
     * C: char skey[SWI_STAR_LENGTH + 2]
     */
    public string $skey = '';

    /**
     * Traditional star name (e.g., "Sirius", "Betelgeuse")
     * C: char starname[SWI_STAR_LENGTH + 1]
     */
    public string $starname = '';

    /**
     * Bayer/Flamsteed designation (e.g., "alCMa", "beOri")
     * C: char starbayer[SWI_STAR_LENGTH + 1]
     */
    public string $starbayer = '';

    /**
     * Sequential star number in catalog
     * C: char starno[10]
     */
    public string $starno = '';

    /**
     * Epoch of coordinates (1950.0, 2000.0, or 0 for ICRS)
     * C: double epoch
     */
    public float $epoch = 0.0;

    /**
     * Right ascension at epoch (radians)
     * C: double ra
     */
    public float $ra = 0.0;

    /**
     * Declination at epoch (radians)
     * C: double de
     */
    public float $de = 0.0;

    /**
     * Proper motion in RA (radians per century, includes cos(dec) factor)
     * C: double ramot
     */
    public float $ramot = 0.0;

    /**
     * Proper motion in Dec (radians per century)
     * C: double demot
     */
    public float $demot = 0.0;

    /**
     * Radial velocity (AU per century)
     * C: double radvel
     */
    public float $radvel = 0.0;

    /**
     * Parallax (radians)
     * C: double parall
     */
    public float $parall = 0.0;

    /**
     * Visual magnitude
     * C: double mag
     */
    public float $mag = 0.0;

    /**
     * Create empty FixedStarData
     */
    public function __construct()
    {
    }

    /**
     * Create FixedStarData from array of values
     *
     * @param array $data Associative array with keys matching property names
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $star = new self();

        $star->skey = $data['skey'] ?? '';
        $star->starname = $data['starname'] ?? '';
        $star->starbayer = $data['starbayer'] ?? '';
        $star->starno = $data['starno'] ?? '';
        $star->epoch = $data['epoch'] ?? 0.0;
        $star->ra = $data['ra'] ?? 0.0;
        $star->de = $data['de'] ?? 0.0;
        $star->ramot = $data['ramot'] ?? 0.0;
        $star->demot = $data['demot'] ?? 0.0;
        $star->radvel = $data['radvel'] ?? 0.0;
        $star->parall = $data['parall'] ?? 0.0;
        $star->mag = $data['mag'] ?? 0.0;

        return $star;
    }

    /**
     * Convert to array
     *
     * @return array Associative array
     */
    public function toArray(): array
    {
        return [
            'skey' => $this->skey,
            'starname' => $this->starname,
            'starbayer' => $this->starbayer,
            'starno' => $this->starno,
            'epoch' => $this->epoch,
            'ra' => $this->ra,
            'de' => $this->de,
            'ramot' => $this->ramot,
            'demot' => $this->demot,
            'radvel' => $this->radvel,
            'parall' => $this->parall,
            'mag' => $this->mag,
        ];
    }

    /**
     * Get full star name (traditional name + Bayer designation)
     *
     * @return string Format: "Traditional,Bayer" (e.g., "Sirius,alCMa")
     */
    public function getFullName(): string
    {
        return sprintf('%s,%s', $this->starname, $this->starbayer);
    }
}
