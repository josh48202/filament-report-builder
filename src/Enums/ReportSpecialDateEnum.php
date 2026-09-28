<?php

namespace Wjbecker\FilamentReportBuilder\Enums;

use Carbon\Carbon;
use Filament\Support\Contracts\HasLabel;

enum ReportSpecialDateEnum: string implements HasLabel
{
    case TODAY = 'today';
    case AFTER_TODAY = 'after_today';
    case BEFORE_TODAY = 'before_today';
    case YESTERDAY = 'yesterday';
    case TOMORROW = 'tomorrow';
    case THIS_MONTH = 'this_month';
    case THIS_YEAR = 'this_year';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::TODAY => 'Today',
            self::AFTER_TODAY => 'After Today',
            self::BEFORE_TODAY => 'Before Today',
            self::YESTERDAY => 'Yesterday',
            self::TOMORROW => 'Tomorrow',
            self::THIS_MONTH => 'This Month',
            self::THIS_YEAR => 'This Year',
        };
    }

    public function getCarbonDates(): Carbon|array|null
    {
        return match ($this) {
            self::TODAY, self::AFTER_TODAY, self::BEFORE_TODAY => Carbon::now(),
            self::YESTERDAY => Carbon::now()->subDay(),
            self::TOMORROW => Carbon::now()->addDay(),
            self::THIS_MONTH => [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()],
            self::THIS_YEAR => [Carbon::now()->startOfYear(), Carbon::now()->endOfYear()],
            default => null,
        };
    }

    public function getCondition(): string
    {
        return match ($this) {
            self::TODAY, self::YESTERDAY, self::TOMORROW => 'equal',
            self::THIS_MONTH, self::THIS_YEAR => 'between',
            self::AFTER_TODAY => 'greater',
            self::BEFORE_TODAY => 'less',
        };
    }
}
