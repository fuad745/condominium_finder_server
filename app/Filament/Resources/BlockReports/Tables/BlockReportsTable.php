<?php

declare(strict_types=1);

namespace App\Filament\Resources\BlockReports\Tables;

use App\Models\Block;
use App\Models\BlockReport;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BlockReportsTable
{
    /** Recomputes a block's report counter and verified flag after triage. */
    private static function refreshBlockCounters(Block $block): void
    {
        $count = $block->reports()->count();
        $block->update([
            'report_count' => $count,
            'is_verified' => $count >= 3 ? false : $block->verified_count >= 3,
        ]);
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['block.project', 'reporter']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('block.block_number')
                    ->label('Block')
                    ->formatStateUsing(fn (BlockReport $record): string => "Block {$record->block?->block_number}")
                    ->description(fn (BlockReport $record): ?string => $record->block?->project?->name),
                TextColumn::make('reason')
                    ->limit(60)
                    ->wrap()
                    ->placeholder('No reason given'),
                TextColumn::make('reporter.display_name')
                    ->label('Reported by')
                    ->placeholder('—'),
                TextColumn::make('block.report_count')
                    ->label('Total reports')
                    ->alignEnd()
                    ->color(fn (?int $state): string => ($state ?? 0) >= 3 ? 'danger' : 'gray'),
                TextColumn::make('created_at')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('map')
                    ->label('Map')
                    ->icon('heroicon-o-map-pin')
                    ->modalHeading(fn (BlockReport $record): string => "Block {$record->block?->block_number} — {$record->block?->project?->name}")
                    ->modalContent(fn (BlockReport $record) => view('filament.map-preview', [
                        'lat' => $record->block->lat,
                        'lng' => $record->block->lng,
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
                Action::make('dismiss')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Dismiss this report?')
                    ->modalDescription('The block is fine — the report is removed and the counters are recalculated.')
                    ->action(function (BlockReport $record): void {
                        $block = $record->block;
                        $record->delete();
                        if ($block !== null) {
                            self::refreshBlockCounters($block);
                        }
                        Notification::make()->title('Report dismissed')->success()->send();
                    }),
                Action::make('deleteBlock')
                    ->label('Delete block')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(fn (BlockReport $record): string => "Delete block {$record->block?->block_number} entirely?")
                    ->modalDescription('Removes the block and all its votes, reports and suggested edits. Use when the pin is bogus.')
                    ->action(function (BlockReport $record): void {
                        $record->block?->delete();
                        Notification::make()->title('Block deleted')->send();
                    }),
            ]);
    }
}
