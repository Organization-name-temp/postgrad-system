<?php

namespace Modules\Identity\Internal\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Admissions\Public\AcceptedStudentIdentifications;
use Modules\Identity\Internal\Models\Student;
use Modules\Identity\Public\Contracts\AuditLogger;

final class RecoverStudentIdentification extends Command
{
    protected $signature = 'identity:recover-identification';

    protected $description = 'Recover missing Student Identification Type from matching accepted Applications';

    public function handle(AcceptedStudentIdentifications $evidence, AuditLogger $audit): int
    {
        $recovered = 0;
        $unresolved = 0;
        foreach (Student::query()->whereNull('identification_type')->orderBy('id')->pluck('id') as $id) {
            $result = DB::transaction(function () use ($id, $evidence, $audit): ?bool {
                $student = Student::query()->lockForUpdate()->findOrFail($id);
                if ($student->identification_type !== null) {
                    return null;
                }
                $type = $evidence->typeFor($student->user_id, $student->admission_cycle_id, $student->national_id);
                if (! in_array($type, ['national_id', 'passport'], true)) {
                    return false;
                }
                $student->update(['identification_type' => $type]);
                $audit->record(action: 'student.identification_recovered', actorUserId: null,
                    metadata: ['identification_type' => $type], subjectType: 'student', subjectId: (string) $id);

                return true;
            });
            if ($result === true) {
                $recovered++;
            } elseif ($result === false) {
                $unresolved++;
            }
        }
        $this->info("Recovered: {$recovered}; Unresolved: {$unresolved}");

        return self::SUCCESS;
    }
}
