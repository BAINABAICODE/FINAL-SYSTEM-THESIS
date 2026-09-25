<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComputationResult extends Model
{
    protected $fillable = [
        'bird_pair_id',
        'prediction_id',
        'status',
        'compatibility_result',
        'genetic_compatibility',
        'overall_compatibility',
        'fertility_result',
        'risk_level',
        'warnings',
        'estimated_clutch_count',
        'offspring_probability',
        'predicted_base_colors',
        'predicted_visual_mutations',
        'predicted_split_genes',
        'predicted_phenotypes',
        'predicted_appearance',
        'predicted_offspring_image',
        'rbgia_data',
        'genetic_calculation',
        'scientific_information',
        'parent_snapshot',
        'offspring_visualizations',
        'result_presentation',
        'ai_interpretation',
    ];

    protected function casts(): array
    {
        return [
            'compatibility_result' => 'array',
            'genetic_compatibility' => 'array',
            'overall_compatibility' => 'array',
            'warnings' => 'array',
            'estimated_clutch_count' => 'integer',
            'offspring_probability' => 'array',
            'predicted_base_colors' => 'array',
            'predicted_visual_mutations' => 'array',
            'predicted_split_genes' => 'array',
            'predicted_phenotypes' => 'array',
            'rbgia_data' => 'array',
            'genetic_calculation' => 'array',
            'scientific_information' => 'array',
            'parent_snapshot' => 'array',
            'offspring_visualizations' => 'array',
            'result_presentation' => 'array',
            'ai_interpretation' => 'array',
        ];
    }

    public function birdPair(): BelongsTo
    {
        return $this->belongsTo(BirdPair::class);
    }

    public function prediction(): BelongsTo
    {
        return $this->belongsTo(BreedingPrediction::class, 'prediction_id');
    }
}
