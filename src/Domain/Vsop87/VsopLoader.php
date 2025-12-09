<?php

namespace Swisseph\Domain\Vsop87;

/** Loads VSOP model from JSON file (full fidelity, no truncation). */
class VsopLoader
{
    public function loadFromJsonFile(string $file): VsopPlanetModel
    {
        $raw = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        $model = new VsopPlanetModel();
        foreach (['L' => $model->L, 'B' => $model->B, 'R' => $model->R] as $key => $series) {
            if (!isset($raw[$key])) continue;
            foreach ($raw[$key] as $powerStr => $terms) {
                $power = (int)$powerStr;
                foreach ($terms as $t) {
                    $A = (float)$t['A'];
                    $B = (float)$t['B'];
                    $C = (float)$t['C'];
                    $series->addTerm($power, $A, $B, $C);
                }
            }
        }
        return $model;
    }
}
