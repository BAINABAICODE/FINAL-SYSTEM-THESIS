<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VisualMutation extends Model
{
    protected $fillable = [
        'lovebird_species_id',
        'species_name',
        'scientific_name',
        'name',
        'series',
        'sf',
        'df',
        'allele',
        'genotype',
        'genetic_code',
        'inheritance_type',
        'phenotype',
        'verification_status',
        'scientific_source',
        'computable',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'lovebird_species_id' => 'integer',
            'computable' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function species(): BelongsTo
    {
        return $this->belongsTo(LovebirdSpecies::class, 'lovebird_species_id');
    }

    public function birds(): HasMany
    {
        return $this->hasMany(Bird::class);
    }

    public function assignedBirds(): BelongsToMany
    {
        return $this->belongsToMany(Bird::class, 'bird_visual_mutation');
    }

    public function headToTail(): HasMany
    {
        return $this->hasMany(HeadToTailPhenotype::class);
    }
}
