<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Admissions\Internal\Enums\DocumentType;
use Modules\Admissions\Internal\Models\Application;
use Modules\Admissions\Internal\Models\ApplicationDocument;

/**
 * @extends Factory<ApplicationDocument>
 */
class ApplicationDocumentFactory extends Factory
{
    /**
     * The relocated model (Modules\Admissions\Internal\Models\ApplicationDocument).
     * Set explicitly because the default resolver would guess App\Models.
     *
     * @var class-string<ApplicationDocument>
     */
    protected $model = ApplicationDocument::class;

    /**
     * A stored graduation-certificate PDF by default. The `stored_path` points at a
     * plausible location on the private disk; tests that stream the file back use
     * {@see Storage::fake()} and put real bytes there.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'application_id' => Application::factory(),
            'document_type' => DocumentType::GraduationCertificate,
            'ulid' => (string) Str::ulid(),
            'original_filename' => 'graduation-certificate.pdf',
            'stored_path' => 'application-documents/'.fake()->uuid().'.pdf',
            'mime' => 'application/pdf',
            'size_bytes' => 1024,
            'uploaded_at' => now(),
        ];
    }

    /** Fix this document to a given type (e.g. to exercise the per-type rules). */
    public function ofType(DocumentType $type): static
    {
        return $this->state(fn (): array => ['document_type' => $type]);
    }
}
