<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('points', 'desc')
            ->columns([
                TextColumn::make('display_name')
                    ->label('Name')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('email')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('auth_provider')
                    ->label('Sign-in')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('role')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'admin' ? 'primary' : 'gray'),
                TextColumn::make('points')
                    ->sortable()
                    ->alignEnd(),
                IconColumn::make('is_trusted')
                    ->label('Trusted')
                    ->boolean(),
                IconColumn::make('is_banned')
                    ->label('Banned')
                    ->boolean()
                    ->trueColor('danger')
                    ->falseColor('gray'),
                TextColumn::make('created_at')
                    ->label('Joined')
                    ->dateTime('Y-m-d')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->options(['user' => 'User', 'admin' => 'Admin']),
                TernaryFilter::make('is_trusted')->label('Trusted'),
                TernaryFilter::make('is_banned')->label('Banned'),
            ])
            ->recordActions([
                Action::make('trust')
                    ->label(fn (User $record): string => $record->is_trusted ? 'Untrust' : 'Trust')
                    ->icon('heroicon-o-shield-check')
                    ->color(fn (User $record): string => $record->is_trusted ? 'gray' : 'success')
                    ->requiresConfirmation()
                    ->modalDescription(fn (User $record): string => $record->is_trusted
                        ? 'Their future contributions will go back into the review queue.'
                        : 'Their future contributions will publish immediately, skipping review.')
                    ->action(function (User $record): void {
                        $record->forceFill(['is_trusted' => ! $record->is_trusted])->save();
                        Notification::make()
                            ->title($record->is_trusted ? 'User is now trusted' : 'Trust removed')
                            ->success()
                            ->send();
                    }),
                Action::make('ban')
                    ->label(fn (User $record): string => $record->is_banned ? 'Unban' : 'Ban')
                    ->icon('heroicon-o-no-symbol')
                    ->color(fn (User $record): string => $record->is_banned ? 'gray' : 'danger')
                    ->visible(fn (User $record): bool => $record->id !== auth()->id())
                    ->requiresConfirmation()
                    ->modalDescription(fn (User $record): string => $record->is_banned
                        ? 'They will be able to sign in and contribute again.'
                        : 'All their API writes are refused and they disappear from leaderboards.')
                    ->action(function (User $record): void {
                        $record->forceFill(['is_banned' => ! $record->is_banned])->save();
                        Notification::make()
                            ->title($record->is_banned ? 'User banned' : 'User unbanned')
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (User $record): bool => $record->id !== auth()->id()),
            ]);
    }
}
