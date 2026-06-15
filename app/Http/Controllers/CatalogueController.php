<?php

namespace App\Http\Controllers;

use App\Enums\ReportType;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CatalogueController extends Controller
{
    public function ppr(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));

        $reports = Report::query()
            ->where('type', ReportType::PPR)
            ->with('provider')
            ->when($search !== '', fn ($q) => $q->whereHas('provider', fn ($p) => $p->where('name', 'like', '%'.$search.'%')))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('catalogue.ppr', ['reports' => $reports, 'search' => $search]);
    }

    public function pmr(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));

        $reports = Report::query()
            ->where('type', ReportType::PMR)
            ->with('market')
            ->when($search !== '', fn ($q) => $q->whereHas('market', fn ($m) => $m->where('name', 'like', '%'.$search.'%')))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('catalogue.pmr', ['reports' => $reports, 'search' => $search]);
    }
}
