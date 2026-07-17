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

    protected $fillable = ['type', 'charity_id', 'provider_id', 'name', 'slug', 'tier'];

    protected function casts(): array
    {
        return ['type' => ReportType::class];
    }

    protected static function booted(): void
    {
        static::saving(function (Report $report): void {
            $type = $report->type instanceof ReportType ? $report->type : ReportType::from($report->type);
            $required = match ($type) {
                ReportType::PIR => 'charity_id',
                ReportType::FAR => 'provider_id',
            };

            foreach (['charity_id', 'provider_id'] as $column) {
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

    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }

    public function currentIssue(): HasOne
    {
        return $this->hasOne(Issue::class)->where('is_current', true);
    }

    public function subject(): Charity|Provider|null
    {
        return match ($this->type) {
            ReportType::PIR => $this->charity,
            ReportType::FAR => $this->provider,
        };
    }
}
