<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BirdGrandparent extends Model
{
    protected $fillable = [
        'bird_id',
        'role',
        'species_id',
        'base_color_id',
        'visual_mutation_id',
        'split_gene_id',
    ];

    protected function casts(): array
    {
        return [
            'bird_id' => 'integer',
            'species_id' => 'integer',
            'base_color_id' => 'integer',
            'visual_mutation_id' => 'integer',
            'split_gene_id' => 'integer',
        ];
    }

    public function bird(): BelongsTo
    {
        return $this->belongsTo(Bird::class);
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
        return $this->belongsToMany(VisualMutation::class, 'bird_grandparent_visual_mutation')
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function splitGene(): BelongsTo
    {
        return $this->belongsTo(SplitGene::class);
    }

    public function splitGenes(): BelongsToMany
    {
        return $this->belongsToMany(SplitGene::class, 'bird_grandparent_split_gene')
            ->orderBy('sort_order')
            ->orderBy('name');
    }
}
