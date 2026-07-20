<?php

namespace App\Filament\Resources\OrderResource\RelationManagers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Services\RefundOrderItem;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Throwable;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('issue.report.name')->label('Report'),
                TextColumn::make('issue.version_label')->label('Issue'),
                TextColumn::make('amount_pence')->label('Amount')
                    ->formatStateUsing(fn (int $state): string => Money::format($state)),
                TextColumn::make('refunded_at')->label('Refunded')->dateTime()->placeholder('—'),
            ])
            ->recordActions([
                Action::make('refund')
                    ->requiresConfirmation()
                    ->color('danger')
                    ->visible(fn (OrderItem $record): bool => $record->refunded_at === null
                        && $record->order->status === 'fulfilled')
                    ->action(function (OrderItem $record): void {
                        $this->refundItem($record);
                    }),
            ])
            ->headerActions([
                Action::make('refundAll')
                    ->label('Refund all')
                    ->requiresConfirmation()
                    ->color('danger')
                    ->visible(fn (): bool => $this->getOwnerRecord()->status === 'fulfilled')
                    ->action(function (): void {
                        /** @var Order $order */
                        $order = $this->getOwnerRecord();
                        $order->items()->whereNull('refunded_at')->get()
                            ->each(fn (OrderItem $item) => $this->refundItem($item));
                    }),
            ]);
    }

    private function refundItem(OrderItem $item): void
    {
        try {
            app(RefundOrderItem::class)->handle($item);

            Notification::make()->success()->title('Item refunded')->send();
        } catch (Throwable $e) {
            Notification::make()->danger()->title('Refund failed')->body($e->getMessage())->send();
        }
    }
}
