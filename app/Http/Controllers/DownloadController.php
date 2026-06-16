<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Services\AccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DownloadController extends Controller
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function show(Request $request, Asset $asset): RedirectResponse
    {
        abort_unless($this->access->canAccess($request->user(), $asset), 403);

        $url = Storage::disk($asset->disk)->temporaryUrl($asset->path, now()->addMinutes(5));

        return redirect()->away($url);
    }
}
