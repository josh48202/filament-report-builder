<?php

namespace Wjbecker\FilamentReportBuilder\Resources;

use Wjbecker\FilamentReportBuilder\Enums\ReportConditionsEnum;
use Wjbecker\FilamentReportBuilder\Enums\ReportSpecialDateEnum;
use Wjbecker\FilamentReportBuilder\Resources\ReportResource\Pages;
use Wjbecker\FilamentReportBuilder\Models\Report;
use Wjbecker\FilamentReportBuilder\Support\DatabaseSchema;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Tables\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Split;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ForceDeleteAction;
use Filament\Tables\Actions\ForceDeleteBulkAction;
use Filament\Tables\Actions\RestoreAction;
use Filament\Tables\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;

class ReportResource extends Resource
{
    protected static ?string $model = Report::class;

    protected static ?string $slug = 'reports';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $navigationIcon = 'heroicon-o-list-bullet';

    protected static Collection $attributes;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Split::make([
                    Section::make([
                        TextInput::make('name')
                            ->required(),
                        TextInput::make('description')
                            ->required(),
                    ]),
                    Section::make([
                        Checkbox::make('data.with_trashed')->label('Show Deleted Records'),
                        TextInput::make('data.filename')->helperText('If blank, report name and current date will be used')
                    ])
                ])->columnSpanFull(),
                Tabs::make()
                    ->tabs([
                        Tabs\Tab::make('Columns')
                            ->schema([
                                Select::make('data.source')
                                    ->options(function () {
                                        return DatabaseSchema::getModels()
                                            ->mapWithKeys(fn ($class, $model) => [$class => Str::headline($model)]);
                                    })
                                    ->searchable()
                                    ->native(false)
                                    ->live(),
                                Repeater::make('data.columns')
                                    ->visible(fn(Get $get) => $get('data.source'))
                                    ->schema([
                                        Select::make('column_data')
                                            ->label('Column')
                                            ->options(fn (Get $get) => static::getAttributes($get('../../../data.source')))
                                            ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                            ->live()
                                            ->afterStateHydrated(fn (Select $component, ?string $state, Get $get) => $component->state(static::normalizeAttributeState($state, $get('../../../data.source'))))
                                            ->afterStateUpdated(function (Set $set, ?string $state) {
                                                if (blank($state)) return;

                                                $attribute = json_decode($state);
                                                $set('column_title', Str::of($attribute->item)
                                                    ->when(isset($attribute->name), function (Stringable $string) use ($attribute) {
                                                        return $string->prepend($attribute->name.'_');
                                                    })
                                                    ->headline()
                                                    ->toString());
                                            })
                                            ->searchable()
                                            ->native(false)
                                            ->required(),
                                        TextInput::make('column_title')
                                            ->label('Column Title')
                                            ->disabled(fn (Get $get): bool => !$get('column_data'))
                                            ->required(),
                                        CheckboxList::make('column_options')
                                            ->options([
                                                'is_sortable' => 'Sortable',
                                                'is_searchable' => 'Searchable',
                                            ])
                                            ->columns(3),
                                    ])
                                    ->defaultItems(1)
                                    ->columns()
                            ]),
                        Tabs\Tab::make('Filters')
                            ->visible(fn(Get $get) => $get('data.source'))
                            ->schema([
                                Repeater::make('data.filter_groups')
                                    ->label('Filter Groups')
                                    ->addActionLabel('Add Group')
                                    ->schema([
                                        Repeater::make('filters')
                                            ->addActionLabel('Add Filter')
                                            ->schema([
                                                Grid::make()
                                                    ->columns(1)
                                                    ->schema([
                                                        Select::make('attribute')
                                                            ->options(fn (Get $get) => static::getAttributes($get('../../../../../data.source')))
                                                            ->searchable()
                                                            ->native(false)
                                                            ->required()
                                                            ->live()
                                                            ->afterStateHydrated(fn (Select $component, ?string $state, Get $get) => $component->state(static::normalizeAttributeState($state, $get('../../../../../data.source'))))
                                                            ->afterStateUpdated(function (Select $component) {
                                                                return $component
                                                                    ->getContainer()->getParentComponent()->getContainer()
                                                                    ->getComponent('dynamicFilterFields')
                                                                    ->getChildComponentContainer()
                                                                    ->fill();
                                                            }),
                                                    ]),
                                                Grid::make(3)
                                                    ->schema(function (Get $get): array {
                                                        if (is_null($get('attribute'))) return [];

                                                        $attribute = json_decode($get('attribute'));

                                                        $components = [
                                                            Select::make('condition')
                                                                ->required()
                                                                ->hiddenLabel()
                                                                ->options(ReportConditionsEnum::class)
                                                                ->live()
                                                                ->native(false)
                                                        ];

                                                        if (is_string($attribute->cast) && enum_exists($enum = $attribute->cast)) {
                                                            $valueComponent = Select::make('value')
                                                                ->options($enum)
                                                                ->native(false);
                                                        } else if($attribute->cast === 'date' || $attribute->cast === 'datetime') {
                                                            $valueComponent = DatePicker::make('value');
                                                        } else if ($attribute->cast === 'boolean') {
                                                            $valueComponent = Radio::make('value')
                                                                ->boolean()
                                                                ->default(1)
                                                                ->inline()
                                                                ->inlineLabel(false);
                                                        } else {
                                                            $valueComponent = TextInput::make('value');
                                                        }

                                                        $components[] = $valueComponent
                                                            ->required()
                                                            ->hiddenLabel()
                                                            ->columnSpan(fn (Get $get) => in_array($get('condition'), ['between', 'not_between']) ? 1 : 2)
                                                            ->hidden(function (Get $get) use ($attribute): bool {
                                                                return $get('condition') === 'is_null' ||
                                                                    $get('condition') === 'is_not_null' ||
                                                                    $get('condition') === 'is_empty' ||
                                                                    $get('condition') === 'is_not_empty' ||
                                                                    $get('condition') === 'special_date';
                                                            });

                                                        $isDate = $attribute->cast === 'date' || $attribute->cast === 'datetime';

                                                        $components[] = ($isDate ? DatePicker::make('value2') : TextInput::make('value2'))
                                                            ->required()
                                                            ->hiddenLabel()
                                                            ->visible(function (Get $get) use ($attribute): bool {
                                                                return $get('condition') === 'between' ||
                                                                    $get('condition') === 'not_between';
                                                            });

                                                        $components[] = Select::make('special')
                                                            ->required()
                                                            ->hiddenLabel()
                                                            ->options(ReportSpecialDateEnum::class)
                                                            ->visible(fn (Get $get): bool => $get('condition') === 'special_date');

                                                        return $components;
                                                    })
                                                    ->key('dynamicFilterFields')
                                            ])
                                    ])
                                    ->defaultItems(0)
                                    ->columns(1)
                            ]),
                    ])
                    ->columnSpanFull()
            ]);
    }

    public static function getAttributes($source): ?Collection
    {
        $model = app()->make($source);
        $relations = DatabaseSchema::getModelRelations($model);
        $attributes = static::getModelAttributes(['related' => $source]);

        foreach ($relations as $relation) {
            $attributes = $attributes->merge(static::getModelAttributes($relation));
        }

        return $attributes->sort();
    }

    /**
     * Stored attribute keys embed metadata (e.g. cast) that can change when models change,
     * so match the stored value to the current option by relation name and attribute instead.
     */
    public static function normalizeAttributeState(?string $state, ?string $source): ?string
    {
        if (blank($state) || blank($source)) return $state;

        $stored = json_decode($state);

        if (!isset($stored->item)) return $state;

        return static::getAttributes($source)
            ->keys()
            ->first(function (string $key) use ($stored) {
                $option = json_decode($key);
                return $option->item === $stored->item && ($option->name ?? null) === ($stored->name ?? null);
            }) ?? $state;
    }

    public static function getModelAttributes($model)
    {
        return DatabaseSchema::getAttributes(app()->make($model['related']))
            ->filter(fn ($attribute) => !$attribute['primary'] && !$attribute['foreign'] && !$attribute['hidden'])
            ->mapWithKeys(fn ($attribute) => [json_encode($model+['item' => $attribute['name'], 'cast' => $attribute['cast']]) => (isset($model['name']) ? Str::headline($model['name']).' ('.Str::headline(class_basename($model['related'])).')' : Str::headline(class_basename($model['related']))) . ' - ' . Str::headline($attribute['name'])]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('description'),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->actions([
                Action::make('view')
                    ->url(fn (Report $record): string => route(Pages\ViewReport::getRouteName(), $record))
                    ->color('info')
                    ->icon('heroicon-m-eye'),
                EditAction::make(),
                DeleteAction::make()->hidden(fn (Report $record): bool => $record->is_built_in),
                RestoreAction::make(),
                ForceDeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReports::route('/'),
            'create' => Pages\CreateReport::route('/create'),
            'edit' => Pages\EditReport::route('/{record}/edit'),
            'view' => Pages\ViewReport::route('/{record}/view'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
