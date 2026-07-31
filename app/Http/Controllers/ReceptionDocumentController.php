<?php

namespace App\Http\Controllers;

use App\Models\ReceptionDocument;
use App\Services\ReceptionAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReceptionDocumentController extends Controller
{
    public function __invoke(
        Request $request,
        ReceptionDocument $receptionDocument,
        ReceptionAccessService $access,
    ): StreamedResponse {
        $receptionDocument->loadMissing(['intake', 'reception']);
        abort_unless($access->canViewDocument($request->user(), $receptionDocument), 403);
        abort_unless(Storage::disk('local')->exists($receptionDocument->stored_path), 404);

        return Storage::disk('local')->download(
            $receptionDocument->stored_path,
            $receptionDocument->original_name,
        );
    }

    public function preview(
        Request $request,
        ReceptionDocument $receptionDocument,
        ReceptionAccessService $access,
    ): StreamedResponse {
        $receptionDocument->loadMissing(['intake', 'reception']);
        abort_unless($access->canViewDocument($request->user(), $receptionDocument), 403);
        abort_unless($receptionDocument->isPreviewable(), 415);
        abort_unless(Storage::disk('local')->exists($receptionDocument->stored_path), 404);

        return Storage::disk('local')->response(
            $receptionDocument->stored_path,
            $receptionDocument->original_name,
            [
                'Cache-Control' => 'private, max-age=300',
                'Content-Type' => $receptionDocument->mime_type,
                'Content-Security-Policy' => "frame-ancestors 'self'",
                'X-Content-Type-Options' => 'nosniff',
                'X-Frame-Options' => 'SAMEORIGIN',
            ],
        );
    }
}
