<?php

declare(strict_types=1);

namespace App\Filament\Resources\AreaEdits\Tables;

use App\Models\AreaEdit;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AreaEditsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('suggester'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('target_type')
                    ->label('Level')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'condominium' ? 'primary' : 'info'),
                TextColumn::make('target_id')
                    ->label('Target')
                    ->formatStateUsing(fn (AreaEdit $record): string => $record->target()?->name ?? '(deleted)'),
                TextColumn::make('suggester.display_name')
                    ->label('Suggested by')->placeholder('—'),
                TextColumn::make('reason')->limit(40)->wrap()->placeholder('—'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'pending' => 'warning',
                        default => 'danger',
                    }),
                TextColumn::make('created_at')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'pending' => 'Pending',
                    'approved' => 'Approved',
                    'rejected' => 'Rejected',
                ])->default('pending'),
            ])
            ->recordActions([
                Action::make('compare')
                    ->label('Compare')
                    ->icon('heroicon-o-map')
                    ->modalHeading(fn (AreaEdit $record): string => ($record->target()?->name ?? 'Area').' — current vs suggested')
                    ->modalContent(fn (AreaEdit $record) => view('filament.area-preview', [
                        'polygon' => $record->new_polygon,
                        'oldPolygon' => $record->target()?->polygon,
                    ]))
                    ->modalWidth('3xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
                Action::make('approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (AreaEdit $record): bool => $record->status === 'pending' && $record->target() !== null)
                    ->modalHeading('Apply this redrawn area?')
                    ->modalDescription('Compare both footprints below. Approving replaces the current area and awards points to the suggester.')
                    ->modalContent(fn (AreaEdit $record) => view('filament.area-preview', [
                        'polygon' => $record->new_polygon,
                        'oldPolygon' => $record->target()?->polygon,
                    ]))
                    ->modalWidth('3xl')
                    ->requiresConfirmation()
                    ->action(function (AreaEdit $record): void {
                        $record->apply();
                        $record->update([
                            'status' => 'approved',
                            'reviewed_by' => auth()->id(),
                            'reviewed_at' => now(),
                        ]);
                        $record->suggester?->awardPoints(AreaEdit::POINTS_ON_APPROVAL);
                        Notification::make()->title('Area updated')->success()->send();
                    }),
                Action::make('reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (AreaEdit $record): bool => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalDescription('The current footprint stays as it is.')
                    ->action(function (AreaEdit $record): void {
                        $record->update([
                            'status' => 'rejected',
                            'reviewed_by' => auth()->id(),
                            'reviewed_at' => now(),
                        ]);
                        Notification::make()->title('Suggestion rejected')->send();
                    }),
            ]);
    }
}
