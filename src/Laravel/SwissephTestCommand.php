<?php

declare(strict_types=1);

namespace Swisseph\Laravel;

use Illuminate\Console\Command;
use Swisseph\OO\Swisseph;
use Swisseph\Constants as C;

/**
 * Artisan command to test Swiss Ephemeris installation
 *
 * @example
 * php artisan swisseph:test
 */
class SwissephTestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'swisseph:test
                            {--planet=jupiter : Planet to calculate}
                            {--jd=2451545.0 : Julian Day}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Swiss Ephemeris installation and calculate a planet position';

    /**
     * Execute the console command.
     */
    public function handle(Swisseph $swisseph): int
    {
        $this->info('Swiss Ephemeris Test');
        $this->info('====================');
        $this->newLine();

        $planetName = $this->option('planet');
        $jd = (float) $this->option('jd');

        $this->info("Calculating {$planetName} at JD {$jd}...");
        $this->newLine();

        try {
            $result = match (strtolower($planetName)) {
                'sun' => $swisseph->sun($jd),
                'moon' => $swisseph->moon($jd),
                'mercury' => $swisseph->mercury($jd),
                'venus' => $swisseph->venus($jd),
                'mars' => $swisseph->mars($jd),
                'jupiter' => $swisseph->jupiter($jd),
                'saturn' => $swisseph->saturn($jd),
                'uranus' => $swisseph->uranus($jd),
                'neptune' => $swisseph->neptune($jd),
                'pluto' => $swisseph->pluto($jd),
                default => throw new \InvalidArgumentException("Unknown planet: {$planetName}")
            };

            if ($result->isSuccess()) {
                $this->table(
                    ['Property', 'Value'],
                    [
                        ['Longitude', sprintf('%.6f°', $result->longitude)],
                        ['Latitude', sprintf('%.6f°', $result->latitude)],
                        ['Distance', sprintf('%.6f AU', $result->distance)],
                        ['Longitude Speed', sprintf('%.6f°/day', $result->longitudeSpeed)],
                        ['Latitude Speed', sprintf('%.6f°/day', $result->latitudeSpeed)],
                        ['Distance Speed', sprintf('%.6f AU/day', $result->distanceSpeed)],
                    ]
                );

                $this->newLine();
                $this->info('✓ Swiss Ephemeris is working correctly!');

                return Command::SUCCESS;
            } else {
                $this->error('Calculation failed: ' . $result->error);
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
