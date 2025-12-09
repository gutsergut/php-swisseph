<?php

namespace Swisseph\Swe\Planets;

class StrategyResult
{
    /** @var 'barycentric_j2000'|'final' */
    public string $kind;
    /** @var array{0:float,1:float,2:float,3:float,4:float,5:float} */
    public array $x;
    /** @var int */
    public int $retc;
    /** @var string|null */
    public ?string $serr;

    private function __construct(string $kind, array $x, int $retc = 0, ?string $serr = null)
    {
        $this->kind = $kind;
        $this->x = $x;
        $this->retc = $retc;
        $this->serr = $serr;
    }

    public static function okBary(array $x): self
    {
        return new self('barycentric_j2000', $x, 0, null);
    }

    public static function okFinal(array $x): self
    {
        return new self('final', $x, 0, null);
    }

    public static function err(string $message, int $retc): self
    {
        return new self('final', [0.0, 0.0, 0.0, 0.0, 0.0, 0.0], $retc, $message);
    }
}
