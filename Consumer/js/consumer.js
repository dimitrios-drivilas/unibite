// Consumer/js/consumer.js

let map = null;
let markersLayer = null;
let userMarker = null;
let userLocation = { lat: null, lon: null };

// Campus center coordinates (Panepistimioupoli Patras, Rio)
const CAMPUS_CENTER = [38.2882, 21.7885];
const CAMPUS_ZOOM = 16;

// Campus locations for autocomplete matching
const CAMPUS_LOCATIONS = [
    { id: 'fep-building-a', name: 'ΦΕΠ - Κτίριο Α (Κοιτονομήματα)', lat: 38.2915, lng: 21.7940, icon: '🏠', category: 'dormitory' },
    { id: 'fep-building-b', name: 'ΦΕΠ - Κτίριο Β (Κοιτονομήματα)', lat: 38.2945, lng: 21.7892, icon: '🏠', category: 'dormitory' },
    { id: 'fep-building-c', name: 'ΦΕΠ - Κτίριο Γ (Κοιτονομήματα)', lat: 38.2948, lng: 21.7897, icon: '🏠', category: 'dormitory' },
    { id: 'fep-building-z', name: 'ΦΕΠ - Κτίριο Ζ (Κοιτονομήματα)', lat: 38.2951, lng: 21.7902, icon: '🏠', category: 'dormitory' },
    { id: 'fep-building-h', name: 'ΦΕΠ - Κτίριο Η (Κοιτονομήματα)', lat: 38.2954, lng: 21.7907, icon: '🏠', category: 'dormitory' },
    { id: 'fep-building-th', name: 'ΦΕΠ - Κτίριο Θ (Κοιτονομήματα)', lat: 38.2957, lng: 21.7912, icon: '🏠', category: 'dormitory' },
    { id: 'fep-canteen', name: 'Φοιτητική Λέσχη / Εστιατόριο Ρίου', lat: 38.2940, lng: 21.7875, icon: '🍽️', category: 'facility' },
    { id: 'admin-building-a', name: 'Κτίριο Α - Διοίκηση / Πρυτανεία', lat: 38.2915, lng: 21.7865, icon: '🏛️', category: 'administration' },
    { id: 'building-b', name: 'Κτίριο Β - Πολυτεχνική / Αρχιτέκτονες / Φυσικοθεραπεία', lat: 38.2920, lng: 21.7870, icon: '🏫', category: 'department' },
    { id: 'ceid-building', name: 'CEID - Μηχανικοί Η/Υ & Πληροφορικής', lat: 38.2905, lng: 21.7920, icon: '💻', category: 'department' },
    { id: 'ece-building', name: 'ECE - Ηλεκτρονικοί Υπολογιστές & Τ.Υ.', lat: 38.2910, lng: 21.7930, icon: '💻', category: 'department' },
    { id: 'mech-aero-building', name: 'Μηχανολόγοι & Αεροναυπηγικοί Μηχανικοί', lat: 38.2925, lng: 21.7940, icon: '⚙️', category: 'department' },
    { id: 'civil-eng-building', name: 'Πολιτικοί Μηχανικοί', lat: 38.2930, lng: 21.7945, icon: '🏗️', category: 'department' },
    { id: 'chem-eng-building', name: 'Χημικοί Μηχανικοί', lat: 38.2935, lng: 21.7950, icon: '⚗️', category: 'department' },
    { id: 'med-preclinical-building', name: 'Ιατρική - Προκλινικά / Αμφιθέατρα', lat: 38.2900, lng: 21.7850, icon: '🏥', category: 'department' },
    { id: 'med-clinical-building', name: 'Ιατρική - Κλινικές / Νοσοκομείο', lat: 38.2895, lng: 21.7845, icon: '🏥', category: 'department' },
    { id: 'physics-math-building', name: 'Φυσική / Μαθηματικά', lat: 38.2918, lng: 21.7830, icon: '🔬', category: 'department' },
    { id: 'chemistry-building-north', name: 'Χημεία - Βόρειο Κτίριο', lat: 38.2922, lng: 21.7825, icon: '⚗️', category: 'department' },
    { id: 'chemistry-building-south', name: 'Χημεία - Νότιο Κτίριο', lat: 38.2928, lng: 21.7835, icon: '⚗️', category: 'department' },
    { id: 'biology-building', name: 'Βιολογία / Μαθηματικά', lat: 38.2910, lng: 21.7810, icon: '🧬', category: 'department' },
    { id: 'geology-building', name: 'Γεωλογία', lat: 38.2905, lng: 21.7805, icon: '🪨', category: 'department' },
    { id: 'education-social-work-building', name: 'Επιστήμες Εκπαίδευσης & Κοιν. Εργασίας', lat: 38.2900, lng: 21.7795, icon: '📚', category: 'department' },
    { id: 'central-library', name: 'Κεντρική Βιβλιοθήκη', lat: 38.2928, lng: 21.7858, icon: '📖', category: 'facility' },
    { id: 'conference-center', name: 'Συνεδριακό & Πολιτιστικό Κέντρο', lat: 38.2912, lng: 21.7848, icon: '🎭', category: 'facility' },
    { id: 'peace-park', name: 'Πάρκο Ειρήνης', lat: 38.2918, lng: 21.7855, icon: '🌳', category: 'facility' },
    { id: 'koukouli-dormitory', name: 'Φοιτητική Εστία Κουκούλι', lat: 38.2540, lng: 21.7330, icon: '🏠', category: 'dormitory' },
    { id: 'koukouli-admin-building', name: 'Κεντρικό Κτίριο - Διοίκηση (Κουκούλι)', lat: 38.2530, lng: 21.7320, icon: '🏛️', category: 'administration' },
    { id: 'koukouli-student-union', name: 'Φοιτητική Λέσχη Κουκούλι', lat: 38.2535, lng: 21.7325, icon: '🎭', category: 'facility' }
];

// Accurate campus locations with verified coordinates
const LOCATIONS = {};
CAMPUS_LOCATIONS.forEach(loc => {
    LOCATIONS[loc.id] = { name: loc.name, lat: loc.lat, lng: loc.lng, category: loc.category, icon: loc.icon };
});

// Coordinates for custom locations
const CUSTOM_LOCATION_COORDS = {
    'dorm': { lat: 38.2865, lng: 21.7862 }, // Φοιτητικές Εστίες Πατρών (center)
    'home': { lat: 38.2466, lng: 21.7346 }  // Κέντρο Πάτρας (fallback)
};

// Helper: normalize Greek text for fuzzy matching (remove accents, lowercase)
function normalizeGreekText(text) {
    return text.toLowerCase()
        .replace(/[άά]/g, 'α')
        .replace(/[έέ]/g, 'ε')
        .replace(/[ήή]/g, 'η')
        .replace(/[ίίϊΐ]/g, 'ι')
        .replace(/[όό]/g, 'ο')
        .replace(/[ύύϋΰ]/g, 'υ')
        .replace(/[ώώ]/g, 'ω')
        .replace(/[ς]/g, 'σ');
}

function formatLocationName(loc) {
    // Handle custom location formats
    if (loc.startsWith('🏢 Εστίες:') || loc.startsWith('dorm_custom:')) {
        const details = loc.replace('🏢 Εστίες:', '').replace('dorm_custom:', '').trim();
        return `🏢 Εστίες: ${details}`;
    }
    if (loc.startsWith('🏠 Σπίτι:') || loc.startsWith('home_custom:')) {
        const details = loc.replace('🏠 Σπίτι:', '').replace('home_custom:', '').trim();
        return `🏠 Σπίτι: ${details}`;
    }
    const locData = LOCATIONS[loc];
    return locData ? locData.name : loc;
}

function getLocationIcon(loc) {
    // Handle custom location formats
    if (loc.startsWith('🏢 Εστίες:') || loc.startsWith('dorm_custom:')) {
        return '🏢';
    }
    if (loc.startsWith('🏠 Σπίτι:') || loc.startsWith('home_custom:')) {
        return '🏠';
    }
    const locData = LOCATIONS[loc];
    return locData ? locData.icon : '📍';
}

function getLocationCoords(loc) {
    // Handle custom location formats
    if (loc.startsWith('🏢 Εστίες:') || loc.startsWith('dorm_custom:')) {
        return CUSTOM_LOCATION_COORDS.dorm;
    }
    if (loc.startsWith('🏠 Σπίτι:') || loc.startsWith('home_custom:')) {
        return CUSTOM_LOCATION_COORDS.home;
    }
    const locData = LOCATIONS[loc];
    return locData ? { lat: locData.lat, lng: locData.lng } : null;
}

document.addEventListener('DOMContentLoaded', () => {
    initMap();
    loadFeed();
    loadMyRequests();
    loadUserPoints();

    const sortSelect = document.getElementById('sort-by');
    const limitSelect = document.getElementById('results-limit');
    const distanceInput = document.getElementById('max-distance');
    const locationTypeSelect = document.getElementById('location-type');

    [sortSelect, limitSelect, distanceInput, locationTypeSelect].forEach(el => {
        if (el) el.addEventListener('change', loadFeed);
    });

    // Initialize autocomplete for user location input
    setupUserLocationAutocomplete();
});

function initMap() {
    const mapEl = document.getElementById('map');
    if (!mapEl || typeof L === 'undefined') return;

    // Initialize map centered on campus
    map = L.map('map', {
        center: CAMPUS_CENTER,
        zoom: CAMPUS_ZOOM,
        zoomControl: true,
        scrollWheelZoom: true
    });

    // Google Maps tiles (updated POIs for Greece)
    L.tileLayer('https://mt1.google.com/vt/lyrs=m&x={x}&y={y}&z={z}', {
        maxZoom: 20,
        attribution: '&copy; Google Maps'
    }).addTo(map);

    // Add custom "Center Campus" button
    const centerButton = L.Control.extend({
        options: {
            position: 'topleft'
        },
        onAdd: function(map) {
            const btn = L.DomUtil.create('button', 'leaflet-bar leaflet-control leaflet-control-custom');
            btn.innerHTML = '🎯';
            btn.title = 'Κέντρο Campus (Πανεπιστημιούπολη Πάτρας)';
            btn.style.cssText = `
                width: 36px; height: 36px; 
                background: var(--dp-color-panel, #fff); 
                border: 2px solid var(--dp-color-accent, #074ce0); 
                border-radius: 8px; 
                font-size: 16px; 
                cursor: pointer; 
                display: flex; 
                align-items: center; 
                justify-content: center;
                box-shadow: 0 2px 8px rgba(0,0,0,0.15);
                transition: all 0.2s ease;
            `;
            btn.onmouseover = () => btn.style.transform = 'scale(1.05)';
            btn.onmouseout = () => btn.style.transform = 'scale(1)';
            btn.onclick = () => {
                map.flyTo(CAMPUS_CENTER, CAMPUS_ZOOM, {
                    duration: 1.5,
                    easeLinearity: 0.25
                });
            };
            return btn;
        }
    });
    map.addControl(new centerButton());

    markersLayer = L.layerGroup().addTo(map);

    // Add custom CSS for popups
    addPopupStyles();
}

function addPopupStyles() {
    if (document.getElementById('leaflet-popup-styles')) return;
    
    const style = document.createElement('style');
    style.id = 'leaflet-popup-styles';
    style.textContent = `
        .leaflet-popup-content-wrapper {
            border-radius: 12px !important;
            box-shadow: 0 8px 32px rgba(22, 25, 31, 0.15) !important;
            padding: 0 !important;
            overflow: hidden;
        }
        .leaflet-popup-content {
            margin: 0 !important;
            font-family: 'Instrument Sans', system-ui, sans-serif !important;
            font-size: 14px !important;
            line-height: 1.5 !important;
            color: var(--dp-color-ink, #16191f) !important;
            width: 220px !important;
        }
        .leaflet-popup-tip {
            background: var(--dp-color-panel, #fff) !important;
        }
        .unibite-popup {
            padding: 12px 14px !important;
        }
        .unibite-popup-title {
            font-family: 'Host Grotesk', system-ui, sans-serif !important;
            font-weight: 700 !important;
            font-size: 15px !important;
            color: var(--dp-color-ink, #16191f) !important;
            margin: 0 0 6px !important;
            line-height: 1.2 !important;
        }
        .unibite-popup-location {
            font-size: 12px !important;
            color: var(--dp-color-ink-secondary, #454c58) !important;
            margin: 0 0 8px !important;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .unibite-popup-meta {
            font-size: 12px !important;
            color: var(--dp-color-ink-secondary, #454c58) !important;
            margin: 0 0 10px !important;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }
        .unibite-popup-meta span {
            display: flex;
            align-items: center;
            gap: 4px;
            font-weight: 500;
        }
        .unibite-popup-btn {
            width: 100% !important;
            padding: 8px 12px !important;
            background: var(--dp-color-accent, #074ce0) !important;
            color: #fff !important;
            border: none !important;
            border-radius: 8px !important;
            font-family: 'Instrument Sans', system-ui, sans-serif !important;
            font-weight: 600 !important;
            font-size: 13px !important;
            cursor: pointer !important;
            transition: background 0.15s ease, transform 0.1s ease !important;
        }
        .unibite-popup-btn:hover {
            background: var(--dp-color-accent-hover, #0638b0) !important;
            transform: translateY(-1px) !important;
        }
        .unibite-popup-btn:active {
            transform: translateY(0) !important;
        }
        .unibite-popup-badge {
            display: inline-flex;
            align-items: center;
            padding: 2px 8px;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 600;
            font-family: 'Instrument Sans', sans-serif;
        }
        .badge-available { background: #dcfce7; color: #15803d; }
        .badge-inactive { background: #f3f4f6; color: #6b7280; }
        .leaflet-container a.leaflet-popup-close-button {
            color: var(--dp-color-ink-secondary, #454c58) !important;
            font-size: 18px !important;
            top: 8px !important;
            right: 10px !important;
        }
        .leaflet-container a.leaflet-popup-close-button:hover {
            color: var(--dp-color-ink, #16191f) !important;
        }
    `;
    document.head.appendChild(style);
}

// Custom marker creation with modern design
function createCustomMarker(offer) {
    const locData = LOCATIONS[offer.pickup_location] || {};
    const isCustomDorm = offer.pickup_location.startsWith('🏢 Εστίες:') || offer.pickup_location.startsWith('dorm_custom:');
    const isCustomHome = offer.pickup_location.startsWith('🏠 Σπίτι:') || offer.pickup_location.startsWith('home_custom:');
    const isInactive = offer.display_status === 'inactive' || parseInt(offer.portions) <= 0;
    const portions = parseInt(offer.portions) || 0;
    const iconEmoji = getLocationIcon(offer.pickup_location) || '📍';
    
    // Marker color based on status and category
    let markerBg = isInactive ? '#94A3B8' : '#074ce0'; // Electric blue for active
    if (!isInactive) {
        if (isCustomDorm) markerBg = '#f59e0b'; // Amber for dorms
        else if (isCustomHome) markerBg = '#10b981'; // Emerald for home
        else if (locData.category === 'dormitory') markerBg = '#074ce0'; // Electric blue
        else if (locData.category === 'facility') markerBg = '#0891b2'; // Cyan
        else if (locData.category === 'department') markerBg = '#7c3aed'; // Violet
        else if (locData.category === 'administration') markerBg = '#dc2626'; // Red
    }

    const markerHtml = `
        <div class="unibite-marker" style="
            background: ${markerBg};
            color: #fff;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 14px;
            border: 3px solid #fff;
            box-shadow: 0 4px 16px rgba(0,0,0,0.25);
            opacity: ${offer.display_status === 'inactive' ? '0.6' : '1'};
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        ">
            ${isInactive ? '' : portions}
        </div>
    `;

    return L.divIcon({
        className: 'unibite-marker-icon',
        html: markerHtml,
        iconSize: [36, 36],
        iconAnchor: [18, 18],
        popupAnchor: [0, -18]
    });
}

function formatLocationName(loc) {
    const locData = LOCATIONS[loc];
    return locData ? locData.name : loc;
}

function getLocationIcon(loc) {
    const locData = LOCATIONS[loc];
    return locData ? locData.icon : '📍';
}

function getLocationCoords(loc) {
    const locData = LOCATIONS[loc];
    return locData ? { lat: locData.lat, lng: locData.lng } : null;
}

async function loadUserPoints() {
    const result = await UniBiteAuth.apiCall('api/get_points.php', {}, 'GET');
    if (result.success) {
        const pointsElem = document.getElementById('user-points');
        if (pointsElem) pointsElem.textContent = result.points;
    }
}

async function loadFeed() {
    const container = document.getElementById('meals-feed');
    if (!container) return;
    container.innerHTML = '<p style="color: var(--dp-color-ink-secondary);">Φόρτωση ενεργών γευμάτων...</p>';

    const sortBy = document.getElementById('sort-by')?.value || 'created_desc';
    const limit = document.getElementById('results-limit')?.value || 50;
    const maxDistance = document.getElementById('max-distance')?.value || 10;
    const locationType = document.getElementById('location-type')?.value || '';

    const params = new URLSearchParams({
        sort: sortBy,
        limit: limit,
        max_distance: maxDistance
    });

    if (locationType) {
        params.set('location_type', locationType);
    }

    if (userLocation.lat !== null && userLocation.lon !== null) {
        params.set('lat', userLocation.lat);
        params.set('lon', userLocation.lon);
    }

    try {
        const response = await fetch(`api/fetch_feed.php?${params.toString()}`, {
            method: 'GET',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' }
        });
        const result = await response.json();

        if (!result.success) {
            container.innerHTML = '<p style="color: var(--dp-color-danger);">' + (result.error || 'Σφάλμα φόρτωσης γευμάτων.') + '</p>';
            return;
        }

        renderFeed(result.offers, container);

    } catch (error) {
        console.error('loadFeed error:', error);
        container.innerHTML = '<p style="color: var(--dp-color-danger);">Σφάλμα σύνδεσης.</p>';
    }
}

function updateMapMarkers(offers) {
    if (!map || !markersLayer) return;
    markersLayer.clearLayers();

    const bounds = [];

    // Add user location marker if available
    if (userLocation.lat !== null && userLocation.lon !== null) {
        const uMarker = L.circleMarker([userLocation.lat, userLocation.lon], {
            radius: 10,
            fillColor: '#074ce0',
            color: '#FFFFFF',
            weight: 3,
            opacity: 1,
            fillOpacity: 0.9
        }).bindPopup(`
            <div class="unibite-popup">
                <div class="unibite-popup-title">📍 Η θέση μου</div>
                <div class="unibite-popup-meta">
                    <span>📍 ${userLocation.lat.toFixed(4)}, ${userLocation.lon.toFixed(4)}</span>
                </div>
            </div>
        `);
        uMarker.addTo(markersLayer);
        bounds.push([userLocation.lat, userLocation.lon]);
    }

    if (offers && offers.length > 0) {
        offers.forEach(offer => {
            let lat = parseFloat(offer.latitude);
            let lng = parseFloat(offer.longitude);
            const isCustomHome = offer.pickup_location.startsWith('🏠 Σπίτι:') || offer.pickup_location.startsWith('home_custom:');
            
            // For home_custom, use geocoding if we have the address details
            if (isCustomHome && (!isNaN(lat) && !isNaN(lng) && lat === CUSTOM_LOCATION_COORDS.home.lat && lng === CUSTOM_LOCATION_COORDS.home.lng)) {
                // Try to extract address from pickup_location for geocoding
                const details = offer.pickup_location.replace('🏠 Σπίτι:', '').replace('home_custom:', '').trim();
                // We'll use the stored lat/lng as fallback, but could geocode here if needed
                // For now, use the fallback coordinates
            }
            
            if (!isNaN(lat) && !isNaN(lng)) {
                const isInactive = offer.display_status === 'inactive' || parseInt(offer.portions) <= 0;

                const customIcon = createCustomMarker(offer);

                const locData = LOCATIONS[offer.pickup_location] || {};
                const iconEmoji = locData.icon || getLocationIcon(offer.pickup_location) || '📍';

                const popupHtml = `
                    <div class="unibite-popup">
                        <div class="unibite-popup-title">${escapeHtml(offer.title)}</div>
                        <div class="unibite-popup-location">
                            ${iconEmoji} ${escapeHtml(formatLocationName(offer.pickup_location))}
                        </div>
                        <div class="unibite-popup-meta">
                            <span>🥣 <strong>${offer.portions}</strong> μερίδες</span>
                            <span>👨‍🍳 <strong>${escapeHtml(offer.cook_name)}</strong></span>
                        </div>
                        <p style="margin: 8px 0 10px; font-size: 12px; color: var(--dp-color-ink-secondary); font-style: italic;">${escapeHtml(offer.description).substring(0, 80)}${offer.description.length > 80 ? '...' : ''}</p>
                        ${!isInactive ? `
                            <button class="unibite-popup-btn" onclick="makeRequest(${offer.id})">
                                🍽️ Αίτηση Μερίδας
                            </button>
                        ` : `
                            <span class="unibite-popup-badge badge-inactive">Εξαντλημένο</span>
                        `}
                    </div>
                `;

                const marker = L.marker([lat, lng], { 
                    icon: createCustomMarker(offer) 
                }).bindPopup(popupHtml, {
                    maxWidth: 260,
                    minWidth: 200,
                    className: 'unibite-popup-container'
                });
                marker.addTo(markersLayer);
                bounds.push([lat, lng]);
            }
        });
    }

    // Fit bounds or center on campus
    if (bounds.length > 0) {
        map.fitBounds(bounds, { padding: [40, 40], maxZoom: 17 });
    } else {
        map.setView(CAMPUS_CENTER, CAMPUS_ZOOM);
    }
}

function renderFeed(offers, container) {
    updateMapMarkers(offers);

    if (!offers || offers.length === 0) {
        container.innerHTML = '<p style="color: var(--dp-color-ink-secondary); text-align: center; padding: 2rem;">Δεν βρέθηκαν διαθέσιμα γεύματα με τα τρέχοντα φίλτρα.</p>';
        updateFilterInfo(0);
        return;
    }

    updateFilterInfo(offers.length);

    container.innerHTML = offers.map(offer => {
        const photoSrc = offer.photo ? '../' + offer.photo.replace(/^(\.\.\/)+/, '') : '';
        return `
        <div class="meal-card offer-card ${offer.display_status === 'inactive' ? 'inactive inactive-offer' : ''}" style="${offer.display_status === 'inactive' ? 'opacity: 0.6; border-left: 4px solid var(--dp-color-warning);' : 'border-left: 4px solid var(--dp-color-accent);'}">
            ${photoSrc ? '<img src="' + escapeHtml(photoSrc) + '" alt="" style="width:100%; max-height:180px; object-fit:cover; border-radius:8px 8px 0 0; margin-bottom:0.5rem;">' : ''}
            <div style="padding: 0.5rem 0.5rem 0;">
                <div class="offer-header" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
                    <h3 style="margin: 0; color: var(--dp-color-ink);">${escapeHtml(offer.title)}</h3>
                    <span class="badge badge-${offer.display_status}">${translateDisplayStatus(offer.display_status)}</span>
                </div>
                <p style="margin: 0.5rem 0; color: var(--dp-color-ink);">${escapeHtml(offer.description)}</p>
                ${offer.allergens ? '<p style="font-size:0.75rem; color:var(--dp-color-ink-secondary); margin:0.25rem 0;"><strong>Αλλεργιογόνα:</strong> ' + escapeHtml(offer.allergens) + '</p>' : ''}
                <div style="display: flex; flex-wrap: wrap; gap: 1rem; margin: 0.5rem 0; font-size: 0.85rem; color: var(--dp-color-ink-secondary);">
                    <span>🥣 <strong>${offer.portions}</strong> μερίδες</span>
                    <span>📍 ${escapeHtml(formatLocationName(offer.pickup_location))}</span>
                    <span>⏰ ${formatDateTime(offer.pickup_time)}</span>
                    ${offer.distance_km !== null && offer.distance_km !== undefined ? `<span>📏 ${offer.distance_km} km</span>` : ''}
                </div>
                <div style="display: flex; align-items: center; gap: 0.5rem; margin-top: 0.5rem;">
                    ${offer.avg_rating > 0 ? `<span style="color: var(--dp-color-warning);">⭐ ${parseFloat(offer.avg_rating).toFixed(1)} (${offer.rating_count})</span>` : ''}
                    <span style="font-size: 0.8rem; color: var(--dp-color-ink-secondary);">από ${escapeHtml(offer.cook_name)}</span>
                </div>
                <button class="btn btn-primary" style="margin-top: 1rem; width: 100%;" 
                        onclick="makeRequest(${offer.id})" 
                        ${offer.display_status === 'inactive' || offer.portions <= 0 ? 'disabled' : ''}>
                    ${offer.display_status === 'inactive' || offer.portions <= 0 ? 'Εξαντλημένο' : 'Αίτηση Μερίδας'}
                </button>
            </div>
        </div>
    `}).join('');
}

// ... rest of the functions remain the same
// [Keep all existing functions: loadMyRequests, renderMyRequests, makeRequest, cancelRequest, 
// openRatingModal, closeRatingModal, geocodeUserLocation, resetFilters, updateFilterInfo, 
// getRequestStatusColor, translateRequestStatus, translateDisplayStatus, formatDateTime, escapeHtml]

function formatLocationName(loc) {
    const locData = LOCATIONS[loc];
    return locData ? locData.name : loc;
}

function getLocationIcon(loc) {
    const locData = LOCATIONS[loc];
    return locData ? locData.icon : '📍';
}

function getLocationCoords(loc) {
    const locData = LOCATIONS[loc];
    return locData ? { lat: locData.lat, lng: locData.lng } : null;
}

async function loadMyRequests() {
    const container = document.getElementById('my-requests');
    if (!container) return;
    container.innerHTML = '<p style="color: var(--dp-color-ink-secondary);">Φόρτωση αιτημάτων...</p>';

    try {
        const result = await UniBiteAuth.apiCall('api/get_my_requests.php', {}, 'GET');

        if (!result.success) {
            container.innerHTML = '<p style="color: var(--dp-color-danger);">' + (result.error || 'Σφάλμα φόρτωσης αιτημάτων.') + '</p>';
            return;
        }

        renderMyRequests(result.requests, container);

    } catch (error) {
        console.error('loadMyRequests error:', error);
        container.innerHTML = '<p style="color: var(--dp-color-danger);">Σφάλμα σύνδεσης.</p>';
    }
}

function renderMyRequests(requests, container) {
    if (!requests || requests.length === 0) {
        container.innerHTML = '<p style="color: var(--dp-color-ink-secondary); text-align: center; padding: 2rem;">Δεν έχετε κάνει κανένα αίτημα ακόμα.</p>';
        return;
    }

    container.innerHTML = requests.map(req => {
        const isPenalized = req.penalized === 1 || req.penalized === '1';
        const showRatingButton = req.status === 'delivered' && !Number(req.is_rated) && !isPenalized;
        const showPenaltyMessage = req.status === 'delivered' && !Number(req.is_rated) && isPenalized;
        
        return `
        <div class="meal-card offer-card" style="border-left: 4px solid ${getRequestStatusColor(req.status)};">
            <div class="offer-header" style="display:flex; justify-content:space-between; align-items:center;">
                <h3 style="margin:0;">${escapeHtml(req.title)}</h3>
                <span class="badge badge-${req.status}">${translateRequestStatus(req.status)}</span>
            </div>
            <p style="margin: 0.5rem 0; color: var(--dp-color-ink-secondary); font-size: 0.9rem;">
                📍 ${escapeHtml(formatLocationName(req.pickup_location))} | ⏰ ${formatDateTime(req.pickup_time)} | 👨‍🍳 ${escapeHtml(req.cook_name)}
            </p>
            <p style="font-size: 0.85rem; color: var(--dp-color-ink-secondary);">Αιτήθηκε: ${formatDateTime(req.created_at)}</p>
            ${showRatingButton ? `
                <button class="btn btn-outline" style="margin-top: 0.5rem; width: 100%;" onclick="openRatingModal(${req.offer_id})">
                    ⭐ Αξιολόγηση Γεύματος
                </button>
            ` : ''}
            ${showPenaltyMessage ? `
                <div style="margin-top: 0.5rem; padding: 0.75rem; background: #FEF2F2; border: 1px solid #FECACA; border-radius: 8px; color: #991B1B; font-size: 0.85rem; text-align: center;">
                    ❌ <strong>Εκπρόθεσμο (Έληξε η προθεσμία και επιβλήθηκε ποινή)</strong>
                </div>
            ` : ''}
            ${req.status === 'pending' ? `
                <button class="btn btn-danger" style="margin-top: 0.5rem; width: 100%;" onclick="cancelRequest(${req.id})">
                    Ακύρωση Αιτήματος
                </button>
            ` : ''}
            ${Number(req.is_rated) ? '<p style="margin-top: 0.5rem; font-size: 0.85rem; color: var(--dp-color-success);">✅ Αξιολογημένο</p>' : ''}
        </div>
    `}).join('');
}

async function makeRequest(offerId) {
    const result = await UniBiteAuth.apiCall('api/make_request.php', { offer_id: offerId }, 'POST');
    if (result.success) {
        UniBiteAuth.showToast(result.message, 'success');
        loadFeed();
        loadMyRequests();
        loadUserPoints();
    } else {
        UniBiteAuth.showToast(result.error || 'Σφάλμα αίτησης.', 'error');
    }
}

async function cancelRequest(requestId) {
    if (!confirm('Είστε σίγουρος ότι θέλετε να ακυρώσετε αυτό το αίτημα;')) return;
    const result = await UniBiteAuth.apiCall('api/cancel_request.php', { request_id: requestId }, 'POST');
    if (result.success) {
        UniBiteAuth.showToast(result.message, 'success');
        loadMyRequests();
        loadFeed();
    } else {
        UniBiteAuth.showToast(result.error || 'Σφάλμα ακύρωσης.', 'error');
    }
}

function openRatingModal(offerId) {
    document.getElementById('rating-offer-id').value = offerId;
    document.getElementById('rating-stars').value = '5';
    document.getElementById('rating-comment').value = '';
    document.getElementById('rating-modal').classList.add('active');
}

function closeRatingModal() {
    document.getElementById('rating-modal').classList.remove('active');
}

document.getElementById('rating-form')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const offerId = document.getElementById('rating-offer-id').value;
    const rating = document.getElementById('rating-stars').value;
    const comment = document.getElementById('rating-comment').value;

    const result = await UniBiteAuth.apiCall('api/submit_rating.php', {
        offer_id: offerId,
        rating: parseInt(rating),
        comment: comment
    }, 'POST');

    if (result.success) {
        UniBiteAuth.showToast(result.message, 'success');
        closeRatingModal();
        loadMyRequests();
        loadFeed();
    } else {
        UniBiteAuth.showToast(result.error || 'Σφάλμα υποβολής αξιολόγησης.', 'error');
    }
});

document.getElementById('rating-modal')?.addEventListener('click', (e) => {
    if (e.target === e.currentTarget) closeRatingModal();
});

async function geocodeUserLocation() {
    const locationInput = document.getElementById('user-location');
    const query = locationInput?.value?.trim();
    if (!query) {
        UniBiteAuth.showToast('Γράψτε μια τοποθεσία πρώτα.', 'error');
        return;
    }

    try {
        // First check against local upatras_locations.json
        const localRes = await fetch('../upatras_locations.json');
        if (localRes.ok) {
            const localData = await localRes.json();
            const qLower = query.toLowerCase();
            const matched = (localData.locations || []).find(loc =>
                loc.name.toLowerCase().includes(qLower) || loc.id.toLowerCase().includes(qLower)
            );
            if (matched) {
                userLocation.lat = parseFloat(matched.lat);
                userLocation.lon = parseFloat(matched.lng);
                UniBiteAuth.showToast(`Τοποθεσία βρέθηκε: ${matched.name}`, 'success');
                loadFeed();
                return;
            }
        }

        const response = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query + ', Πάτρα, Ελλάδα')}&limit=1`);
        const data = await response.json();

        if (data.length > 0) {
            userLocation.lat = parseFloat(data[0].lat);
            userLocation.lon = parseFloat(data[0].lon);
            UniBiteAuth.showToast(`Τοποθεσία βρέθηκε: ${data[0].display_name}`, 'success');
            loadFeed();
        } else {
            UniBiteAuth.showToast('Δεν βρέθηκε η τοποθεσία. Δοκιμάστε άλλη.', 'error');
        }
    } catch (error) {
        console.error('Geocoding error:', error);
        UniBiteAuth.showToast('Σφάλμα αναζήτησης τοποθεσίας.', 'error');
    }
}

function resetFilters() {
    document.getElementById('user-location').value = '';
    document.getElementById('max-distance').value = '10';
    document.getElementById('sort-by').value = 'created_desc';
    document.getElementById('results-limit').value = '50';
    document.getElementById('location-type').value = '';
    userLocation = { lat: null, lon: null };
    loadFeed();
}

function updateFilterInfo(count) {
    const info = document.getElementById('filter-info');
    if (info) {
        const hasLocation = userLocation.lat !== null;
        info.textContent = `Βρέθηκαν ${count} γεύματα${hasLocation ? ' εντός ' + document.getElementById('max-distance').value + ' km από την τοποθεσία σας' : ''}.`;
    }
}

function getRequestStatusColor(status) {
    switch(status) {
        case 'pending': return 'var(--dp-color-warning)';
        case 'approved': return 'var(--dp-color-accent)';
        case 'delivered': return 'var(--dp-color-success)';
        case 'absent': return 'var(--dp-color-danger)';
        case 'rejected': return 'var(--dp-color-danger)';
        case 'cancelled': return 'var(--dp-color-ink-secondary)';
        default: return 'var(--dp-color-rule)';
    }
}

function translateRequestStatus(status) {
    switch(status) {
        case 'pending': return 'Σε Αναμονή';
        case 'approved': return 'Εγκεκριμένο';
        case 'delivered': return 'Παραδόθηκε';
        case 'absent': return 'Απών';
        case 'rejected': return 'Απορρίφθηκε';
        case 'cancelled': return 'Ακυρώθηκε';
        default: return status;
    }
}

function translateDisplayStatus(status) {
    switch(status) {
        case 'active': return 'Διαθέσιμο';
        case 'inactive': return 'Εξαντλημένο';
        default: return status;
    }
}

function formatDateTime(dateStr) {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    return date.toLocaleDateString('el-GR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}


// ==========================================================================
// User Location Autocomplete
// ==========================================================================
function setupUserLocationAutocomplete() {
    const input = document.getElementById('user-location');
    const dropdown = document.getElementById('user-location-suggestions');
    const findBtn = document.querySelector('button[onclick="geocodeUserLocation()"]');
    
    if (!input || !dropdown) return;

    let debounceTimer = null;
    let selectedIndex = -1;
    let suggestions = [];
    const PATRAS_CENTER = { lat: 38.2466, lng: 21.7346 };

    // Add has-dropdown class for CSS focus state
    input.classList.add('has-dropdown');

    // Show dropdown
    function showDropdown() {
        dropdown.style.display = 'block';
    }

    // Hide dropdown
    function hideDropdown() {
        dropdown.style.display = 'none';
        selectedIndex = -1;
    }

    // Render suggestions
    function renderSuggestions(items) {
        suggestions = items;
        if (items.length === 0) {
            dropdown.innerHTML = '<div class="no-results">Δεν βρέθηκαν προτάσεις. Προσπαθήστε με άλλη λέξη-κλειδί.</div>';
            showDropdown();
            return;
        }
        
        dropdown.innerHTML = items.map((item, index) => {
            const isCampus = item.source === 'campus';
            const icon = isCampus ? '🏛️' : '📍';
            const categoryLabel = isCampus ? 
                (item.category === 'dormitory' ? 'Φοιτητική Εστία' : 
                 item.category === 'facility' ? 'Χώρος' : 
                 item.category === 'administration' ? 'Διοίκηση' : 'Τμήμα') : 
                'Διεύθυνση';
            
            return '<div class="autocomplete-item" data-index="' + index + '" data-lat="' + item.lat + '" data-lng="' + item.lng + '" data-name="' + escapeHtml(item.displayName) + '">' +
                '<span class="autocomplete-icon">' + (isCampus ? '🏛️' : '📍') + '</span>' +
                '<div>' +
                '<span class="autocomplete-text">' + escapeHtml(item.displayName) + '</span>' +
                '<span class="autocomplete-subtext">' + categoryLabel + (item.subtext ? ' • ' + item.subtext : '') + '</span>' +
                '</div>' +
            '</div>';
        }).join('');
        
        // Add click listeners
        dropdown.querySelectorAll('.autocomplete-item').forEach(item => {
            item.addEventListener('click', () => selectSuggestion(item));
            item.addEventListener('mouseenter', () => highlightItem(item));
        });
        
        showDropdown();
    }

    // Highlight item on hover
    function highlightItem(item) {
        dropdown.querySelectorAll('.autocomplete-item').forEach(el => el.classList.remove('highlighted'));
        item.classList.add('highlighted');
        selectedIndex = parseInt(item.dataset.index);
    }

    // Select suggestion
    function selectSuggestion(item) {
        const lat = parseFloat(item.dataset.lat);
        const lng = parseFloat(item.dataset.lng);
        const name = item.dataset.name;
        
        // Set input value
        input.value = name;
        
        // Update userLocation
        userLocation.lat = lat;
        userLocation.lon = lng;
        
        // Update map marker
        updateUserMarker();
        
        // Load feed with new location
        loadFeed();
        
        hideDropdown();
        UniBiteAuth.showToast('Τοποθεσία ορίστηκε: ' + name, 'success');
    }

    // Update user marker on map
    function updateUserMarker() {
        if (!map || !markersLayer) return;
        
        // Remove existing user marker
        markersLayer.eachLayer(layer => {
            if (layer._isUserMarker) {
                markersLayer.removeLayer(layer);
            }
        });
        
        if (userLocation.lat !== null && userLocation.lon !== null) {
            const uMarker = L.circleMarker([userLocation.lat, userLocation.lon], {
                radius: 10,
                fillColor: '#074ce0',
                color: '#FFFFFF',
                weight: 3,
                opacity: 1,
                fillOpacity: 0.9
            }).bindPopup('<div class="unibite-popup"><div class="unibite-popup-title">📍 Η θέση μου</div><div class="unibite-popup-meta"><span>📍 ' + userLocation.lat.toFixed(4) + ', ' + userLocation.lon.toFixed(4) + '</span></div></div>');
            uMarker._isUserMarker = true;
            uMarker.addTo(markersLayer);
        }
    }

    // Debounced search
    function search(query) {
        if (query.length < 2) {
            hideDropdown();
            return;
        }
        
        dropdown.innerHTML = '<div class="loading">Αναζήτηση τοποθεσιών...</div>';
        showDropdown();
        
        const normalizedQuery = normalizeGreekText(query);
        
        // 1. Search campus locations first
        const campusResults = CAMPUS_LOCATIONS.filter(loc => {
            const normalizedName = normalizeGreekText(loc.name);
            const normalizedId = normalizeGreekText(loc.id);
            return normalizedName.includes(normalizedQuery) || normalizedId.includes(normalizedQuery);
        }).slice(0, 5).map(loc => ({
            lat: loc.lat,
            lng: loc.lng,
            displayName: loc.name,
            subtext: '',
            source: 'campus',
            category: loc.category
        }));
        
        // 2. Search Nominatim for streets/addresses
        const searchQuery = query.toLowerCase().includes('πάτρα') || query.toLowerCase().includes('patra') 
            ? query 
            : query + ', Πάτρα, Ελλάδα';
        
        const nominatimUrl = 'https://nominatim.openstreetmap.org/search?format=json&addressdetails=1&limit=5&countrycodes=gr&accept-language=el&q=' + encodeURIComponent(query + (query.toLowerCase().includes('πάτρα') || query.toLowerCase().includes('patra') ? '' : ', Πάτρα, Ελλάδα'));
        
        fetch(nominatimUrl)
            .then(response => response.json())
            .then(data => {
                // Filter out campus-like results to avoid duplicates
                const streetResults = (data || []).filter(item => {
                    const itemNormalized = normalizeGreekText(item.display_name || '');
                    // Check if it's not already in campus results
                    return !campusResults.some(c => normalizeGreekText(c.displayName).includes(itemNormalized) || normalizeGreekText(c.id).includes(itemNormalized));
                }).slice(0, 5).map(item => {
                    const address = item.address || {};
                    const road = address.road || address.pedestrian || address.footway || address.neighbourhood || '';
                    const houseNumber = address.house_number || '';
                    const suburb = address.suburb || address.city_district || '';
                    const city = address.city || address.town || address.municipality || '';
                    const postcode = address.postcode || '';
                    
                    let displayName = road;
                    if (houseNumber) displayName += ' ' + houseNumber;
                    if (suburb && suburb !== city) displayName += ', ' + suburb;
                    if (city) displayName += ', ' + city;
                    if (postcode) displayName += ' ' + postcode;
                    
                    // If user typed a number but API didn't return it, preserve it
                    if (houseNumber === '' && query.match(/\d+/)) {
                        const numMatch = query.match(/\d+/);
                        if (numMatch) displayName += ' ' + numMatch[0];
                    }
                    
                    let subtext = '';
                    if (suburb && suburb !== city) subtext = suburb;
                    if (city) subtext += (subtext ? ', ' : '') + city;
                    if (postcode) subtext += ' ' + postcode;
                    
                    return {
                        lat: parseFloat(item.lat),
                        lng: parseFloat(item.lon),
                        displayName: displayName || item.display_name,
                        subtext: subtext,
                        source: 'nominatim'
                    };
                });
                
                // Combine results: campus first, then streets
                const allResults = [...campusResults, ...streetResults].slice(0, 8);
                
                // Deduplicate by displayName (keep first occurrence)
                const seen = new Set();
                const dedupedResults = allResults.filter(item => {
                    const key = item.displayName;
                    if (seen.has(key)) {
                        return false;
                    }
                    seen.add(key);
                    return true;
                });
                
                renderSuggestions(dedupedResults);
            })
            .catch(() => {
                // If Nominatim fails, just show campus results
                renderSuggestions(campusResults.slice(0, 8));
            });
    }

    // Input event with debounce
    input.addEventListener('input', (e) => {
        clearTimeout(debounceTimer);
        const query = e.target.value.trim();
        debounceTimer = setTimeout(() => search(query), 250);
    });

    // Focus to show existing suggestions if any
    input.addEventListener('focus', () => {
        if (input.value.trim().length >= 2 && suggestions.length > 0) {
            showDropdown();
        }
    });

    // Keyboard navigation
    input.addEventListener('keydown', (e) => {
        if (dropdown.style.display === 'none') return;
        
        const items = dropdown.querySelectorAll('.autocomplete-item');
        if (items.length === 0) return;
        
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            selectedIndex = Math.min(selectedIndex + 1, items.length - 1);
            items.forEach((item, i) => item.classList.toggle('highlighted', i === selectedIndex));
            items[selectedIndex]?.scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            selectedIndex = Math.max(selectedIndex - 1, -1);
            items.forEach((item, i) => item.classList.toggle('highlighted', i === selectedIndex));
            if (selectedIndex >= 0) items[selectedIndex]?.scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (selectedIndex >= 0) {
                selectSuggestion(items[selectedIndex]);
            } else if (input.value.trim().length >= 2) {
                // Trigger geocoding with current input
                geocodeUserLocation();
            }
        } else if (e.key === 'Escape') {
            hideDropdown();
        }
    });

    // Click outside to close
    document.addEventListener('click', (e) => {
        if (!input.contains(e.target) && !dropdown.contains(e.target) && e.target !== findBtn) {
            hideDropdown();
        }
    });

    // Handle "Find" button click - use first suggestion or geocode
    if (findBtn) {
        findBtn.addEventListener('click', () => {
            if (suggestions.length > 0) {
                selectSuggestion(dropdown.querySelector('.autocomplete-item'));
            } else {
                geocodeUserLocation();
            }
        });
    }
}

function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str).replace(/[&<>"']/g, function(tag) {
        const chars = {
            '&': '&',
            '<': '<',
            '>': '>',
            '"': '"',
            "'": "'"
        };
        return chars[tag] || tag;
    });
}