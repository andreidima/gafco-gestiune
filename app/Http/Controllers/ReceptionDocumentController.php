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
}
