<?php

declare(strict_types=1);

namespace App\Filament\Resources\BlockReports;

use App\Filament\Resources\BlockReports\Pages\ListBlockReports;
use App\Filament\Resources\BlockReports\Tables\BlockReportsTable;
use App\Models\BlockReport;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class BlockReportResource extends Resource
{
    protected static ?string $model = BlockReport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static string|UnitEnum|null $navigationGroup = 'Moderation';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Reports';

    protected static ?string $modelLabel = 'report';

    public static function getNavigationBadge(): ?string
    {
        $open = BlockReport::query()->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return BlockReportsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBlockReports::route('/'),
        ];
    }
}
