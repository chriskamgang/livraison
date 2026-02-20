<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DriverResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DriverResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationGroup = 'Utilisateurs';
    protected static ?string $navigationLabel = 'Livreurs';
    protected static ?int $navigationSort = 2;
    protected static ?string $slug = 'drivers';

    // Filtre automatique : seulement les livreurs
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('role', 'driver');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Informations personnelles')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nom complet')
                        ->required(),
                    Forms\Components\TextInput::make('email')
                        ->label('Email')
                        ->email()
                        ->required(),
                    Forms\Components\TextInput::make('phone')
                        ->label('Téléphone')
                        ->tel(),
                    Forms\Components\TextInput::make('password')
                        ->label('Mot de passe')
                        ->password()
                        ->required(fn ($context) => $context === 'create')
                        ->dehydrateStateUsing(fn ($state) => $state ? bcrypt($state) : null)
                        ->dehydrated(fn ($state) => filled($state)),
                    Forms\Components\Select::make('status')
                        ->label('Statut du compte')
                        ->options([
                            'active'    => 'Actif',
                            'pending'   => 'En attente de validation',
                            'suspended' => 'Suspendu',
                            'banned'    => 'Banni',
                        ])
                        ->required(),
                    Forms\Components\Hidden::make('role')->default('driver'),
                ])->columns(2),

            Forms\Components\Section::make('Véhicule & Documents')
                ->schema([
                    Forms\Components\Select::make('vehicle_type')
                        ->label('Type de véhicule')
                        ->options([
                            'moto'     => 'Moto',
                            'voiture'  => 'Voiture',
                            'velo'     => 'Vélo',
                            'tricycle' => 'Tricycle',
                        ]),
                    Forms\Components\TextInput::make('vehicle_number')
                        ->label('Plaque d\'immatriculation'),
                    Forms\Components\TextInput::make('license_number')
                        ->label('N° Permis de conduire'),
                    Forms\Components\Toggle::make('is_verified')
                        ->label('Documents vérifiés')
                        ->helperText('Cocher après vérification des documents'),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('avatar')
                    ->label('')
                    ->circular()
                    ->defaultImageUrl(fn ($record) => 'https://ui-avatars.com/api/?name=' . urlencode($record->name) . '&color=fff&background=3b82f6&size=64'),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Téléphone')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('vehicle_type')
                    ->label('Véhicule')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'moto'     => 'Moto',
                        'voiture'  => 'Voiture',
                        'velo'     => 'Vélo',
                        'tricycle' => 'Tricycle',
                        default    => $state ?? '—',
                    }),
                Tables\Columns\TextColumn::make('vehicle_number')
                    ->label('Plaque')
                    ->searchable(),
                Tables\Columns\TextColumn::make('deliveries_count')
                    ->label('Livraisons')
                    ->counts('deliveries')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('rating')
                    ->label('Note')
                    ->formatStateUsing(fn ($state) => $state > 0 ? '⭐ ' . number_format($state, 1) : '—')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_online')
                    ->label('En ligne')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_verified')
                    ->label('Vérifié')
                    ->boolean(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active'    => 'success',
                        'pending'   => 'warning',
                        'suspended' => 'danger',
                        'banned'    => 'danger',
                        default     => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Statut')
                    ->options([
                        'active'    => 'Actifs',
                        'pending'   => 'En attente',
                        'suspended' => 'Suspendus',
                    ]),
                Tables\Filters\TernaryFilter::make('is_online')
                    ->label('En ligne'),
                Tables\Filters\TernaryFilter::make('is_verified')
                    ->label('Vérifié'),
                Tables\Filters\SelectFilter::make('vehicle_type')
                    ->label('Véhicule')
                    ->options([
                        'moto'    => 'Moto',
                        'voiture' => 'Voiture',
                        'velo'    => 'Vélo',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('verify')
                    ->label('Valider')
                    ->icon('heroicon-o-shield-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Valider ce livreur ?')
                    ->modalDescription('Les documents ont été vérifiés. Le livreur pourra maintenant recevoir des commandes.')
                    ->visible(fn (User $record) => !$record->is_verified || $record->status === 'pending')
                    ->action(fn (User $record) => $record->update(['is_verified' => true, 'status' => 'active'])),
                Tables\Actions\Action::make('suspend')
                    ->label('Suspendre')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (User $record) => $record->status === 'active')
                    ->action(fn (User $record) => $record->update(['status' => 'suspended', 'is_online' => false])),
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
            'index'  => Pages\ListDrivers::route('/'),
            'create' => Pages\CreateDriver::route('/create'),
            'edit'   => Pages\EditDriver::route('/{record}/edit'),
        ];
    }
}
