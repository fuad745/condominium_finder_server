<?php

declare(strict_types=1);

namespace App\Filament\Resources\Blocks;

use App\Filament\Resources\Blocks\Pages\EditBlock;
use App\Filament\Resources\Blocks\Pages\ListBlocks;
use App\Filament\Resources\Blocks\Schemas\BlockForm;
use App\Filament\Resources\Blocks\Tables\BlocksTable;
use App\Models\Block;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class BlockResource extends Resource
{
    protected static ?string $model = Block::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|UnitEnum|null $navigationGroup = 'Moderation';

    protected static ?int $navigationSort = 2;

    public static function getNavigationBadge(): ?string
    {
        $pending = Block::query()->where('status', 'pending')->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return BlockForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BlocksTable::configure($table);
    }

    public static function canCreate(): bool
    {
        // Blocks are contributed from the app; the panel only moderates.
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBlocks::route('/'),
            'edit' => EditBlock::route('/{record}/edit'),
        ];
    }
}
