<?php

namespace App\Http\Requests\Api;

use App\Models\Bird;
use App\Support\SplitGeneCatalog;
use App\Support\VisualMutationCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreBirdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return $this->birdRules();
    }

    /**
     * @return array<string, mixed>
     */
    protected function birdRules(?int $birdId = null): array
    {
        $grandparentRules = [];

        foreach (Bird::GRANDPARENT_ROLES as $role) {
            $grandparentRules["grandparents.{$role}"] = ['nullable', 'array'];
            $grandparentRules["grandparents.{$role}.species_id"] = [
                'nullable',
                'integer',
                'exists:lovebird_species,id',
            ];
            $grandparentRules["grandparents.{$role}.base_color_id"] = [
                'nullable',
                'integer',
                'exists:base_colors,id',
            ];
            $grandparentRules["grandparents.{$role}.visual_mutation_id"] = [
                'nullable',
                'integer',
                'exists:visual_mutations,id',
            ];
            $grandparentRules["grandparents.{$role}.visual_mutation_ids"] = ['nullable', 'array'];
            $grandparentRules["grandparents.{$role}.visual_mutation_ids.*"] = [
                'integer',
                'distinct',
                'exists:visual_mutations,id',
            ];
            $grandparentRules["grandparents.{$role}.split_gene_id"] = [
                'nullable',
                'integer',
                'exists:split_genes,id',
            ];
            $grandparentRules["grandparents.{$role}.split_gene_ids"] = ['nullable', 'array'];
            $grandparentRules["grandparents.{$role}.split_gene_ids.*"] = [
                'integer',
                'distinct',
                'exists:split_genes,id',
            ];
        }

        return array_merge([
            'bird_id' => [
                'required',
                'string',
                'max:80',
                Rule::unique('birds', 'bird_id')->ignore($birdId),
            ],
            'age_months' => ['required', 'integer', 'min:0', 'max:600'],
            'species_id' => ['required', 'integer', 'exists:lovebird_species,id'],
            'sex' => ['required', 'string', Rule::in([Bird::SEX_HEN, Bird::SEX_COCK])],
            'base_color_id' => ['nullable', 'integer', 'exists:base_colors,id'],
            'visual_mutation_id' => ['nullable', 'integer', 'exists:visual_mutations,id'],
            'visual_mutation_ids' => ['nullable', 'array'],
            'visual_mutation_ids.*' => ['integer', 'distinct', 'exists:visual_mutations,id'],
            'split_gene_id' => ['nullable', 'integer', 'exists:split_genes,id'],
            'split_gene_ids' => ['nullable', 'array'],
            'split_gene_ids.*' => ['integer', 'distinct', 'exists:split_genes,id'],
            'grandparents' => ['nullable', 'array'],
        ], $grandparentRules);
    }

    protected function prepareForValidation(): void
    {
        $nullableIds = [
            'base_color_id',
            'visual_mutation_id',
            'split_gene_id',
        ];

        $payload = [];

        foreach ($nullableIds as $field) {
            if ($this->has($field) && $this->input($field) === '') {
                $payload[$field] = null;
            }
        }

        if ($this->has('sex') && is_string($this->input('sex'))) {
            $payload['sex'] = strtolower(trim((string) $this->input('sex')));
        }

        if ($this->has('grandparents') && is_array($this->input('grandparents'))) {
            $grandparents = $this->input('grandparents');

            foreach (Bird::GRANDPARENT_ROLES as $role) {
                if (! isset($grandparents[$role]) || ! is_array($grandparents[$role])) {
                    continue;
                }

                foreach (['species_id', 'base_color_id', 'visual_mutation_id', 'split_gene_id'] as $field) {
                    if (array_key_exists($field, $grandparents[$role]) && $grandparents[$role][$field] === '') {
                        $grandparents[$role][$field] = null;
                    }
                }
            }

            $payload['grandparents'] = $grandparents;
        }

        if ($this->has('visual_mutation_ids') && is_array($this->input('visual_mutation_ids'))) {
            $payload['visual_mutation_ids'] = $this->normalizeIdList($this->input('visual_mutation_ids'));
        } elseif ($this->filled('visual_mutation_id')) {
            $payload['visual_mutation_ids'] = [(int) $this->input('visual_mutation_id')];
        }

        if ($this->has('split_gene_ids') && is_array($this->input('split_gene_ids'))) {
            $payload['split_gene_ids'] = $this->normalizeIdList($this->input('split_gene_ids'));
        } elseif ($this->filled('split_gene_id')) {
            $payload['split_gene_ids'] = [(int) $this->input('split_gene_id')];
        }

        if (isset($payload['grandparents']) && is_array($payload['grandparents'])) {
            $grandparents = $payload['grandparents'];
        } elseif ($this->has('grandparents') && is_array($this->input('grandparents'))) {
            $grandparents = $this->input('grandparents');
        } else {
            $grandparents = null;
        }

        if (is_array($grandparents)) {
            foreach (Bird::GRANDPARENT_ROLES as $role) {
                if (! isset($grandparents[$role]) || ! is_array($grandparents[$role])) {
                    continue;
                }

                if (array_key_exists('visual_mutation_ids', $grandparents[$role])) {
                    $grandparents[$role]['visual_mutation_ids'] = $this->normalizeIdList(
                        $grandparents[$role]['visual_mutation_ids']
                    );
                } elseif (! empty($grandparents[$role]['visual_mutation_id'])) {
                    $grandparents[$role]['visual_mutation_ids'] = [(int) $grandparents[$role]['visual_mutation_id']];
                }

                if (array_key_exists('split_gene_ids', $grandparents[$role])) {
                    $grandparents[$role]['split_gene_ids'] = $this->normalizeIdList(
                        $grandparents[$role]['split_gene_ids']
                    );
                } elseif (! empty($grandparents[$role]['split_gene_id'])) {
                    $grandparents[$role]['split_gene_ids'] = [(int) $grandparents[$role]['split_gene_id']];
                }
            }

            $payload['grandparents'] = $grandparents;
        }

        if ($payload !== []) {
            $this->merge($payload);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->rejectInvalidVisualMutations(
                $validator,
                $this->input('species_id'),
                $this->input('visual_mutation_ids', []),
                'visual_mutation_ids',
            );
            $this->rejectInvalidSplitGenes(
                $validator,
                $this->input('species_id'),
                $this->input('split_gene_ids', []),
                'split_gene_ids',
                is_string($this->input('sex')) ? $this->input('sex') : null,
            );

            $grandparents = $this->input('grandparents', []);
            if (! is_array($grandparents)) {
                return;
            }

            foreach (Bird::GRANDPARENT_ROLES as $role) {
                $record = $grandparents[$role] ?? null;
                if (! is_array($record)) {
                    continue;
                }

                $this->rejectInvalidVisualMutations(
                    $validator,
                    $record['species_id'] ?? null,
                    $record['visual_mutation_ids'] ?? [],
                    "grandparents.{$role}.visual_mutation_ids",
                );
                $this->rejectInvalidSplitGenes(
                    $validator,
                    $record['species_id'] ?? null,
                    $record['split_gene_ids'] ?? [],
                    "grandparents.{$role}.split_gene_ids",
                    $this->sexForGrandparentRole($role),
                );
            }
        });
    }

    /**
     * @param  mixed  $ids
     * @return list<int>
     */
    private function normalizeIdList(mixed $ids): array
    {
        if (! is_array($ids)) {
            return [];
        }

        $normalized = [];
        foreach ($ids as $id) {
            if ($id === null || $id === '') {
                continue;
            }
            $normalized[] = (int) $id;
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param  mixed  $speciesId
     * @param  mixed  $ids
     */
    private function rejectInvalidVisualMutations(
        Validator $validator,
        mixed $speciesId,
        mixed $ids,
        string $field,
    ): void {
        $ids = $this->normalizeIdList($ids);
        if ($ids === []) {
            return;
        }

        if ($speciesId === null || $speciesId === '') {
            $validator->errors()->add($field, 'Select a species before choosing visual mutations.');

            return;
        }

        $message = VisualMutationCatalog::incompatibleMessage($ids, (int) $speciesId);
        if ($message !== null) {
            $validator->errors()->add($field, $message);
        }
    }

    /**
     * @param  mixed  $speciesId
     * @param  mixed  $ids
     */
    private function rejectInvalidSplitGenes(
        Validator $validator,
        mixed $speciesId,
        mixed $ids,
        string $field,
        ?string $sex = null,
    ): void {
        $ids = $this->normalizeIdList($ids);
        if ($ids === []) {
            return;
        }

        if ($speciesId === null || $speciesId === '') {
            $validator->errors()->add($field, 'Select a species before choosing split/hidden genes.');

            return;
        }

        $message = SplitGeneCatalog::incompatibleMessage($ids, (int) $speciesId, $sex);
        if ($message !== null) {
            $validator->errors()->add($field, $message);
        }
    }

    private function sexForGrandparentRole(string $role): ?string
    {
        if (str_contains($role, 'grandmother')) {
            return Bird::SEX_HEN;
        }

        if (str_contains($role, 'grandfather')) {
            return Bird::SEX_COCK;
        }

        return null;
    }
}
