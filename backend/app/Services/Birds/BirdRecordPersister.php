<?php

namespace App\Services\Birds;

use App\Models\Bird;
use App\Models\BirdGrandparent;

class BirdRecordPersister
{
    /**
     * Save a breeding-form parent without creating a second record for a selected or existing Bird ID.
     *
     * @param  array<string, mixed>  $payload
     */
    public function persist(array $payload): Bird
    {
        $code = trim((string) ($payload['bird_id'] ?? ''));
        $sourceId = ! empty($payload['source_bird_id']) ? (int) $payload['source_bird_id'] : null;

        $bird = null;
        if ($sourceId) {
            $bird = Bird::query()->find($sourceId);
        }

        if (! $bird && $code !== '') {
            $bird = Bird::query()->where('bird_id', $code)->first();
        }

        if (! $bird) {
            $bird = new Bird();
        }

        $visualMutationIds = $this->idList($payload['visual_mutation_ids'] ?? []);
        $splitGeneIds = $this->idList($payload['split_gene_ids'] ?? []);

        $bird->fill([
            'bird_id' => $code,
            'age_months' => $payload['age_months'],
            'species_id' => $payload['species_id'],
            'sex' => $payload['sex'],
            'base_color_id' => $payload['base_color_id'] ?? null,
            'visual_mutation_id' => $visualMutationIds[0] ?? null,
            'split_gene_id' => $splitGeneIds[0] ?? null,
        ]);
        $bird->save();
        $bird->visualMutations()->sync($visualMutationIds);
        $bird->splitGenes()->sync($splitGeneIds);
        $this->syncGrandparents($bird, is_array($payload['grandparents'] ?? null) ? $payload['grandparents'] : []);

        return $bird->fresh([
            'species',
            'baseColor',
            'visualMutations',
            'splitGenes',
            'grandparents.species',
            'grandparents.baseColor',
            'grandparents.visualMutations',
            'grandparents.splitGenes',
        ]);
    }

    /**
     * @param  array<string, mixed>  $grandparents
     */
    private function syncGrandparents(Bird $bird, array $grandparents): void
    {
        foreach (Bird::GRANDPARENT_ROLES as $role) {
            $payload = is_array($grandparents[$role] ?? null) ? $grandparents[$role] : [];
            $visualMutationIds = $this->idList($payload['visual_mutation_ids'] ?? []);
            $splitGeneIds = $this->idList($payload['split_gene_ids'] ?? []);
            if ($splitGeneIds === [] && ! empty($payload['split_gene_id'])) {
                $splitGeneIds = [(int) $payload['split_gene_id']];
            }

            $fields = [
                'species_id' => $payload['species_id'] ?? null,
                'base_color_id' => $payload['base_color_id'] ?? null,
                'visual_mutation_id' => $visualMutationIds[0] ?? null,
                'split_gene_id' => $splitGeneIds[0] ?? null,
            ];

            $hasData = collect($fields)->contains(fn ($value) => $value !== null && $value !== '')
                || $visualMutationIds !== []
                || $splitGeneIds !== [];

            if (! $hasData) {
                BirdGrandparent::query()
                    ->where('bird_id', $bird->id)
                    ->where('role', $role)
                    ->delete();

                continue;
            }

            $record = BirdGrandparent::query()->updateOrCreate(
                [
                    'bird_id' => $bird->id,
                    'role' => $role,
                ],
                $fields,
            );
            $record->visualMutations()->sync($visualMutationIds);
            $record->splitGenes()->sync($splitGeneIds);
        }
    }

    /**
     * @param  mixed  $ids
     * @return list<int>
     */
    private function idList(mixed $ids): array
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
}
