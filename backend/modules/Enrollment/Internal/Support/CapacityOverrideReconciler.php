<?php

namespace Modules\Enrollment\Internal\Support;

use Illuminate\Support\Facades\DB;
use Modules\Enrollment\Internal\Enums\RegistrationStatus;
use Modules\Enrollment\Internal\Models\RegistrationRequest;

final readonly class CapacityOverrideReconciler
{
    public function __construct(private CapacityOverrideFinalizer $finalizer) {}

    public function reconcileOffering(int $offeringId): void
    {
        RegistrationRequest::query()
            ->where('status', RegistrationStatus::AwaitingCapacityOverride)
            ->whereHas('lines', fn ($query) => $query->where('course_offering_id', $offeringId))
            ->orderBy('id')
            ->pluck('id')
            ->each(fn (int $requestId) => $this->reconcileRequest($requestId));
    }

    public function reconcileAll(): void
    {
        RegistrationRequest::query()
            ->where('status', RegistrationStatus::AwaitingCapacityOverride)
            ->orderBy('id')
            ->pluck('id')
            ->each(fn (int $requestId) => $this->reconcileRequest($requestId));
    }

    private function reconcileRequest(int $requestId): void
    {
        DB::transaction(function () use ($requestId): void {
            $request = RegistrationRequest::query()->lockForUpdate()->find($requestId);
            if ($request?->status === RegistrationStatus::AwaitingCapacityOverride) {
                $this->finalizer->finalizeIfReady($request);
            }
        });
    }
}
