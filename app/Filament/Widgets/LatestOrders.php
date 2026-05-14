<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Auth;

class LatestOrders extends BaseWidget
{
    protected static ?string $heading = 'Dernieres commandes';
    protected static ?int $sort = 4;
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $user = Auth::user();
        $rid = ($user && $user->role === 'restaurant_admin') ? $user->restaurant_id : null;

        $query = Order::query()->with(['user', 'restaurant'])->latest()->limit(10);
        if ($rid) $query->where('restaurant_id', $rid);

        $isSuperAdmin = $user && in_array($user->role, ['admin', 'super_admin']);

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('N Commande')
                    ->weight('bold')
                    ->color('primary'),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Client')
                    ->icon('heroicon-m-user'),
                Tables\Columns\TextColumn::make('restaurant.name')
                    ->label('Restaurant')
                    ->visible($isSuperAdmin),
                Tables\Columns\TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'En attente', 'confirmed' => 'Confirmee', 'preparing' => 'En preparation',
                        'ready' => 'Prete', 'assigned' => 'Assignee', 'on_the_way' => 'En route',
                        'delivered' => 'Livree', 'cancelled' => 'Annulee', default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning', 'confirmed', 'preparing' => 'info',
                        'ready', 'assigned', 'on_the_way' => 'primary',
                        'delivered' => 'success', 'cancelled', 'refunded' => 'danger', default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->formatStateUsing(fn ($state) => number_format($state, 0, ',', ' ') . ' XAF')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('payment_status')
                    ->label('Paiement')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success', 'pending' => 'warning', 'failed' => 'danger', default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d/m/Y H:i'),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('Voir')
                    ->icon('heroicon-m-eye')
                    ->url(fn (Order $record): string => route('filament.admin.resources.orders.edit', $record))
                    ->color('primary'),
            ])
            ->paginated(false);
    }
}
