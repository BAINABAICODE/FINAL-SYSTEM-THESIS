<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpeciesBreedingCompatibility extends Model
{
    public const SAME_SPECIES = 'SAME_SPECIES';

    public const DOCUMENTED_HYBRID = 'DOCUMENTED_HYBRID';

    public const LIMITED_OR_UNCERTAIN = 'LIMITED_OR_UNCERTAIN';

    public const NOT_DOCUMENTED = 'NOT_DOCUMENTED';

    public const UNSUPPORTED = 'UNSUPPORTED';

    protected $fillable = [
        'species_1_id',
        'species_2_id',
        'species_low_id',
        'species_high_id',
        'direction_sensitive',
        'compatibility_status',
        'breeding_type',
        'fertility_status',
        'risk_level',
        'warning_message',
        'scientific_basis',
        'scientific_source',
        'verification_status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'species_1_id' => 'integer',
            'species_2_id' => 'integer',
            'species_low_id' => 'integer',
            'species_high_id' => 'integer',
            'direction_sensitive' => 'boolean',
        ];
    }

    public function speciesOne(): BelongsTo
    {
        return $this->belongsTo(LovebirdSpecies::class, 'species_1_id');
    }

    public function speciesTwo(): BelongsTo
    {
        return $this->belongsTo(LovebirdSpecies::class, 'species_2_id');
    }

    public static function pairKey(int $leftId, int $rightId): array
    {
        return [min($leftId, $rightId), max($leftId, $rightId)];
    }
}
