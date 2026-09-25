<?php

namespace App\Http\Controllers;

use App\Enums\ReportType;
use App\Models\Asset;
use App\Services\AccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class DownloadController extends Controller
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function show(Request $request, Asset $asset): Response
    {
        abort_unless($this->access->canAccess($request->user(), $asset), 403);

        // FARs are financially sensitive and only for professional service providers,
        // so they require a verified email address. PIRs do not.
        $asset->loadMissing('issue.report');
        if ($asset->issue?->report?->type === ReportType::FAR && ! $request->user()->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        $disk = Storage::disk($asset->disk);
        $filename = $asset->original_filename ?: basename($asset->path);

        // Demo-only: if the real object isn't in the bucket yet (e.g. a dev
        // bucket without the published PDFs), serve the bundled sample so the
        // purchase -> download flow still demonstrates. Skipped in production.
        if (config('demo.instant_fulfil') && ! $this->exists($disk, $asset->path)) {
            $sampleDisk = Storage::disk(config('demo.sample_report_disk'));
            $samplePath = (string) config('demo.sample_report_pdf');

            if ($this->exists($sampleDisk, $samplePath)) {
                return $sampleDisk->download($samplePath, $filename);
            }
        }

        // Cloud disks (S3) hand back a short-lived signed URL. Local/dev disks
        // can't presign, so stream the file directly instead.
        try {
            return redirect()->away($disk->temporaryUrl($asset->path, now()->addMinutes(5)));
        } catch (Throwable) {
            abort_unless($this->exists($disk, $asset->path), 404);

            return $disk->download($asset->path, $filename);
        }
    }

    private function exists(\Illuminate\Contracts\Filesystem\Filesystem $disk, string $path): bool
    {
        try {
            return $disk->exists($path);
        } catch (Throwable) {
            return false;
        }
    }
}
