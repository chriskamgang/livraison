<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class OrdersChart extends ChartWidget
{
    protected static ?string $heading = 'Commandes des 7 derniers jours';
    protected static ?int $sort = 2;
    protected int | string | array $columnSpan = 1;

    private function getRestaurantId(): ?int
    {
        $user = Auth::user();
        return ($user && $user->role === 'restaurant_admin') ? $user->restaurant_id : null;
    }

    protected function getData(): array
    {
        $rid = $this->getRestaurantId();
        $labels = [];
        $ordersData = [];
        $deliveredData = [];
        $cancelledData = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $labels[] = $date->translatedFormat('D d/m');

            $base = Order::query();
            if ($rid) $base->where('restaurant_id', $rid);
            $ordersData[] = (clone $base)->whereDate('created_at', $date)->count();

            $base2 = Order::query();
            if ($rid) $base2->where('restaurant_id', $rid);
            $deliveredData[] = (clone $base2)->whereDate('updated_at', $date)->where('status', 'delivered')->count();

            $base3 = Order::query();
            if ($rid) $base3->where('restaurant_id', $rid);
            $cancelledData[] = (clone $base3)->whereDate('updated_at', $date)->where('status', 'cancelled')->count();
        }

        return [
            'datasets' => [
                ['label' => 'Commandes', 'data' => $ordersData, 'borderColor' => '#FF6B35', 'backgroundColor' => 'rgba(255, 107, 53, 0.1)', 'fill' => true, 'tension' => 0.4],
                ['label' => 'Livrees', 'data' => $deliveredData, 'borderColor' => '#10B981', 'backgroundColor' => 'rgba(16, 185, 129, 0.1)', 'fill' => true, 'tension' => 0.4],
                ['label' => 'Annulees', 'data' => $cancelledData, 'borderColor' => '#EF4444', 'backgroundColor' => 'rgba(239, 68, 68, 0.1)', 'fill' => false, 'tension' => 0.4],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string { return 'line'; }
}
