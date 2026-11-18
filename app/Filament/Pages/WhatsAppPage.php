<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Http;

class WhatsAppPage extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-chat-bubble-left-ellipsis';
    protected static ?string $navigationLabel = 'WhatsApp';
    protected static ?string $navigationGroup = 'Parametres';
    protected static ?string $title           = 'Connexion WhatsApp';
    protected static ?int    $navigationSort  = 10;
    protected static string  $view            = 'filament.pages.whats-app-page';

    public bool   $connected = false;
    public string $qrDataUrl = '';
    public string $statusMessage = '';

    protected $listeners = ['refreshStatus' => 'loadStatus'];

    public function mount(): void
    {
        $this->loadStatus();
    }

    public function loadStatus(): void
    {
        $waUrl    = config('services.whatsapp.url', 'http://localhost:3500');
        $waSecret = config('services.whatsapp.secret', 'estuaire-wa-secret');

        try {
            $status = Http::timeout(5)
                ->withHeaders(['x-wa-secret' => $waSecret])
                ->get("{$waUrl}/status");

            if ($status->successful()) {
                $data = $status->json();
                $this->connected = $data['connected'] ?? false;

                if ($this->connected) {
                    $this->statusMessage = '✅ WhatsApp est connecté';
                    $this->qrDataUrl = '';
                } else {
                    $this->statusMessage = '⏳ En attente de connexion — scannez le QR code';
                    // Récupérer le QR
                    $qr = Http::timeout(5)
                        ->withHeaders(['x-wa-secret' => $waSecret])
                        ->get("{$waUrl}/qr");

                    if ($qr->successful() && isset($qr->json()['qr'])) {
                        $qrData = $qr->json()['qr'];
                        // Générer le QR en base64 PNG
                        $this->qrDataUrl = $this->generateQrDataUrl($qrData);
                    } else {
                        $this->statusMessage = '⏳ Génération du QR code en cours...';
                    }
                }
            }
        } catch (\Exception $e) {
            $this->statusMessage = '❌ Service WhatsApp inaccessible';
        }
    }

    private function generateQrDataUrl(string $qrData): string
    {
        // Utilise l'endpoint /qr-image du microservice
        $waUrl    = config('services.whatsapp.url', 'http://localhost:3500');
        $waSecret = config('services.whatsapp.secret', 'estuaire-wa-secret');

        try {
            $response = Http::timeout(5)
                ->withHeaders(['x-wa-secret' => $waSecret])
                ->get("{$waUrl}/qr-image", ['data' => urlencode($qrData)]);

            if ($response->successful()) {
                return 'data:image/png;base64,' . base64_encode($response->body());
            }
        } catch (\Exception $e) {}

        return '';
    }

    public static function canAccess(): bool
    {
        return in_array(auth()->user()?->role, ['admin', 'super_admin']);
    }
}
