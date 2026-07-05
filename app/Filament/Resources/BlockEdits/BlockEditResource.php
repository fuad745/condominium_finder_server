<?php

declare(strict_types=1);

namespace App\Filament\Resources\BlockEdits;

use App\Filament\Resources\BlockEdits\Pages\ListBlockEdits;
use App\Filament\Resources\BlockEdits\Tables\BlockEditsTable;
use App\Models\BlockEdit;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class BlockEditResource extends Resource
{
    protected static ?string $model = BlockEdit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsPointingOut;

    protected static string|UnitEnum|null $navigationGroup = 'Moderation';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Suggested edits';

    protected static ?string $modelLabel = 'suggested edit';

    public static function getNavigationBadge(): ?string
    {
        $pending = BlockEdit::query()->where('status', 'pending')->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return BlockEditsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBlockEdits::route('/'),
        ];
    }
}
