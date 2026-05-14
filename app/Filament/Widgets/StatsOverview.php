<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\User;
use App\Models\Restaurant;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;
    protected int | string | array $columnSpan = 'full';

    private function getRestaurantId(): ?int
    {
        $user = Auth::user();
        if ($user && $user->role === 'restaurant_admin') {
            return $user->restaurant_id;
        }
        return null;
    }

    private function isSuperAdmin(): bool
    {
        $user = Auth::user();
        return $user && in_array($user->role, ['admin', 'super_admin']);
    }

    private function ordersQuery()
    {
        $query = Order::query();
        $rid = $this->getRestaurantId();
        if ($rid) $query->where('restaurant_id', $rid);
        return $query;
    }

    protected function getStats(): array
    {
        $today = Carbon::today();
        $thisMonth = Carbon::now()->startOfMonth();

        // Commandes aujourd'hui
        $ordersToday = (clone $this->ordersQuery())->whereDate('created_at', $today)->count();
        $ordersYesterday = (clone $this->ordersQuery())->whereDate('created_at', $today->copy()->subDay())->count();
        $ordersTrend = $ordersYesterday > 0
            ? round((($ordersToday - $ordersYesterday) / $ordersYesterday) * 100)
            : ($ordersToday > 0 ? 100 : 0);

        // Revenus
        $validStatuses = ['confirmed', 'preparing', 'ready', 'assigned', 'on_the_way', 'delivered'];
        $revenueToday = (clone $this->ordersQuery())->whereDate('created_at', $today)
            ->whereIn('status', $validStatuses)->sum('total');
        $revenueMonth = (clone $this->ordersQuery())->where('created_at', '>=', $thisMonth)
            ->whereIn('status', $validStatuses)->sum('total');

        // Commandes en cours
        $activeOrders = (clone $this->ordersQuery())
            ->whereIn('status', ['pending', 'confirmed', 'preparing', 'ready', 'assigned', 'on_the_way'])->count();
        $deliveredToday = (clone $this->ordersQuery())
            ->whereDate('updated_at', $today)->where('status', 'delivered')->count();

        // Sparklines 7 jours
        $last7Days = collect(range(6, 0))->map(fn ($d) =>
            (clone $this->ordersQuery())->whereDate('created_at', Carbon::today()->subDays($d))->count()
        )->toArray();

        $revenueLast7 = collect(range(6, 0))->map(fn ($d) =>
            (int) (clone $this->ordersQuery())->whereDate('created_at', Carbon::today()->subDays($d))
                ->whereIn('status', $validStatuses)->sum('total')
        )->toArray();

        $stats = [
            Stat::make('Commandes aujourd\'hui', $ordersToday)
                ->description($ordersTrend >= 0 ? "+{$ordersTrend}% vs hier" : "{$ordersTrend}% vs hier")
                ->descriptionIcon($ordersTrend >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($ordersTrend >= 0 ? 'success' : 'danger')
                ->chart($last7Days)
                ->icon('heroicon-o-shopping-bag'),

            Stat::make('Revenus aujourd\'hui', number_format($revenueToday, 0, ',', ' ') . ' XAF')
                ->description('Ce mois: ' . number_format($revenueMonth, 0, ',', ' ') . ' XAF')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success')
                ->chart($revenueLast7)
                ->icon('heroicon-o-currency-dollar'),

            Stat::make('Commandes en cours', $activeOrders)
                ->description("{$deliveredToday} livrees aujourd'hui")
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('warning')
                ->icon('heroicon-o-clock'),
        ];

        // Stats visibles seulement pour super admin
        if ($this->isSuperAdmin()) {
            $totalClients = User::where('role', 'client')->count();
            $newClientsToday = User::where('role', 'client')->whereDate('created_at', $today)->count();
            $totalRestaurants = Restaurant::count();
            $activeRestaurants = Restaurant::where('is_active', true)->count();
            $driversOnline = User::where('role', 'driver')->where('is_online', true)->count();
            $driversTotal = User::where('role', 'driver')->count();

            $stats[] = Stat::make('Restaurants', "{$activeRestaurants} / {$totalRestaurants}")
                ->description("{$activeRestaurants} actifs")
                ->descriptionIcon('heroicon-m-building-storefront')
                ->color('info')
                ->icon('heroicon-o-building-storefront');

            $stats[] = Stat::make('Clients', $totalClients)
                ->description("+{$newClientsToday} aujourd'hui")
                ->descriptionIcon('heroicon-m-user-plus')
                ->color('info')
                ->icon('heroicon-o-users');

            $stats[] = Stat::make('Livreurs en ligne', "{$driversOnline} / {$driversTotal}")
                ->description($driversTotal > 0 ? round(($driversOnline / $driversTotal) * 100) . '% disponibles' : 'Aucun')
                ->color($driversOnline > 0 ? 'success' : 'danger')
                ->icon('heroicon-o-truck');
        }

        return $stats;
    }
}
