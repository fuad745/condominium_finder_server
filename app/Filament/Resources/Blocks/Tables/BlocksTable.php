<?php

declare(strict_types=1);

namespace App\Filament\Resources\Blocks\Tables;

use App\Models\Block;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BlocksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['project', 'submitter']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('block_number')
                    ->label('Block')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('project.name')
                    ->label('Project')
                    ->searchable()
                    ->sortable()
                    ->limit(30),
                TextColumn::make('submitter.display_name')
                    ->label('Submitted by')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'pending' => 'warning',
                        default => 'danger',
                    }),
                IconColumn::make('is_verified')
                    ->label('Verified')
                    ->boolean(),
                TextColumn::make('verified_count')
                    ->label('Confirms')
                    ->sortable()
                    ->alignEnd(),
                TextColumn::make('report_count')
                    ->label('Reports')
                    ->sortable()
                    ->alignEnd()
                    ->color(fn (int $state): string => $state >= 3 ? 'danger' : 'gray'),
                TextColumn::make('created_at')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                    ]),
                SelectFilter::make('project')
                    ->relationship('project', 'name'),
            ])
            ->recordActions([
                Action::make('map')
                    ->label('Map')
                    ->icon('heroicon-o-map-pin')
                    ->modalHeading(fn (Block $record): string => "Block {$record->block_number} — {$record->project?->name}")
                    ->modalContent(fn (Block $record) => view('filament.map-preview', [
                        'lat' => $record->lat,
                        'lng' => $record->lng,
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
                Action::make('approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Block $record): bool => $record->status === 'pending')
                    ->modalHeading(fn (Block $record): string => "Approve block {$record->block_number}?")
                    ->modalDescription('Check the pin below — approving publishes it on the map and awards points to the contributor.')
                    ->modalContent(fn (Block $record) => view('filament.map-preview', [
                        'lat' => $record->lat,
                        'lng' => $record->lng,
                    ]))
                    ->requiresConfirmation()
                    ->action(function (Block $record): void {
                        $record->update(['status' => 'approved']);
                        $record->submitter?->awardPoints(Block::POINTS_ON_APPROVAL);
                        Notification::make()
                            ->title("Block {$record->block_number} approved")
                            ->success()
                            ->send();
                    }),
                Action::make('reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Block $record): bool => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading(fn (Block $record): string => "Reject block {$record->block_number}?")
                    ->modalDescription('The submission is deleted so the block number stays free for a correct entry.')
                    ->action(function (Block $record): void {
                        $record->delete();
                        Notification::make()->title('Submission rejected')->send();
                    }),
                EditAction::make(),
            ]);
    }
}
