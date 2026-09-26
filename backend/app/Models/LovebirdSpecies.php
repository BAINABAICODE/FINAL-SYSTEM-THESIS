<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LovebirdSpecies extends Model
{
    public $timestamps = false;

    protected $table = 'lovebird_species';

    protected $fillable = [
        'id',
        'common_name',
        'alternate_names',
        'scientific_name',
        'species_group',
        'has_eye_ring',
        'description',
        'scientific_source',
        'verification_status',
    ];

    protected function casts(): array
    {
        return [
            'has_eye_ring' => 'boolean',
        ];
    }

    public function birds(): HasMany
    {
        return $this->hasMany(Bird::class, 'species_id');
    }

    public function visualMutations(): HasMany
    {
        return $this->hasMany(VisualMutation::class, 'lovebird_species_id');
    }

    public function headToTailIdentity(): HasMany
    {
        return $this->hasMany(HeadToTailPhenotype::class, 'lovebird_species_id');
    }
}
