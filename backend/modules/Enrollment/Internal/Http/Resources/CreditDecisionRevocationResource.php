<?php

namespace Modules\Enrollment\Internal\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Enrollment\Internal\Models\CreditDecisionRevocation;

/** @mixin CreditDecisionRevocation */
class CreditDecisionRevocationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'revoked_by_user_id' => $this->revoked_by_user_id,
            'reason' => $this->reason,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
