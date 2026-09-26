<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BirdPair extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'parent_1_bird_id',
        'parent_2_bird_id',
        'pairing_type',
        'species_1_id',
        'species_2_id',
        'pairing_status',
        'compatibility_status',
        'fertility_status',
        'risk_level',
        'warning_summary',
    ];

    public function parentOne(): BelongsTo
    {
        return $this->belongsTo(Bird::class, 'parent_1_bird_id');
    }

    public function parentTwo(): BelongsTo
    {
        return $this->belongsTo(Bird::class, 'parent_2_bird_id');
    }

    public function speciesOne(): BelongsTo
    {
        return $this->belongsTo(LovebirdSpecies::class, 'species_1_id');
    }

    public function speciesTwo(): BelongsTo
    {
        return $this->belongsTo(LovebirdSpecies::class, 'species_2_id');
    }

    public function computationResults(): HasMany
    {
        return $this->hasMany(ComputationResult::class);
    }
}
