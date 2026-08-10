<?php

namespace App\Services;

use R;

class PredictorService
{
    /**
     * Calculate oxygen needs using standard hospital prediction weights.
     */
    public function calculateNeeds(array $params): float
    {
        $peaditric  = (float)($params['peaditric'] ?? 0);
        $malaria    = (float)($params['malaria'] ?? 0);
        $intensive  = (float)($params['intensive'] ?? 0);
        $accident   = (float)($params['accident'] ?? 0);
        $theatre    = (float)($params['theatre'] ?? 0);
        $maternity  = (float)($params['materinity'] ?? 0);
        $typhoid    = (float)($params['typhoid'] ?? 0);
        $diabetes   = (float)($params['diabetes'] ?? 0);

        return (
            13.01
            + (3.5123 * $peaditric)
            + (5.4793 * $malaria)
            + (2.2490 * $intensive)
            + (6.8767 * $accident)
            + ($theatre * 0.1935)
            + ($maternity * 5.9922)
            + ($typhoid * -10.1190)
            + ($diabetes * -6.0203)
        );
    }

    /**
     * Calculate oxygen needs using supervisor-adjusted prediction weights.
     */
    public function calculateSupervisorNeeds(array $params): float
    {
        $peaditric  = (float)($params['peaditric'] ?? 0);
        $malaria    = (float)($params['malaria'] ?? 0);
        $intensive  = (float)($params['intensive'] ?? 0);
        $accident   = (float)($params['accident'] ?? 0);
        $theatre    = (float)($params['theatre'] ?? 0);
        $maternity  = (float)($params['materinity'] ?? 0);
        $typhoid    = (float)($params['typhoid'] ?? 0);
        $diabetes   = (float)($params['diabetes'] ?? 0);

        return (
            13.1414
            + (3.7123 * $peaditric)
            + (5.4793 * $malaria)
            + (2.2490 * $intensive)
            + (6.8767 * $accident)
            + ($theatre * 0.1935)
            + ($maternity * 5.9922)
            + ($typhoid * -10.1190)
            + ($diabetes * -6.0203)
        );
    }

    /**
     * Save a prediction run to the database.
     */
    public function savePrediction(int $hospitalId, float $needs, ?int $timestamp = null): int
    {
        $time = $timestamp ?? time();
        $prediction = R::dispense('predictions');
        $prediction->hospital_id = $hospitalId;
        $prediction->predictions = $needs;
        $prediction->tym = $time;

        return (int)R::store($prediction);
    }
}
