<?php

namespace Modules\Admissions\Internal\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Admissions\Internal\Enums\DocumentType;
use Modules\Admissions\Internal\Http\Concerns\ResolvesCurrentApplication;
use Modules\Admissions\Internal\Http\Resources\ApplicationDocumentResource;
use Modules\Admissions\Internal\Models\ApplicationDocument;
use Modules\Admissions\Internal\Support\ApplicationAccess;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * @group Admissions
 *
 * Application documents (ticket 05). The applicant uploads, replaces, and removes
 * the certificates and files that complete their application, each validated
 * against its {@see DocumentType}'s own rules (format, size, cardinality). Files
 * land on the private `local` disk under an opaque ULID (never `public`, ADR-0005)
 * and are streamed back only to the owning applicant or a scoped reviewer.
 * Uploads and removals are allowed only while the Application
 * is still in the applicant's hands (Draft / Submitted / EmailVerified /
 * ReturnedForRevision); once it enters review, the documents lock.
 */
class DocumentController
{
    use ResolvesCurrentApplication;

    /**
     * Upload a document.
     *
     * Validates the file against the chosen {@see DocumentType}'s rules — allowed
     * formats, size ceiling, and how many of that type the application may hold —
     * stores it on the private disk under a fresh ULID, and returns the created
     * document. A bad type, wrong format, oversize file, or one copy too many is a
     * 422; uploading once the application has left the applicant's hands is a 409.
     *
     * @bodyParam document_type string required The kind of document. One of graduation_certificate, transcript, national_id, photo, english_certificate, msc_thesis, cv, recommendation_letter, additional_certificate. Example: graduation_certificate
     * @bodyParam file file required The file to upload (PDF or image, within the type's size limit).
     *
     * @response 201 {"data": {"id": "01ARZ3NDEKTSV4RRFFQ69G5FAV", "document_type": "graduation_certificate", "original_filename": "certificate.pdf", "mime": "application/pdf", "size_bytes": 24576, "uploaded_at": "2026-07-14T10:00:00+00:00", "download_url": "http://localhost/api/v1/documents/01ARZ3NDEKTSV4RRFFQ69G5FAV"}}
     * @response 409 scenario="documents locked" {"message": "Documents can no longer be changed for this application."}
     * @response 422 scenario="over the cap" {"message": "You can upload at most 1 document of this type.", "errors": {"document_type": ["You can upload at most 1 document of this type."]}}
     * @response 422 scenario="bad file" {"message": "The file must be a file of type: application/pdf, image/jpeg, image/png.", "errors": {"file": ["The file must be a file of type: application/pdf, image/jpeg, image/png."]}}
     */
    public function store(Request $request): JsonResponse
    {
        $application = $this->currentApplication($request);

        if (! $application->areDocumentsEditable()) {
            throw new ConflictHttpException(__('admissions.documents.locked'));
        }

        // Resolve the type first, so the file can be validated against that type's
        // own format and size rules rather than a one-size-fits-all set.
        $validated = $request->validate(['document_type' => ['required', Rule::enum(DocumentType::class)]]);
        $type = DocumentType::from($validated['document_type']);

        $request->validate([
            'file' => [
                'required', 'file',
                'mimetypes:'.implode(',', $type->allowedMimes()),
                'max:'.$type->maxSizeKb(),
            ],
        ]);

        // Cardinality: refuse the upload that would exceed this type's cap (BR-68).
        if ($application->documents()->where('document_type', $type)->count() >= $type->maxCount()) {
            throw ValidationException::withMessages([
                'document_type' => [__('admissions.documents.too_many', ['max' => $type->maxCount()])],
            ]);
        }

        $file = $request->file('file');

        // Stored under an opaque name on the private disk; the recorded metadata is
        // what the applicant and reviewers see, never the storage layout.
        $storedPath = $file->store('application-documents/'.$application->getKey(), 'local');

        $document = $application->documents()->create([
            'document_type' => $type,
            'original_filename' => $file->getClientOriginalName(),
            'stored_path' => $storedPath,
            'mime' => $file->getMimeType() ?? $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
        ]);

        return (new ApplicationDocumentResource($document))->response()->setStatusCode(201);
    }

    /**
     * Remove a document.
     *
     * Deletes the file (bytes and metadata) from the signed-in applicant's own
     * application, freeing its slot so a replacement can be uploaded. Allowed only
     * while the application is still applicant-editable; a document that is not the
     * current applicant's is a 404, and a locked application is a 409.
     *
     * @urlParam document_ulid string required The ULID of the document to remove. Example: 01ARZ3NDEKTSV4RRFFQ69G5FAV
     *
     * @response 204 scenario="removed"
     * @response 404 scenario="not the applicant’s document" {"message": "Not found."}
     * @response 409 scenario="documents locked" {"message": "Documents can no longer be changed for this application."}
     */
    public function destroy(Request $request, ApplicationDocument $document): Response
    {
        $application = $this->currentApplication($request);

        // Not this applicant's current application → 404 (don't confirm it exists).
        if ($document->application_id !== $application->getKey()) {
            abort(404);
        }

        if (! $application->areDocumentsEditable()) {
            throw new ConflictHttpException(__('admissions.documents.locked'));
        }

        Storage::disk('local')->delete($document->stored_path);
        $document->delete();

        return response()->noContent();
    }

    /**
     * Stream a document.
     *
     * Serves the stored file inline to the owning applicant or a reviewer authorized
     * for this Application's Faculty scope; anyone else is refused with a 403. The ULID in the path
     * is the only handle — the storage path and row id are never exposed.
     *
     * @urlParam document_ulid string required The ULID of the document to stream. Example: 01ARZ3NDEKTSV4RRFFQ69G5FAV
     *
     * @response 403 scenario="not owner or reviewer" {"message": "This action is unauthorized."}
     *
     * @responseField file The raw document bytes, served with the stored content type.
     */
    public function download(Request $request, ApplicationDocument $document, ApplicationAccess $access): StreamedResponse
    {
        abort_unless($access->reads((int) $request->user()?->getAuthIdentifier(), $document->application), 403);

        return Storage::disk('local')->response(
            $document->stored_path,
            $document->original_filename,
            ['Content-Type' => $document->mime],
        );
    }
}
