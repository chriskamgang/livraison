<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Filament\Traits\HasRestaurantScope;
use App\Models\Order;
use App\Models\User;
use App\Models\Delivery;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;

class OrderResource extends Resource
{
    use HasRestaurantScope;

    protected static ?string $model = Order::class;
    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';
    protected static ?string $navigationGroup = 'Commandes';
    protected static ?string $navigationLabel = 'Commandes';
    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $restaurantId = static::getRestaurantId();

        if ($restaurantId) {
            $query->where('restaurant_id', $restaurantId);
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Commande')
                ->schema([
                    Forms\Components\TextInput::make('order_number')
                        ->label('N Commande')
                        ->disabled(),
                    Forms\Components\Select::make('status')
                        ->label('Statut')
                        ->options([
                            'pending'    => 'En attente',
                            'confirmed'  => 'Confirmee',
                            'preparing'  => 'En preparation',
                            'ready'      => 'Prete',
                            'assigned'   => 'Assignee',
                            'on_the_way' => 'En route',
                            'delivered'  => 'Livree',
                            'cancelled'  => 'Annulee',
                            'refunded'   => 'Remboursee',
                        ])
                        ->required(),
                    Forms\Components\Select::make('payment_method')
                        ->label('Paiement')
                        ->options([
                            'cash'         => 'Especes',
                            'mtn_momo'     => 'MTN Mobile Money',
                            'orange_money' => 'Orange Money',
                            'card'         => 'Carte bancaire',
                        ]),
                    Forms\Components\Select::make('payment_status')
                        ->label('Statut paiement')
                        ->options([
                            'pending'  => 'En attente',
                            'paid'     => 'Paye',
                            'failed'   => 'Echoue',
                            'refunded' => 'Rembourse',
                        ]),
                ])->columns(2),

            Forms\Components\Section::make('Montants')
                ->schema([
                    Forms\Components\TextInput::make('subtotal')->label('Sous-total')->numeric()->suffix('XAF'),
                    Forms\Components\TextInput::make('delivery_fee')->label('Livraison')->numeric()->suffix('XAF'),
                    Forms\Components\TextInput::make('discount_amount')->label('Remise')->numeric()->suffix('XAF'),
                    Forms\Components\TextInput::make('total')->label('Total')->numeric()->suffix('XAF'),
                ])->columns(4),

            Forms\Components\Section::make('Details')
                ->schema([
                    Forms\Components\Textarea::make('special_instructions')->label('Instructions speciales')->columnSpanFull(),
                    Forms\Components\Textarea::make('cancellation_reason')->label('Raison annulation')->columnSpanFull(),
                    Forms\Components\DateTimePicker::make('estimated_delivery_at')->label('Livraison estimee'),
                    Forms\Components\DateTimePicker::make('delivered_at')->label('Livre le'),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('N Commande')
                    ->searchable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Client')
                    ->searchable(),
                Tables\Columns\TextColumn::make('restaurant.name')
                    ->label('Restaurant')
                    ->searchable()
                    ->visible(fn () => static::isSuperAdmin()),
                Tables\Columns\TextColumn::make('delivery.driver.name')
                    ->label('Livreur')
                    ->badge()
                    ->color('info')
                    ->default('Non assigne'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending'    => 'En attente',
                        'confirmed'  => 'Confirmee',
                        'preparing'  => 'En preparation',
                        'ready'      => 'Prete',
                        'assigned'   => 'Assignee',
                        'on_the_way' => 'En route',
                        'delivered'  => 'Livree',
                        'cancelled'  => 'Annulee',
                        'refunded'   => 'Remboursee',
                        default      => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'pending'    => 'warning',
                        'confirmed', 'preparing' => 'info',
                        'ready', 'assigned', 'on_the_way' => 'primary',
                        'delivered'  => 'success',
                        'cancelled', 'refunded' => 'danger',
                        default      => 'gray',
                    }),
                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->formatStateUsing(fn ($state) => number_format($state, 0, ',', ' ') . ' XAF')
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment_status')
                    ->label('Paiement')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'paid'     => 'success',
                        'pending'  => 'warning',
                        'failed'   => 'danger',
                        default    => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Statut')
                    ->options([
                        'pending'    => 'En attente',
                        'confirmed'  => 'Confirmee',
                        'preparing'  => 'En preparation',
                        'ready'      => 'Prete',
                        'delivered'  => 'Livree',
                        'cancelled'  => 'Annulee',
                    ]),
                Tables\Filters\SelectFilter::make('payment_status')
                    ->label('Paiement')
                    ->options([
                        'pending'  => 'En attente',
                        'paid'     => 'Paye',
                        'failed'   => 'Echoue',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('mark_preparing')
                    ->label('En preparation')
                    ->icon('heroicon-o-fire')
                    ->color('info')
                    ->visible(fn (Order $record) => $record->status === 'confirmed')
                    ->requiresConfirmation()
                    ->modalHeading('Commencer la preparation ?')
                    ->modalDescription('La commande sera marquee comme en cours de preparation.')
                    ->action(function (Order $record): void {
                        $record->update(['status' => 'preparing']);
                        Notification::make()->success()->title('Commande en preparation')->send();
                    }),
                Tables\Actions\Action::make('mark_ready')
                    ->label('Prete !')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Order $record) => in_array($record->status, ['confirmed', 'preparing']))
                    ->requiresConfirmation()
                    ->modalHeading('Commande prete ?')
                    ->modalDescription('La commande sera visible par les livreurs en ligne.')
                    ->action(function (Order $record): void {
                        $record->update(['status' => 'ready']);
                        Notification::make()->success()->title('Commande prete - visible par les livreurs')->send();
                    }),
                Tables\Actions\Action::make('assign_driver')
                    ->label('Assigner livreur')
                    ->icon('heroicon-o-truck')
                    ->color('success')
                    ->visible(fn (Order $record) => in_array($record->status, ['confirmed', 'preparing', 'ready']) && (!$record->delivery || !$record->delivery->driver_id))
                    ->form([
                        Forms\Components\Select::make('driver_id')
                            ->label('Livreur')
                            ->options(fn () => User::where('role', 'driver')->where('is_online', true)->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (Order $record, array $data): void {
                        if ($record->delivery) {
                            $record->delivery->update([
                                'driver_id' => $data['driver_id'],
                                'status' => 'assigned',
                                'assigned_at' => now(),
                            ]);
                        } else {
                            Delivery::create([
                                'order_id' => $record->id,
                                'driver_id' => $data['driver_id'],
                                'pickup_address' => $record->restaurant->address ?? 'Restaurant',
                                'delivery_address' => $record->address->address ?? '',
                                'status' => 'assigned',
                                'assigned_at' => now(),
                            ]);
                        }
                        $record->update(['status' => 'ready']);
                        Notification::make()->success()->title('Livreur assigne')->send();
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->visible(fn () => static::isSuperAdmin()),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array { return []; }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit'   => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}
