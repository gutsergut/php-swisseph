<?php

namespace Swisseph\Domain\Houses;

/**
 * Контракт стратегии системы домов.
 * Возвращает массив $cusps[1..12] в радианах (индекс 0 не используется).
 * Должен заполнять базовые куспы 1 (Asc) и 10 (MC) если система этого требует;
 * постобработка противоположных (7,4,5,6,8,9) выполняется на уровне фасада.
 */
interface HouseSystem
{
    /**
     * @param float $armc_rad ARMC в радианах
     * @param float $geolat_rad широта в радианах
     * @param float $eps_rad наклон эклиптики в радианах
     * @param float $asc_rad ascendant в радианах (если известен заранее); можно передать NAN и вычислить внутри
     * @param float $mc_rad midheaven в радианах (если известен заранее); можно передать NAN и вычислить внутри
     * @return array cusps[1..12] в радианах; индекс 0 не используется
     */
    public function cusps(float $armc_rad, float $geolat_rad, float $eps_rad, float $asc_rad = NAN, float $mc_rad = NAN): array;
}
