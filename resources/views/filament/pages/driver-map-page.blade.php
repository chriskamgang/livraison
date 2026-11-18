<x-filament-panels::page>
    <style>
        .dm-wrap { display: grid; grid-template-columns: minmax(0, 1fr); gap: 1rem; }
        @media (min-width: 1024px) { .dm-wrap { grid-template-columns: minmax(0, 2fr) minmax(0, 1fr); } }
        .dm-map { height: 70vh; min-height: 420px; width: 100%; border-radius: .75rem; z-index: 0; }
        .dm-card { background: #fff; border-radius: .75rem; box-shadow: 0 1px 3px rgba(0,0,0,.08); padding: 1rem; margin-bottom: 1rem; }
        .dark .dm-card { background: rgb(17 24 39); color: #e5e7eb; }
        .dm-card h3 { font-size: .95rem; margin: 0 0 .5rem; font-weight: 600; }
        .dm-row { display: flex; align-items: flex-start; gap: .5rem; padding: .5rem 0; border-top: 1px solid rgba(127,127,127,.15); font-size: .85rem; cursor: pointer; }
        .dm-row:first-of-type { border-top: 0; }
        .dm-dot { width: .65rem; height: .65rem; border-radius: 9999px; margin-top: .35rem; flex: none; }
        .dm-muted { color: #6b7280; font-size: .75rem; }
        .dm-badge { display: inline-block; padding: .05rem .45rem; border-radius: 9999px; font-size: .7rem; background: #FFEDD5; color: #9A3412; }
        .dm-bar { display: flex; justify-content: space-between; align-items: center; font-size: .8rem; color: #6b7280; margin-bottom: .5rem; gap: .5rem; flex-wrap: wrap; }
        .dm-legend span { display: inline-flex; align-items: center; gap: .25rem; margin-right: .75rem; }
        .dm-line { width: 1.2rem; height: .25rem; border-radius: 2px; display: inline-block; }
        .dm-error { color: #b91c1c; }
    </style>

    <script>
        window.driverMapPage = window.driverMapPage || function ($wire) {
            // Objets Leaflet hors du state Alpine (les proxies réactifs cassent Leaflet)
            let map = null, timer = null, fitted = false;
            const driverMarkers = {};   // id -> marker
            const deliveryLayers = {};  // id -> { group, resto, client, toResto, toClient }
            const BAFOUSSAM = [5.4781, 10.4176];

            const loadLeaflet = () => new Promise((resolve, reject) => {
                if (window.L) return resolve();
                if (!document.getElementById('leaflet-css')) {
                    const css = document.createElement('link');
                    css.id = 'leaflet-css'; css.rel = 'stylesheet';
                    css.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
                    document.head.appendChild(css);
                }
                const s = document.createElement('script');
                s.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
                s.onload = () => resolve();
                s.onerror = () => reject(new Error('Leaflet indisponible'));
                document.head.appendChild(s);
            });

            const esc = (v) => String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

            const ago = (s) => {
                if (s === null || s === undefined) return 'jamais';
                if (s < 60) return 'il y a ' + s + ' s';
                if (s < 3600) return 'il y a ' + Math.floor(s / 60) + ' min';
                if (s < 86400) return 'il y a ' + Math.floor(s / 3600) + ' h';
                return 'il y a ' + Math.floor(s / 86400) + ' j';
            };
            const duration = (s) => {
                if (s === null || s === undefined) return '—';
                const m = Math.floor(s / 60);
                return m < 60 ? m + ' min' : Math.floor(m / 60) + ' h ' + String(m % 60).padStart(2, '0');
            };

            const emojiIcon = (emoji) => L.divIcon({
                className: '', iconSize: [28, 28], iconAnchor: [14, 14],
                html: '<div style="font-size:22px;line-height:28px;text-align:center;filter:drop-shadow(0 1px 1px rgba(0,0,0,.4))">' + emoji + '<\/div>',
            });

            const driverPopup = (d) => {
                let html = '<strong>' + esc(d.name) + '<\/strong><br>' + esc(d.phone)
                    + '<br>' + (d.is_online ? '🟢 En ligne' : '⚪ Hors ligne')
                    + '<br><small>vu ' + ago(d.last_seen_seconds) + '<\/small>';
                if (d.current_delivery) {
                    html += '<br>📦 ' + esc(d.current_delivery.order_number) + ' — ' + esc(d.current_delivery.status_label);
                }
                return html;
            };

            return {
                drivers: [],
                deliveries: [],
                error: '',
                updatedLabel: 'Chargement…',
                ago, duration,

                async init() {
                    try {
                        await loadLeaflet();
                    } catch (e) {
                        this.error = e.message;
                        return;
                    }
                    map = L.map(this.$refs.map).setView(BAFOUSSAM, 14);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 19, attribution: '&copy; OpenStreetMap',
                    }).addTo(map);

                    await this.refresh();
                    timer = setInterval(() => {
                        // Stoppe le polling si on a quitté la page (navigation SPA)
                        if (!document.body.contains(this.$refs.map)) { clearInterval(timer); return; }
                        this.refresh();
                    }, 10000);
                },

                async refresh() {
                    try {
                        const data = await $wire.getMapData();
                        this.error = '';
                        this.drivers = data.drivers;
                        this.deliveries = data.deliveries;
                        this.render(data);
                        this.updatedLabel = 'Mis à jour à ' + new Date().toLocaleTimeString('fr-FR');
                    } catch (e) {
                        this.error = 'Erreur de rafraîchissement';
                    }
                },

                render(data) {
                    const bounds = [];

                    // --- Livreurs ---
                    const seenDrivers = new Set();
                    data.drivers.forEach(d => {
                        if (!d.position) return;
                        seenDrivers.add(d.id);
                        bounds.push(d.position);
                        const style = {
                            radius: 9, weight: 2, color: '#fff', fillOpacity: 1,
                            fillColor: d.is_online ? '#16a34a' : '#9ca3af',
                        };
                        let m = driverMarkers[d.id];
                        if (!m) {
                            m = L.circleMarker(d.position, style).addTo(map);
                            m.bindTooltip(d.name, { direction: 'top', offset: [0, -8] });
                            m.bindPopup('');
                            driverMarkers[d.id] = m;
                        } else {
                            m.setLatLng(d.position);
                            m.setStyle(style);
                        }
                        m.setPopupContent(driverPopup(d));
                        if (d.is_online) m.bringToFront();
                    });
                    Object.keys(driverMarkers).forEach(id => {
                        if (!seenDrivers.has(Number(id))) { map.removeLayer(driverMarkers[id]); delete driverMarkers[id]; }
                    });

                    // --- Livraisons actives ---
                    const seenDeliveries = new Set();
                    data.deliveries.forEach(l => {
                        seenDeliveries.add(l.id);
                        let g = deliveryLayers[l.id];
                        if (!g) {
                            g = { group: L.layerGroup().addTo(map) };
                            g.toResto = L.polyline([], { color: '#2563eb', weight: 4, opacity: .8 }).addTo(g.group);
                            g.toClient = L.polyline([], { color: '#f97316', weight: 4, opacity: .9 }).addTo(g.group);
                            deliveryLayers[l.id] = g;
                        }
                        g.toResto.setLatLngs(l.path_to_restaurant);
                        g.toClient.setLatLngs(l.path_to_customer);

                        if (l.restaurant) {
                            bounds.push(l.restaurant);
                            if (!g.resto) g.resto = L.marker(l.restaurant, { icon: emojiIcon('🍽️') }).addTo(g.group);
                            g.resto.setLatLng(l.restaurant);
                            g.resto.bindPopup('<strong>' + esc(l.restaurant_name) + '<\/strong><br>Commande ' + esc(l.order_number));
                        }
                        if (l.customer) {
                            bounds.push(l.customer);
                            if (!g.client) g.client = L.marker(l.customer, { icon: emojiIcon('🏠') }).addTo(g.group);
                            g.client.setLatLng(l.customer);
                            g.client.bindPopup('<strong>' + esc(l.customer_name) + '<\/strong><br>' + esc(l.delivery_address)
                                + '<br>Commande ' + esc(l.order_number) + ' — ' + esc(l.status_label));
                        }
                    });
                    Object.keys(deliveryLayers).forEach(id => {
                        if (!seenDeliveries.has(Number(id))) { map.removeLayer(deliveryLayers[id].group); delete deliveryLayers[id]; }
                    });

                    // Cadrage initial uniquement (ne pas perturber l'utilisateur ensuite)
                    if (!fitted && bounds.length) {
                        map.fitBounds(bounds, { padding: [40, 40], maxZoom: 15 });
                        fitted = true;
                    }
                },

                focusDriver(id) {
                    const m = driverMarkers[id];
                    if (m && map) { map.setView(m.getLatLng(), Math.max(map.getZoom(), 15)); m.openPopup(); }
                },

                focusDelivery(id) {
                    const g = deliveryLayers[id];
                    if (!g || !map) return;
                    const b = L.latLngBounds([]);
                    g.group.eachLayer(layer => {
                        if (layer.getBounds) { const lb = layer.getBounds(); if (lb.isValid()) b.extend(lb); }
                        else if (layer.getLatLng) b.extend(layer.getLatLng());
                    });
                    if (b.isValid()) map.fitBounds(b, { padding: [40, 40], maxZoom: 16 });
                },
            };
        };
    </script>

    <div wire:ignore x-data="driverMapPage($wire)">
        <div class="dm-bar">
            <div class="dm-legend">
                <span><i class="dm-dot" style="background:#16a34a"></i> En ligne</span>
                <span><i class="dm-dot" style="background:#9ca3af"></i> Hors ligne</span>
                <span>🍽️ Restaurant</span>
                <span>🏠 Client</span>
                <span><i class="dm-line" style="background:#2563eb"></i> Vers le restaurant</span>
                <span><i class="dm-line" style="background:#f97316"></i> Vers le client</span>
            </div>
            <div>
                <span x-show="error" class="dm-error" x-text="error"></span>
                <span x-text="updatedLabel"></span>
            </div>
        </div>

        <div class="dm-wrap">
            <div>
                <div x-ref="map" class="dm-map"></div>
            </div>

            <div>
                <div class="dm-card">
                    <h3>Livreurs (<span x-text="drivers.filter(d => d.is_online).length"></span> en ligne / <span x-text="drivers.length"></span>)</h3>
                    <template x-if="drivers.length === 0">
                        <p class="dm-muted">Aucun livreur.</p>
                    </template>
                    <template x-for="d in drivers" :key="'d' + d.id">
                        <div class="dm-row" @click="focusDriver(d.id)">
                            <i class="dm-dot" :style="'background:' + (d.is_online ? '#16a34a' : '#9ca3af')"></i>
                            <div style="flex:1; min-width:0">
                                <div><strong x-text="d.name"></strong> <span class="dm-muted" x-text="d.phone"></span></div>
                                <div class="dm-muted">
                                    <span x-text="d.is_online ? 'En ligne' : 'Hors ligne'"></span> ·
                                    <span x-text="'vu ' + ago(d.last_seen_seconds)"></span>
                                    <span x-show="!d.position"> · pas de position</span>
                                </div>
                                <template x-if="d.current_delivery">
                                    <div><span class="dm-badge" x-text="d.current_delivery.order_number + ' — ' + d.current_delivery.status_label"></span></div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="dm-card">
                    <h3>Livraisons en cours (<span x-text="deliveries.length"></span>)</h3>
                    <template x-if="deliveries.length === 0">
                        <p class="dm-muted">Aucune livraison en cours.</p>
                    </template>
                    <template x-for="l in deliveries" :key="'l' + l.id">
                        <div class="dm-row" @click="focusDelivery(l.id)">
                            <i class="dm-dot" :style="'background:' + (l.picked_up ? '#f97316' : '#2563eb')"></i>
                            <div style="flex:1; min-width:0">
                                <div><strong x-text="l.order_number"></strong> · <span class="dm-badge" x-text="l.status_label"></span></div>
                                <div x-text="(l.restaurant_name || 'Restaurant') + ' → ' + (l.customer_name || 'Client')"></div>
                                <div class="dm-muted" x-text="l.delivery_address || ''"></div>
                                <div class="dm-muted">
                                    <span x-text="'Livreur : ' + (l.driver_name || '—')"></span> ·
                                    <span x-text="'depuis ' + duration(l.elapsed_seconds)"></span>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>

</x-filament-panels::page>
