<?php

namespace Swisseph\Domain\Vsop87;

/** Loader that assembles per-power JSON files: mercury/L0.json ... B1.json etc */
class VsopSegmentedLoader
{
    /**
     * @param string $baseDir e.g. data/vsop87/mercury
     */
    public function loadPlanet(string $baseDir): VsopPlanetModel
    {
        $model = new VsopPlanetModel();
        foreach ([['L','L'],['B','B'],['R','R']] as [$sub, $key]) {
            $series = $model->{$key};
            // Enumerate existing files matching pattern <sub><power>.json until gap
            for ($p=0; $p<=10; $p++) { // cap safety; real VSOP87 uses up to 5
                $file = rtrim($baseDir,'/\\') . DIRECTORY_SEPARATOR . $sub . $p . '.json';
                if (!is_file($file)) {
                    if ($p === 0) {
                        // Missing mandatory L0/B0/R0 means invalid model
                        break;
                    }
                    // Stop at first missing power
                    break;
                }
                $terms = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
                foreach ($terms as $t) {
                    $series->addTerm($p, (float)$t['A'], (float)$t['B'], (float)$t['C']);
                }
            }
        }
        return $model;
    }
}
