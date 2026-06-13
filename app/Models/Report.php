<?php

namespace App\Models;

use App\Enums\ReportType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use InvalidArgumentException;

class Report extends Model
{
    use HasFactory;

    protected $fillable = ['type', 'charity_id', 'provider_id', 'market_id', 'name', 'slug'];

    protected function casts(): array
    {
        return ['type' => ReportType::class];
    }

    protected static function booted(): void
    {
        static::saving(function (Report $report): void {
            $type = $report->type instanceof ReportType ? $report->type : ReportType::from($report->type);
            $required = match ($type) {
                ReportType::FAR => 'charity_id',
                ReportType::PPR => 'provider_id',
                ReportType::PMR => 'market_id',
            };

            foreach (['charity_id', 'provider_id', 'market_id'] as $column) {
                if ($column === $required && empty($report->{$column})) {
                    throw new InvalidArgumentException("A {$type->value} report requires {$column}.");
                }
                if ($column !== $required && ! empty($report->{$column})) {
                    throw new InvalidArgumentException("A {$type->value} report must not set {$column}.");
                }
            }
        });
    }

    public function charity(): BelongsTo
    {
        return $this->belongsTo(Charity::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }

    public function currentIssue(): HasOne
    {
        return $this->hasOne(Issue::class)->where('is_current', true);
    }

    public function subject(): Charity|Provider|Market|null
    {
        return match ($this->type) {
            ReportType::FAR => $this->charity,
            ReportType::PPR => $this->provider,
            ReportType::PMR => $this->market,
        };
    }
}
