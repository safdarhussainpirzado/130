/**
 * NHMP 130 base map. Prefers the self-hosted OpenStreetMap (Protomaps/PMTiles) vector basemap served from
 * /maps/pakistan.pmtiles (set by the layout when the file exists); falls back to the Esri raster tiles otherwise,
 * so a missing/failed map file never leaves users with a blank map.
 */
(function () {
    var ESRI = 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Street_Map/MapServer/tile/{z}/{y}/{x}';
    window.nhmpBaseLayer = function (map, esriOptions) {
        var layer;
        try {
            if (window.NHMP_OSM_PMTILES && window.protomapsL && window.L) {
                layer = window.protomapsL.leafletLayer({
                    url: window.NHMP_OSM_PMTILES,
                    flavor: 'light',
                    lang: 'en',
                    maxZoom: 19,
                    maxDataZoom: 14,
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &middot; <a href="https://protomaps.com">Protomaps</a>'
                });
            }
        } catch (e) {
            layer = null;
        }
        if (!layer) {
            layer = window.L.tileLayer(ESRI, Object.assign({ maxZoom: 19 }, esriOptions || {}));
        }
        return layer.addTo(map);
    };
})();
