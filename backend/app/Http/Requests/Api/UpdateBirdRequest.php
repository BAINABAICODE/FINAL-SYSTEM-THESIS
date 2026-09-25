<?php

namespace App\Http\Requests\Api;

class UpdateBirdRequest extends StoreBirdRequest
{
    public function rules(): array
    {
        /** @var int|null $birdId */
        $birdId = $this->route('bird')?->id;

        return $this->birdRules($birdId);
    }
}
