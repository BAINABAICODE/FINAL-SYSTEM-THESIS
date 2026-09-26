<?php

namespace App\Http\Requests\Api;

use App\Models\Bird;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AnalyzeBreedingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(
            $this->parentRules('parent_1'),
            $this->parentRules('parent_2'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function parentRules(string $key): array
    {
        $rules = [
            $key => ['required', 'array'],
            "{$key}.source_bird_id" => [
                'nullable',
                'integer',
                Rule::exists('birds', 'id')->where(fn ($query) => $query->where('user_id', $this->user()?->id)),
            ],
            "{$key}.bird_id" => ['required', 'string', 'max:80'],
            "{$key}.species_id" => ['required', 'integer', 'exists:lovebird_species,id'],
            "{$key}.sex" => ['required', 'string', Rule::in([Bird::SEX_HEN, Bird::SEX_COCK])],
            "{$key}.age_months" => ['required', 'integer', 'min:0', 'max:600'],
            "{$key}.base_color_id" => ['nullable', 'integer', 'exists:base_colors,id'],
            "{$key}.visual_mutation_ids" => ['nullable', 'array'],
            "{$key}.visual_mutation_ids.*" => ['integer', 'distinct', 'exists:visual_mutations,id'],
            "{$key}.split_gene_ids" => ['nullable', 'array'],
            "{$key}.split_gene_ids.*" => ['integer', 'distinct', 'exists:split_genes,id'],
            "{$key}.grandparents" => ['nullable', 'array'],
        ];

        foreach (Bird::GRANDPARENT_ROLES as $role) {
            $rules["{$key}.grandparents.{$role}"] = ['nullable', 'array'];
            $rules["{$key}.grandparents.{$role}.species_id"] = ['nullable', 'integer', 'exists:lovebird_species,id'];
            $rules["{$key}.grandparents.{$role}.base_color_id"] = ['nullable', 'integer', 'exists:base_colors,id'];
            $rules["{$key}.grandparents.{$role}.visual_mutation_ids"] = ['nullable', 'array'];
            $rules["{$key}.grandparents.{$role}.visual_mutation_ids.*"] = ['integer', 'exists:visual_mutations,id'];
            $rules["{$key}.grandparents.{$role}.split_gene_id"] = ['nullable', 'integer', 'exists:split_genes,id'];
            $rules["{$key}.grandparents.{$role}.split_gene_ids"] = ['nullable', 'array'];
            $rules["{$key}.grandparents.{$role}.split_gene_ids.*"] = ['integer', 'exists:split_genes,id'];
        }

        return $rules;
    }
}
