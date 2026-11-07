// Consumer/js/consumer.js

let map = null;
let markersLayer = null;
let userMarker = null;
let userLocation = { lat: null, lon: null };

const LOCATION_LABELS = {
    'fep-building-a': 'ΦΕΠ - Κτίριο Α (Κοιτώνες)',
    'fep-building-b': 'ΦΕΠ - Κτίριο Β (Κοιτώνες)',
    'fep-building-c': 'ΦΕΠ - Κτίριο Γ (Κοιτώνες)',
    'fep-building-z': 'ΦΕΠ - Κτίριο Ζ (Κοιτώνες)',
    'fep-building-h': 'ΦΕΠ - Κτίριο Η (Κοιτώνες)',
    'fep-building-th': 'ΦΕΠ - Κτίριο Θ (Κοιτώνες)',
    'fep-canteen': 'Φοιτητική Λέσχη / Εστιατόριο Ρίου',
    'admin-building-a': 'Κτίριο Α - Διοίκηση / Πρυτανεία',
    'building-b': 'Κτίριο Β - Πολυτεχνική / Αρχιτέκτονες',
    'ceid-building': 'CEID - Μηχανικοί Η/Υ & Πληροφορικής',
    'ece-building': 'ECE - Ηλεκτρολόγοι Μηχανικοί & Τ.Υ.',
    'mech-aero-building': 'Μηχανολόγοι & Αεροναυπηγοί Μηχανικοί',
    'civil-eng-building': 'Πολιτικοί Μηχανικοί',
    'chem-eng-building': 'Χημικοί Μηχανικοί',
    'med-preclinical-building': 'Ιατρική - Προκλινικά / Αμφιθέατρα',
    'med-clinical-building': 'Ιατρική - Κλινικές / Νοσοκομείο',
    'physics-math-building': 'Φυσική / Μαθηματικά',
    'chemistry-building-north': 'Χημεία - Βόρειο Κτίριο',
    'chemistry-building-south': 'Χημεία - Νότιο Κτίριο',
    'biology-building': 'Βιολογία / Μαθηματικά',
    'geology-building': 'Γεωλογία',
    'education-social-work-building': 'Επιστήμες Εκπαίδευσης & Κοιν. Εργασίας',
    'central-library': 'Κεντρική Βιβλιοθήκη',
    'conference-center': 'Συνεδριακό & Πολιτιστικό Κέντρο',
    'peace-park': 'Πάρκο Ειρήνης',
    'koukouli-dormitory': 'Φοιτητική Εστία Κουκούλι',
    'koukouli-admin-building': 'Κεντρικό Κτίριο - Διοίκηση (Κουκούλι)',
    'koukouli-student-union': 'Φοιτητική Λέσχη Κουκούλι'
};

function formatLocationName(loc) {
    return LOCATION_LABELS[loc] || loc;
}

document.addEventListener('DOMContentLoaded', () => {
    initMap();
    loadFeed();
    loadMyRequests();
    loadUserPoints();

    const sortSelect = document.getElementById('sort-by');
    const limitSelect = document.getElementById('results-limit');
    const distanceInput = document.getElementById('max-distance');

    [sortSelect, limitSelect, distanceInput].forEach(el => {
        if (el) el.addEventListener('change', loadFeed);
    });
});

function initMap() {
    const mapEl = document.getElementById('map');
    if (!mapEl || typeof L === 'undefined') return;

    map = L.map('map').setView([38.2918, 21.7855], 14);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);
    markersLayer = L.layerGroup().addTo(map);
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
    container.innerHTML = '<p style="color: var(--text-muted);">Φόρτωση ενεργών γευμάτων...</p>';

    const sortBy = document.getElementById('sort-by')?.value || 'created_desc';
    const limit = document.getElementById('results-limit')?.value || 50;
    const maxDistance = document.getElementById('max-distance')?.value || 10;

    const params = new URLSearchParams({
        sort: sortBy,
        limit: limit,
        max_distance: maxDistance
    });

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
            container.innerHTML = '<p style="color: var(--danger-color);">' + (result.error || 'Σφάλμα φόρτωσης γευμάτων.') + '</p>';
            return;
        }

        renderFeed(result.offers, container);

    } catch (error) {
        console.error('loadFeed error:', error);
        container.innerHTML = '<p style="color: var(--danger-color);">Σφάλμα σύνδεσης.</p>';
    }
}

function updateMapMarkers(offers) {
    if (!map || !markersLayer) return;
    markersLayer.clearLayers();

    const bounds = [];

    if (userLocation.lat !== null && userLocation.lon !== null) {
        const uMarker = L.circleMarker([userLocation.lat, userLocation.lon], {
            radius: 8,
            fillColor: '#2563EB',
            color: '#FFFFFF',
            weight: 2,
            opacity: 1,
            fillOpacity: 0.9
        }).bindPopup('<b>📍 Η θέση μου</b>');
        uMarker.addTo(markersLayer);
        bounds.push([userLocation.lat, userLocation.lon]);
    }

    if (offers && offers.length > 0) {
        offers.forEach(offer => {
            const lat = parseFloat(offer.latitude);
            const lng = parseFloat(offer.longitude);
            if (!isNaN(lat) && !isNaN(lng)) {
                const isInactive = offer.display_status === 'inactive' || parseInt(offer.portions) <= 0;
                const markerColor = isInactive ? '#94A3B8' : '#EA580C';

                const customIcon = L.divIcon({
                    className: 'custom-meal-pin',
                    html: `<div style="background:${markerColor}; color:#fff; width:30px; height:30px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:bold; border:2px solid #fff; box-shadow:0 2px 6px rgba(0,0,0,0.35); opacity:${isInactive ? '0.6' : '1'};">${offer.portions}</div>`,
                    iconSize: [30, 30],
                    iconAnchor: [15, 15]
                });

                const popupHtml = `
                    <div style="min-width: 180px;">
                        <h4 style="margin:0 0 4px; color:#0F172A;">${escapeHtml(offer.title)}</h4>
                        <p style="margin:0 0 4px; font-size:0.85rem; color:#475569;">📍 ${escapeHtml(formatLocationName(offer.pickup_location))}</p>
                        <p style="margin:0 0 6px; font-size:0.85rem;">🥣 Μερίδες: <strong>${offer.portions}</strong> | 👨‍🍳 ${escapeHtml(offer.cook_name)}</p>
                        ${!isInactive ? `<button onclick="makeRequest(${offer.id})" style="width:100%; padding:5px 8px; background:#EA580C; color:#fff; border:none; border-radius:6px; cursor:pointer; font-weight:600;">Αίτηση Μερίδας</button>` : `<span style="color:#DC2626; font-size:0.8rem; font-weight:600;">Εξαντλημένο</span>`}
                    </div>
                `;

                const marker = L.marker([lat, lng], { icon: customIcon }).bindPopup(popupHtml);
                marker.addTo(markersLayer);
                bounds.push([lat, lng]);
            }
        });
    }

    if (bounds.length > 0) {
        map.fitBounds(bounds, { padding: [35, 35], maxZoom: 16 });
    }
}

function renderFeed(offers, container) {
    updateMapMarkers(offers);

    if (!offers || offers.length === 0) {
        container.innerHTML = '<p style="color: var(--text-muted); text-align: center; padding: 2rem;">Δεν βρέθηκαν διαθέσιμα γεύματα με τα τρέχοντα φίλτρα.</p>';
        updateFilterInfo(0);
        return;
    }

    updateFilterInfo(offers.length);

    container.innerHTML = offers.map(offer => {
        const photoSrc = offer.photo ? '../' + offer.photo.replace(/^(\.\.\/)+/, '') : '';
        return `
        <div class="meal-card offer-card ${offer.display_status === 'inactive' ? 'inactive inactive-offer' : ''}" style="${offer.display_status === 'inactive' ? 'opacity: 0.6; border-left: 4px solid var(--warning-color);' : 'border-left: 4px solid var(--primary-color);'}">
            ${photoSrc ? '<img src="' + escapeHtml(photoSrc) + '" alt="" style="width:100%; max-height:180px; object-fit:cover; border-radius:8px 8px 0 0; margin-bottom:0.5rem;">' : ''}
            <div style="padding: 0.5rem 0.5rem 0;">
                <div class="offer-header" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
                    <h3 style="margin: 0; color: var(--secondary-color);">${escapeHtml(offer.title)}</h3>
                    <span class="badge badge-${offer.display_status}">${translateDisplayStatus(offer.display_status)}</span>
                </div>
                <p style="margin: 0.5rem 0; color: var(--text-color);">${escapeHtml(offer.description)}</p>
                ${offer.allergens ? '<p style="font-size:0.75rem; color:var(--text-muted); margin:0.25rem 0;"><strong>Αλλεργιογόνα:</strong> ' + escapeHtml(offer.allergens) + '</p>' : ''}
                <div style="display: flex; flex-wrap: wrap; gap: 1rem; margin: 0.5rem 0; font-size: 0.85rem; color: var(--text-muted);">
                    <span>🥣 <strong>${offer.portions}</strong> μερίδες</span>
                    <span>📍 ${escapeHtml(formatLocationName(offer.pickup_location))}</span>
                    <span>⏰ ${formatDateTime(offer.pickup_time)}</span>
                    ${offer.distance_km !== null && offer.distance_km !== undefined ? `<span>📏 ${offer.distance_km} km</span>` : ''}
                </div>
                <div style="display: flex; align-items: center; gap: 0.5rem; margin-top: 0.5rem;">
                    ${offer.avg_rating > 0 ? `<span style="color: var(--warning-color);">⭐ ${parseFloat(offer.avg_rating).toFixed(1)} (${offer.rating_count})</span>` : ''}
                    <span style="font-size: 0.8rem; color: var(--text-muted);">από ${escapeHtml(offer.cook_name)}</span>
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

async function loadMyRequests() {
    const container = document.getElementById('my-requests');
    if (!container) return;
    container.innerHTML = '<p style="color: var(--text-muted);">Φόρτωση αιτημάτων...</p>';

    try {
        const result = await UniBiteAuth.apiCall('api/get_my_requests.php', {}, 'GET');

        if (!result.success) {
            container.innerHTML = '<p style="color: var(--danger-color);">' + (result.error || 'Σφάλμα φόρτωσης αιτημάτων.') + '</p>';
            return;
        }

        renderMyRequests(result.requests, container);

    } catch (error) {
        console.error('loadMyRequests error:', error);
        container.innerHTML = '<p style="color: var(--danger-color);">Σφάλμα σύνδεσης.</p>';
    }
}

function renderMyRequests(requests, container) {
    if (!requests || requests.length === 0) {
        container.innerHTML = '<p style="color: var(--text-muted); text-align: center; padding: 2rem;">Δεν έχετε κάνει κανένα αίτημα ακόμα.</p>';
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
            <p style="margin: 0.5rem 0; color: var(--text-muted); font-size: 0.9rem;">
                📍 ${escapeHtml(formatLocationName(req.pickup_location))} | ⏰ ${formatDateTime(req.pickup_time)} | 👨‍🍳 ${escapeHtml(req.cook_name)}
            </p>
            <p style="font-size: 0.85rem; color: var(--text-muted);">Αιτήθηκε: ${formatDateTime(req.created_at)}</p>
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
            ${Number(req.is_rated) ? '<p style="margin-top: 0.5rem; font-size: 0.85rem; color: var(--success-color);">✅ Αξιολογημένο</p>' : ''}
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
        case 'pending': return 'var(--warning-color)';
        case 'approved': return 'var(--primary-color)';
        case 'delivered': return 'var(--success-color)';
        case 'absent': return 'var(--danger-color)';
        case 'rejected': return 'var(--danger-color)';
        case 'cancelled': return 'var(--text-muted)';
        default: return 'var(--border-color)';
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

function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str).replace(/[&<>"']/g, function(tag) {
        const chars = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        };
        return chars[tag] || tag;
    });
}
