<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class RevenueChart extends ChartWidget
{
    protected static ?string $heading = 'Revenus des 7 derniers jours';
    protected static ?int $sort = 3;
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
        $revenueData = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $labels[] = $date->translatedFormat('D d/m');

            $query = Order::whereDate('created_at', $date)
                ->whereIn('status', ['confirmed', 'preparing', 'ready', 'assigned', 'on_the_way', 'delivered']);
            if ($rid) $query->where('restaurant_id', $rid);
            $revenueData[] = (int) $query->sum('total');
        }

        return [
            'datasets' => [
                [
                    'label' => 'Revenus (XAF)',
                    'data' => $revenueData,
                    'backgroundColor' => 'rgba(255, 107, 53, 0.8)',
                    'borderRadius' => 8,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string { return 'bar'; }
}
