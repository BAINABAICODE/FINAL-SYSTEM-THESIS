<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bird extends Model
{
    public const SEX_HEN = 'hen';

    public const SEX_COCK = 'cock';

    public const GRANDPARENT_ROLES = [
        'paternal_grandfather',
        'paternal_grandmother',
        'maternal_grandfather',
        'maternal_grandmother',
    ];

    protected $fillable = [
        'bird_id',
        'age_months',
        'species_id',
        'sex',
        'base_color_id',
        'visual_mutation_id',
        'split_gene_id',
    ];

    protected function casts(): array
    {
        return [
            'age_months' => 'integer',
            'species_id' => 'integer',
            'base_color_id' => 'integer',
            'visual_mutation_id' => 'integer',
            'split_gene_id' => 'integer',
        ];
    }

    public function species(): BelongsTo
    {
        return $this->belongsTo(LovebirdSpecies::class, 'species_id');
    }

    public function baseColor(): BelongsTo
    {
        return $this->belongsTo(BaseColor::class);
    }

    public function visualMutation(): BelongsTo
    {
        return $this->belongsTo(VisualMutation::class);
    }

    public function visualMutations(): BelongsToMany
    {
        return $this->belongsToMany(VisualMutation::class, 'bird_visual_mutation')
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function splitGene(): BelongsTo
    {
        return $this->belongsTo(SplitGene::class);
    }

    public function splitGenes(): BelongsToMany
    {
        return $this->belongsToMany(SplitGene::class, 'bird_split_gene')
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function grandparents(): HasMany
    {
        return $this->hasMany(BirdGrandparent::class);
    }

    public function pairsAsParentOne(): HasMany
    {
        return $this->hasMany(BirdPair::class, 'parent_1_bird_id');
    }

    public function pairsAsParentTwo(): HasMany
    {
        return $this->hasMany(BirdPair::class, 'parent_2_bird_id');
    }
}
