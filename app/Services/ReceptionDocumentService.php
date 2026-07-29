<?php

namespace App\Services;

use App\Models\ReceptionDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ReceptionDocumentService
{
    /**
     * @param  array<int, array{file: UploadedFile, type: string, custom_label?: string|null}>  $attachments
     * @return Collection<int, ReceptionDocument>
     */
    public function store(
        array $attachments,
        User $uploader,
        ?int $intakeId = null,
        ?int $receptionId = null,
    ): Collection {
        $documents = collect();
        $storedPaths = [];

        try {
            foreach ($attachments as $attachment) {
                $file = $attachment['file'];
                $path = $file->store('reception-documents/'.now()->format('Y/m'), 'local');
                if (! $path) {
                    throw new RuntimeException('Documentul nu a putut fi salvat.');
                }
                $storedPaths[] = $path;
                $absolutePath = Storage::disk('local')->path($path);

                $documents->push(ReceptionDocument::create([
                    'reception_intake_id' => $intakeId,
                    'supplier_reception_id' => $receptionId,
                    'uploaded_by' => $uploader->id,
                    'document_type' => $attachment['type'],
                    'custom_label' => $attachment['type'] === 'custom'
                        ? trim((string) ($attachment['custom_label'] ?? ''))
                        : null,
                    'original_name' => $file->getClientOriginalName(),
                    'stored_path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'size_bytes' => $file->getSize(),
                    'sha256' => hash_file('sha256', $absolutePath),
                ]));
            }
        } catch (\Throwable $exception) {
            foreach ($storedPaths as $path) {
                Storage::disk('local')->delete($path);
            }

            throw $exception;
        }

        return $documents;
    }

    /** @param iterable<int, ReceptionDocument> $documents */
    public function remove(iterable $documents): void
    {
        foreach ($documents as $document) {
            Storage::disk('local')->delete($document->stored_path);
            $document->delete();
        }
    }
}
