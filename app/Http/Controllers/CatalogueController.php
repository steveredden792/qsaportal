<?php

namespace App\Http\Controllers;

use App\Enums\ReportType;
use App\Models\Report;
use Illuminate\View\View;

class CatalogueController extends Controller
{
    public function ppr(): View
    {
        $reports = Report::query()
            ->where('type', ReportType::PPR)
            ->with('provider')
            ->orderBy('name')
            ->paginate(20);

        return view('catalogue.ppr', ['reports' => $reports]);
    }

    public function pmr(): View
    {
        $reports = Report::query()
            ->where('type', ReportType::PMR)
            ->with('market')
            ->orderBy('name')
            ->paginate(20);

        return view('catalogue.pmr', ['reports' => $reports]);
    }
}
