<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InheritanceMode extends Model
{
    protected $fillable = [
        'code',
        'name',
        'chromosome_model',
        'sex_linked',
        'punnett_rule',
        'expression_rule',
        'scientific_basis',
        'scientific_source',
        'verification_status',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sex_linked' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
