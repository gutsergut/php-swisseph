<?php

namespace Swisseph\Domain\Vsop87;

class Series
{
    /** @var Term[] */
    public array $terms = [];
    public int $power; // exponent of t

    public function __construct(int $power, array $terms = [])
    {
        $this->power = $power;
        $this->terms = $terms;
    }

    public function add(Term $t): void
    {
        $this->terms[] = $t;
    }
}
