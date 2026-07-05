<?php

declare(strict_types=1);

namespace App\Filament\Resources\AreaEdits;

use App\Filament\Resources\AreaEdits\Pages\ListAreaEdits;
use App\Filament\Resources\AreaEdits\Tables\AreaEditsTable;
use App\Models\AreaEdit;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class AreaEditResource extends Resource
{
    protected static ?string $model = AreaEdit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static string|UnitEnum|null $navigationGroup = 'Moderation';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Area edits';

    protected static ?string $modelLabel = 'area edit';

    public static function getNavigationBadge(): ?string
    {
        $pending = AreaEdit::query()->where('status', 'pending')->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return AreaEditsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAreaEdits::route('/'),
        ];
    }
}
