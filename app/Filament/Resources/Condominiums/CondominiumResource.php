<?php

declare(strict_types=1);

namespace App\Filament\Resources\Condominiums;

use App\Filament\Resources\Condominiums\Pages\EditCondominium;
use App\Filament\Resources\Condominiums\Pages\ListCondominiums;
use App\Filament\Resources\Condominiums\Schemas\CondominiumForm;
use App\Filament\Resources\Condominiums\Tables\CondominiumsTable;
use App\Models\Condominium;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class CondominiumResource extends Resource
{
    protected static ?string $model = Condominium::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHomeModern;

    protected static string|UnitEnum|null $navigationGroup = 'Moderation';

    protected static ?int $navigationSort = 0;

    protected static ?string $pluralModelLabel = 'condominiums';

    public static function getNavigationBadge(): ?string
    {
        $pending = Condominium::query()->where('status', 'pending')->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return CondominiumForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CondominiumsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false; // drawn in the app; the panel moderates
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCondominiums::route('/'),
            'edit' => EditCondominium::route('/{record}/edit'),
        ];
    }
}
