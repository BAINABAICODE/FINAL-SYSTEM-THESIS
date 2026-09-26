<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;

/**
 * Cache of Hugging Face chick visualizations keyed by genetic signature.
 * Does not store or alter RBGIA genetic calculations.
 */
class ChickOutcomeImage extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'signature',
        'egg_number',
        'species',
        'sex',
        'base_color',
        'visual_mutations',
        'split_genes',
        'prompt',
        'image_path',
        'image_url',
        'provider',
        'model',
        'hf_provider',
        'status',
        'error',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'visual_mutations' => 'array',
            'split_genes' => 'array',
            'generated_at' => 'datetime',
        ];
    }
}
