<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BreedingPrediction extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'bird_pair_id',
        'computation_result_id',
        'parent_1_bird_id',
        'parent_2_bird_id',
        'status',
        'parent_1',
        'parent_2',
        'validation',
        'prediction',
    ];

    protected function casts(): array
    {
        return [
            'parent_1' => 'array',
            'parent_2' => 'array',
            'validation' => 'array',
            'prediction' => 'array',
        ];
    }

    public function birdPair(): BelongsTo
    {
        return $this->belongsTo(BirdPair::class);
    }

    public function computationResult(): BelongsTo
    {
        return $this->belongsTo(ComputationResult::class);
    }
}
