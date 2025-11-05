<?php

namespace Swisseph\SwephFile;

/**
 * Topocentric observer data
 * Port of struct topo_data from sweph.h:758-764
 */
class TopoData
{
    /** Geographic longitude in degrees (positive East) */
    public float $geolon = 0.0;

    /** Geographic latitude in degrees (positive North) */
    public float $geolat = 0.0;

    /** Altitude above sea level in meters */
    public float $geoalt = 0.0;

    /** Julian day (TT) for which observer position was evaluated */
    public float $teval = 0.0;

    /** Julian day (UT) */
    public float $tjd_ut = 0.0;

    /** Observer barycentric position and velocity [x, y, z, dx, dy, dz] in AU */
    public array $xobs = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];

    public function __construct()
    {
        $this->geolon = 0.0;
        $this->geolat = 0.0;
        $this->geoalt = 0.0;
        $this->teval = 0.0;
        $this->tjd_ut = 0.0;
        $this->xobs = array_fill(0, 6, 0.0);
    }
}
