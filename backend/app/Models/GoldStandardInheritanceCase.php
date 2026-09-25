<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoldStandardInheritanceCase extends Model
{
    protected $fillable = [
        'case_key',
        'lovebird_species_id',
        'locus_name',
        'inheritance_type',
        'cock_code',
        'hen_code',
        'sex_linked',
        'expected_outcomes',
        'hand_computation',
        'scientific_source',
        'verification_status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'lovebird_species_id' => 'integer',
            'sex_linked' => 'boolean',
            'expected_outcomes' => 'array',
        ];
    }

    public function species(): BelongsTo
    {
        return $this->belongsTo(LovebirdSpecies::class, 'lovebird_species_id');
    }
}
