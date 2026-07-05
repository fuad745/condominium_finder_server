<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ProjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('condominium_id')
                    ->relationship('condominium', 'name')
                    ->required(),
                TextInput::make('name')
                    ->required()
                    ->maxLength(150),
                TextInput::make('area_name')
                    ->maxLength(100),
                Select::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ])
                    ->required(),
                TextInput::make('block_range_start')
                    ->label('First block #')
                    ->numeric(),
                TextInput::make('block_range_end')
                    ->label('Last block #')
                    ->numeric(),
            ]);
    }
}
