<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('display_name')
                    ->required()
                    ->maxLength(100),
                TextInput::make('email')
                    ->email()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('password')
                    ->password()
                    ->revealable()
                    // Stored into password_hash by the Create/Edit pages;
                    // leave blank on edit to keep the current password.
                    ->dehydrated(false)
                    ->requiredOn('create')
                    ->minLength(6)
                    ->helperText('Only used for email sign-in. Leave blank to keep unchanged.'),
                Select::make('role')
                    ->options(['user' => 'User', 'admin' => 'Admin'])
                    ->required()
                    ->helperText('Admins can sign in to this panel.'),
                TextInput::make('points')
                    ->numeric()
                    ->minValue(0)
                    ->default(0),
                Toggle::make('is_trusted')
                    ->label('Trusted (skips the review queue)'),
                Toggle::make('is_banned')
                    ->label('Banned'),
            ]);
    }
}
