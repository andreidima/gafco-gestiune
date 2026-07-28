<?php

namespace App\Http\Controllers;

use App\Models\ReleaseNote;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ReleaseNoteController extends Controller
{
    public function index(): View
    {
        return view('release-notes.index', [
            'releaseNotes' => ReleaseNote::published()
                ->orderByDesc('released_at')
                ->orderByDesc('id')
                ->paginate(12),
        ]);
    }

    public function show(ReleaseNote $releaseNote): View
    {
        abort_unless(
            $releaseNote->status === 'published'
                && $releaseNote->published_at !== null
                && $releaseNote->published_at->isPast(),
            404
        );

        return view('release-notes.show', [
            'releaseNote' => $releaseNote,
            'releaseBody' => new HtmlString(Str::markdown($releaseNote->body_markdown, [
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ])),
        ]);
    }
}
