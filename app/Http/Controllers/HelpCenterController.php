<?php

namespace App\Http\Controllers;

use App\Models\HelpArticle;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\View\View;

class HelpCenterController extends Controller
{
    public function index(Request $request): View
    {
        $articles = $this->articles();
        $search = trim((string) $request->query('q'));
        $results = collect();

        if ($search !== '') {
            $results = HelpArticle::published()
                ->where(function ($query) use ($search): void {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('summary', 'like', "%{$search}%")
                        ->orWhere('body_markdown', 'like', "%{$search}%");
                })
                ->orderBy('sort_order')
                ->orderBy('title')
                ->get();
        }

        return view('help.index', $this->viewData($articles, $articles->first(), $search, $results));
    }

    public function show(HelpArticle $helpArticle): View
    {
        abort_unless(
            $helpArticle->status === 'published'
                && $helpArticle->published_at !== null
                && $helpArticle->published_at->isPast(),
            404
        );

        return view('help.index', $this->viewData($this->articles(), $helpArticle));
    }

    private function articles(): Collection
    {
        return HelpArticle::published()
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();
    }

    private function viewData(
        Collection $articles,
        ?HelpArticle $selected,
        string $search = '',
        ?Collection $results = null
    ): array {
        return [
            'articles' => $articles,
            'selectedArticle' => $selected,
            'articleBody' => $selected ? $this->markdown($selected->body_markdown) : null,
            'search' => $search,
            'searchResults' => $results ?? collect(),
            'sectionLabels' => [
                'start' => 'Începe de aici',
                'workflows' => 'Fluxuri',
                'roles' => 'Roluri',
                'reference' => 'Referințe',
            ],
        ];
    }

    private function markdown(string $body): HtmlString
    {
        return new HtmlString(Str::markdown($body, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]));
    }
}
