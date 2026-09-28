<?php

namespace Wjbecker\FilamentReportBuilder\Support;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Wjbecker\FilamentReportBuilder\Enums\ReportConditionsEnum;
use Wjbecker\FilamentReportBuilder\Enums\ReportSpecialDateEnum;

class ReportQueryBuilder
{
    public function __construct(private $report)
    {
    }

    public function query()
    {
        $query = app($this->report->data['source'])::query();

        if (data_get($this->report->data, 'with')) {
            $query->with($this->report->data['with']);
        }

        collect(data_get($this->report->data, 'filter_groups', []))->each(function ($group) use ($query) {
            $query->where(function ($query) use ($group) {
                foreach ($group['filters'] as $filter) {
                    $this->applyFilter($query, $filter);
                }
            });
        });

        if (data_get($this->report->data, 'with_trashed', false)) {
            $query->withTrashed();
        }

        return $query;
    }

    private function applyFilter(Builder $query, $filter): void
    {
        $attribute = json_decode($filter['attribute']);
        $value = $this->resolveFilterValue($filter);

        $query->when(isset($attribute->name), function (Builder $query) use ($filter, $attribute, $value) {
            $query->whereRelation($attribute->name, fn (Builder $query) => $this->filterQuery($query, $filter, $value));
        }, fn (Builder $query) => $this->filterQuery($query, $filter, $value));
    }

    private function filterQuery(Builder $query, $filter, $value = ''): Builder
    {
        $attribute = json_decode($filter['attribute']);
        $condition = ReportConditionsEnum::from($filter['condition']);

        return match ($condition) {
            ReportConditionsEnum::IS_NULL => $query->whereNull($attribute->item),
            ReportConditionsEnum::IS_NOT_NULL => $query->whereNotNull($attribute->item),
            ReportConditionsEnum::SPECIAL_DATE => $this->applySpecialDateCondition($query, $filter, $attribute),
            ReportConditionsEnum::BETWEEN, ReportConditionsEnum::NOT_BETWEEN => $this->applyBetweenCondition($query, $filter, $attribute, $condition),
            default => $this->applyStandardCondition($query, $attribute, $condition, $value),
        };
    }

    private function applySpecialDateCondition(Builder $query, $filter, $attribute): Builder
    {
        $reportSpecialDate = ReportSpecialDateEnum::from($filter['special']);
        $values = $reportSpecialDate->getCarbonDates();
        $condition = ReportConditionsEnum::from($reportSpecialDate->getCondition());

        if (is_array($values)) {
            [$value, $value2] = $values;
        } else {
            $value = $values;
        }

        // Adjust value for datetime fields
        if ($attribute->cast === 'datetime') {
            $value = Carbon::make($value)->startOfDay();
            $value2 = isset($filter['value2']) ? Carbon::make($filter['value2'])->endOfDay() : null;
        }

        return $query->whereDate($attribute->item, $condition->getOperator(), $value ?? null);
    }

    private function applyBetweenCondition(Builder $query, $filter, $attribute, $condition): Builder
    {
        $not = $condition === ReportConditionsEnum::NOT_BETWEEN;

        if ($attribute->cast === 'date' || $attribute->cast === 'datetime') {
            $from = Carbon::make($filter['value'])->toDateString();
            $to = Carbon::make($filter['value2'])->toDateString();

            // Compare on the date part so the end date is inclusive for datetime columns
            return $not
                ? $query->where(fn (Builder $query) => $query
                    ->whereDate($attribute->item, '<', $from)
                    ->orWhereDate($attribute->item, '>', $to))
                : $query->whereDate($attribute->item, '>=', $from)
                    ->whereDate($attribute->item, '<=', $to);
        }

        return $query->whereBetween($attribute->item, [$filter['value'], $filter['value2']], 'and', $not);
    }

    private function applyStandardCondition(Builder $query, $attribute, $condition, $value): Builder
    {
        if ($attribute->cast === 'date' || $attribute->cast === 'datetime') {
            $value = Carbon::make($value);

            return $query->whereDate($attribute->item, $condition->getOperator(), $value);
        }

        return $query->where($attribute->item, $condition->getOperator(), $value);
    }

    private function resolveFilterValue($filter)
    {
        $condition = ReportConditionsEnum::from($filter['condition']);
        $value = $filter['value'];

        return match ($condition) {
            ReportConditionsEnum::BEGINS_WITH, ReportConditionsEnum::NOT_BEGINS_WITH => $value . '%',
            ReportConditionsEnum::ENDS_WITH, ReportConditionsEnum::NOT_ENDS_WITH => '%' . $value,
            ReportConditionsEnum::CONTAINS, ReportConditionsEnum::NOT_CONTAINS => '%' . $value . '%',
            ReportConditionsEnum::IS_NOT_NULL, ReportConditionsEnum::IS_NULL => null,
            default => $value,
        };
    }
}
