<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;

class PaymentMethodsChart extends ChartWidget
{
    protected static ?string $heading = 'Methodes de paiement';
    protected static ?int $sort = 5;
    protected int | string | array $columnSpan = 1;

    protected function getData(): array
    {
        $methods = [
            'mtn_momo' => ['label' => 'MTN MoMo', 'color' => 'rgba(255, 204, 0, 0.8)'],
            'orange_money' => ['label' => 'Orange Money', 'color' => 'rgba(255, 107, 0, 0.8)'],
            'cash' => ['label' => 'Especes', 'color' => 'rgba(107, 114, 128, 0.8)'],
            'card' => ['label' => 'Carte', 'color' => 'rgba(59, 130, 246, 0.8)'],
        ];

        $data = [];
        $labels = [];
        $colors = [];

        foreach ($methods as $key => $method) {
            $count = Order::where('payment_method', $key)->count();
            if ($count > 0) {
                $data[] = $count;
                $labels[] = $method['label'];
                $colors[] = $method['color'];
            }
        }

        // Si aucune donnée, afficher un placeholder
        if (empty($data)) {
            $data = [1];
            $labels = ['Aucune commande'];
            $colors = ['rgba(209, 213, 219, 0.5)'];
        }

        return [
            'datasets' => [
                [
                    'data' => $data,
                    'backgroundColor' => $colors,
                    'borderWidth' => 0,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
