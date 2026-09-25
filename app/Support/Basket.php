<?php

namespace App\Support;

use App\Models\BasketItem;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * Basket that works for guests as well as signed-in users.
 *
 * Guests keep their chosen report IDs in the session; signed-in users use
 * basket_items rows. When a guest signs in or registers, the session basket
 * is merged into their account (see AppServiceProvider).
 */
class Basket
{
    private const SESSION_KEY = 'basket.report_ids';

    public static function add(Report $report): void
    {
        if (Auth::check()) {
            BasketItem::firstOrCreate(['user_id' => Auth::id(), 'report_id' => $report->id]);

            return;
        }

        $ids = self::sessionIds();
        $ids[] = $report->id;
        Session::put(self::SESSION_KEY, array_values(array_unique($ids)));
    }

    public static function removeReport(int $reportId): void
    {
        if (Auth::check()) {
            BasketItem::where('user_id', Auth::id())->where('report_id', $reportId)->delete();

            return;
        }

        Session::put(self::SESSION_KEY, array_values(array_diff(self::sessionIds(), [$reportId])));
    }

    public static function contains(int $reportId): bool
    {
        return in_array($reportId, self::reportIds(), true);
    }

    public static function count(): int
    {
        return count(self::reportIds());
    }

    /** @return array<int, int> */
    public static function reportIds(): array
    {
        if (Auth::check()) {
            return BasketItem::where('user_id', Auth::id())->pluck('report_id')->map(fn ($id) => (int) $id)->all();
        }

        return self::sessionIds();
    }

    /** Reports in the basket, newest first. @return Collection<int, Report> */
    public static function reports(): Collection
    {
        if (Auth::check()) {
            return BasketItem::with('report')
                ->where('user_id', Auth::id())
                ->latest()
                ->get()
                ->pluck('report')
                ->filter()
                ->values();
        }

        $ids = array_reverse(self::sessionIds());

        return Report::whereIn('id', $ids)->get()->sortBy(fn (Report $r) => array_search($r->id, $ids, true))->values();
    }

    /** Move a guest's session basket into the given user's account basket. */
    public static function mergeIntoUser(User $user): void
    {
        foreach (self::sessionIds() as $reportId) {
            BasketItem::firstOrCreate(['user_id' => $user->id, 'report_id' => $reportId]);
        }

        Session::forget(self::SESSION_KEY);
    }

    /** @return array<int, int> */
    private static function sessionIds(): array
    {
        return array_map('intval', (array) Session::get(self::SESSION_KEY, []));
    }
}
