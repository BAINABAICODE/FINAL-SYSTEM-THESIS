<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SplitGene extends Model
{
    protected $fillable = [
        'lovebird_species_id',
        'species_name',
        'scientific_name',
        'name',
        'genetic_symbol',
        'wild_type_allele',
        'mutant_allele',
        'inheritance_type',
        'genetic_category',
        'cock_can_split',
        'hen_can_split',
        'heterozygous_genotype',
        'homozygous_genotype',
        'genetic_code',
        'phenotype_when_visual',
        'description',
        'scientific_source',
        'verification_status',
        'computable',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'lovebird_species_id' => 'integer',
            'cock_can_split' => 'boolean',
            'hen_can_split' => 'boolean',
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
        return $this->belongsToMany(Bird::class, 'bird_split_gene');
    }
}
