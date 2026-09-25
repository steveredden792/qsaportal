<?php

namespace App\Console\Commands;

use App\Models\Charity;
use App\Models\Entitlement;
use App\Models\Issue;
use App\Models\Order;
use App\Models\OrderItem;
use App\Support\PirIndexFile;
use Illuminate\Console\Command;

class PurgeUnindexedCharities extends Command
{
    protected $signature = 'catalogue:purge-unindexed {path : Path to the authoritative PIR index CSV/XLSX} {--force : Actually delete; without this the command only reports what would be removed} {--with-orders : Also delete order items and entitlements that reference these charities\' reports (orders left empty are removed too)}';

    protected $description = 'Remove charities (and their PIR reports, issues and assets via cascade) whose CC ref is not present in the given PIR index — e.g. leftover demo/seeder data.';

    public function handle(): int
    {
        $path = (string) $this->argument('path');

        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $indexed = [];
        foreach (PirIndexFile::read($path) as $row) {
            $indexed[] = trim((string) ($row['cc_ref'] ?? ''));
        }
        $indexed = array_values(array_unique(array_filter($indexed)));

        $this->info(count($indexed).' CC refs in index; '.Charity::count().' charities in database.');

        $orphans = Charity::whereNotIn('cc_ref', $indexed)->orderBy('name')->get(['id', 'cc_ref', 'name']);

        if ($orphans->isEmpty()) {
            $this->info('Nothing to remove — every charity is in the index.');

            return self::SUCCESS;
        }

        $this->table(['ID', 'CC ref', 'Name'], $orphans->map(fn ($c) => [$c->id, $c->cc_ref, $c->name])->all());

        if (! $this->option('force')) {
            $this->warn($orphans->count().' charities would be removed. Re-run with --force to delete them.');

            return self::SUCCESS;
        }

        $issueIds = Issue::whereHas('report', fn ($q) => $q->whereIn('charity_id', $orphans->pluck('id')))->pluck('id');
        $orderItems = OrderItem::whereIn('issue_id', $issueIds)->get();
        $entitlements = Entitlement::whereIn('issue_id', $issueIds)->orWhereIn('order_item_id', $orderItems->pluck('id'))->get();

        if ($orderItems->isNotEmpty() || $entitlements->isNotEmpty()) {
            if (! $this->option('with-orders')) {
                $this->error("{$orderItems->count()} order item(s) and {$entitlements->count()} entitlement(s) reference these charities' reports, so they cannot be deleted (foreign key constraint).");
                $this->warn('Re-run with --force --with-orders to remove those purchase records as well.');

                return self::FAILURE;
            }

            $orderIds = $orderItems->pluck('order_id')->unique();
            Entitlement::whereIn('id', $entitlements->pluck('id'))->delete();
            OrderItem::whereIn('id', $orderItems->pluck('id'))->delete();
            $emptyOrders = Order::whereIn('id', $orderIds)->doesntHave('items')->get();
            $emptyOrders->each->delete();

            $this->warn("Removed {$entitlements->count()} entitlement(s), {$orderItems->count()} order item(s) and {$emptyOrders->count()} now-empty order(s).");
        }

        $deleted = Charity::whereIn('id', $orphans->pluck('id'))->get()->each->delete()->count();

        $this->info("Deleted {$deleted} charities. ".Charity::count().' remain.');

        return self::SUCCESS;
    }
}
