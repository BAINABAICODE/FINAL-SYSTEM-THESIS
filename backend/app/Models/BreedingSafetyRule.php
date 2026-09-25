<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BreedingSafetyRule extends Model
{
    protected $fillable = [
        'lovebird_species_id',
        'minimum_age_months',
        'recommended_min_months',
        'recommended_max_months',
        'warning_below_minimum',
        'warning_outside_range',
        'scientific_source',
        'verification_status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'lovebird_species_id' => 'integer',
            'minimum_age_months' => 'integer',
            'recommended_min_months' => 'integer',
            'recommended_max_months' => 'integer',
        ];
    }

    public function species(): BelongsTo
    {
        return $this->belongsTo(LovebirdSpecies::class, 'lovebird_species_id');
    }
}
