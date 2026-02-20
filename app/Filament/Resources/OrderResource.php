<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use App\Models\User;
use App\Models\Delivery;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;
    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';
    protected static ?string $navigationGroup = 'Commandes';
    protected static ?string $navigationLabel = 'Commandes';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Commande')
                ->schema([
                    Forms\Components\TextInput::make('order_number')
                        ->label('N° Commande')
                        ->disabled(),
                    Forms\Components\Select::make('status')
                        ->label('Statut')
                        ->options([
                            'pending'    => 'En attente',
                            'confirmed'  => 'Confirmée',
                            'preparing'  => 'En préparation',
                            'ready'      => 'Prête',
                            'assigned'   => 'Assignée',
                            'on_the_way' => 'En route',
                            'delivered'  => 'Livrée',
                            'cancelled'  => 'Annulée',
                            'refunded'   => 'Remboursée',
                        ])
                        ->required(),
                    Forms\Components\Select::make('payment_method')
                        ->label('Paiement')
                        ->options([
                            'cash'         => 'Espèces',
                            'mtn_momo'     => 'MTN Mobile Money',
                            'orange_money' => 'Orange Money',
                            'card'         => 'Carte bancaire',
                        ]),
                    Forms\Components\Select::make('payment_status')
                        ->label('Statut paiement')
                        ->options([
                            'pending'  => 'En attente',
                            'paid'     => 'Payé',
                            'failed'   => 'Échoué',
                            'refunded' => 'Remboursé',
                        ]),
                ])->columns(2),

            Forms\Components\Section::make('Montants')
                ->schema([
                    Forms\Components\TextInput::make('subtotal')->label('Sous-total')->numeric()->suffix('XAF'),
                    Forms\Components\TextInput::make('delivery_fee')->label('Livraison')->numeric()->suffix('XAF'),
                    Forms\Components\TextInput::make('discount_amount')->label('Remise')->numeric()->suffix('XAF'),
                    Forms\Components\TextInput::make('total')->label('Total')->numeric()->suffix('XAF'),
                ])->columns(4),

            Forms\Components\Section::make('Détails')
                ->schema([
                    Forms\Components\Textarea::make('special_instructions')->label('Instructions spéciales')->columnSpanFull(),
                    Forms\Components\Textarea::make('cancellation_reason')->label('Raison annulation')->columnSpanFull(),
                    Forms\Components\DateTimePicker::make('estimated_delivery_at')->label('Livraison estimée'),
                    Forms\Components\DateTimePicker::make('delivered_at')->label('Livré le'),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('N° Commande')
                    ->searchable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Client')
                    ->searchable(),
                Tables\Columns\TextColumn::make('restaurant.name')
                    ->label('Restaurant')
                    ->searchable(),
                Tables\Columns\TextColumn::make('delivery.driver.name')
                    ->label('Livreur')
                    ->badge()
                    ->color('info')
                    ->default('Non assigné')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending'    => 'warning',
                        'confirmed'  => 'info',
                        'preparing'  => 'info',
                        'ready'      => 'success',
                        'assigned'   => 'success',
                        'on_the_way' => 'success',
                        'delivered'  => 'success',
                        'cancelled'  => 'danger',
                        'refunded'   => 'danger',
                        default      => 'gray',
                    }),
                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->formatStateUsing(fn ($state) => number_format($state, 0, ',', ' ') . ' XAF')
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Paiement')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('payment_status')
                    ->label('Paiement')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'paid'     => 'success',
                        'pending'  => 'warning',
                        'failed'   => 'danger',
                        'refunded' => 'gray',
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
                        'confirmed'  => 'Confirmée',
                        'preparing'  => 'En préparation',
                        'ready'      => 'Prête',
                        'delivered'  => 'Livrée',
                        'cancelled'  => 'Annulée',
                    ]),
                Tables\Filters\SelectFilter::make('payment_status')
                    ->label('Statut paiement')
                    ->options([
                        'pending'  => 'En attente',
                        'paid'     => 'Payé',
                        'failed'   => 'Échoué',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('assign_driver')
                    ->label('Assigner un livreur')
                    ->icon('heroicon-o-truck')
                    ->color('success')
                    ->visible(fn (Order $record) => in_array($record->status, ['confirmed', 'preparing', 'ready']) && (!$record->delivery || !$record->delivery->driver_id))
                    ->form([
                        Forms\Components\Select::make('driver_id')
                            ->label('Sélectionner un livreur')
                            ->options(function () {
                                return User::where('role', 'driver')
                                ->where('is_online', true)
                                ->pluck('name', 'id');
                            })
                            ->searchable()
                            ->required()
                            ->helperText('Seuls les livreurs en ligne sont affichés'),
                        Forms\Components\Textarea::make('notes')
                            ->label('Notes pour le livreur')
                            ->rows(3),
                    ])
                    ->action(function (Order $record, array $data): void {
                        // Créer ou mettre à jour la livraison
                        if ($record->delivery) {
                            // Mettre à jour la livraison existante
                            $record->delivery->update([
                                'driver_id' => $data['driver_id'],
                                'status' => 'assigned',
                                'notes' => $data['notes'] ?? null,
                                'assigned_at' => now(),
                            ]);
                        } else {
                            // Créer une nouvelle livraison
                            Delivery::create([
                                'order_id' => $record->id,
                                'driver_id' => $data['driver_id'],
                                'pickup_address' => $record->restaurant->address ?? 'Restaurant',
                                'delivery_address' => $record->address->address ?? '',
                                'status' => 'assigned',
                                'notes' => $data['notes'] ?? null,
                                'assigned_at' => now(),
                            ]);
                        }

                        // Mettre à jour le statut de la commande
                        $record->update([
                            'status' => 'ready',
                        ]);

                        // Notification de succès
                        Notification::make()
                            ->success()
                            ->title('Livreur assigné')
                            ->body('La commande a été assignée au livreur avec succès.')
                            ->send();
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit'   => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}
