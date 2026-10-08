// Consumer/js/consumer.js

let map;
let markers = [];
let currentRatingOfferId = null;
let userLocation = null; // {lat, lon, address}
let locationMap = {}; // ID -> name mapping from upatras_locations.json

document.addEventListener('DOMContentLoaded', () => {
    initMap();
    loadUserPoints();
    loadLocationMap().then(() => {
        loadFeed();
        loadMyRequests();
    });
});

async function loadUserPoints() {
    const result = await UniBiteAuth.apiCall('api/get_points.php', {}, 'GET');
    if (result.success) {
        const pointsElem = document.getElementById('user-points');
        if (pointsElem) pointsElem.textContent = result.points;
    }
}

function initMap() {
    try {
        map = L.map('map').setView([37.983810, 23.727539], 14);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);
    } catch (e) {
        console.error('Map initialization failed:', e);
    }
}

async function loadLocationMap() {
    try {
        const response = await fetch('../upatras_locations.json');
        if (response.ok) {
            const data = await response.json();
            data.locations.forEach(loc => {
                locationMap[loc.id] = loc.name;
            });
        }
    } catch (e) {
        console.warn('Could not load location map:', e);
    }
}

function getLocationName(locationId) {
    return locationMap[locationId] || locationId;
}

function getFilterParams() {
    const params = new URLSearchParams();
    const sortBy = document.getElementById('sort-by')?.value || 'created_desc';
    const limit = document.getElementById('results-limit')?.value || 50;

    params.set('sort', sortBy);
    params.set('limit', limit);

    if (userLocation) {
        params.set('lat', userLocation.lat);
        params.set('lon', userLocation.lon);
        const maxDistance = document.getElementById('max-distance')?.value || 10;
        params.set('max_distance', maxDistance);
    }

    return params;
}

async function loadFeed() {
    const params = getFilterParams();
    const queryString = params.toString();
    const url = 'api/fetch_feed.php' + (queryString ? '?' + queryString : '');

    const result = await UniBiteAuth.apiCall(url, {}, 'GET');
    const container = document.getElementById('meals-feed');
    if (!container) return;

    if (!result.success) {
        container.innerHTML = '<p style="color: var(--danger-color);">' + (result.error || 'Σφάλμα φόρτωσης ροής.') + '</p>';
        return;
    }

    // Safe map cleanup
    try {
        if (markers && map) {
            markers.forEach(m => map.removeLayer(m));
        }
    } catch (e) {
        console.warn('Map cleanup failed:', e);
    }
    markers = [];

    if (result.offers.length === 0) {
        container.innerHTML = '<p style="color: var(--text-muted);">Δεν υπάρχουν διαθέσιμα γεύματα που να ταιριάζουν στα φίλτρα.</p>';
        updateFilterInfo(0);
        return;
    }

    updateFilterInfo(result.offers.length);

    container.innerHTML = result.offers.map(offer => {
        const locationName = getLocationName(offer.pickup_location);
        // Create marker if map is available
        if (offer.latitude && offer.longitude && map) {
            try {
                const marker = L.marker([offer.latitude, offer.longitude]).addTo(map)
                    .bindPopup('<b>' + escapeHtml(offer.title) + '</b><br>' + escapeHtml(locationName) + '<br>Μερίδες: ' + offer.portions + (offer.distance_km ? '<br>Απόσταση: ' + offer.distance_km + ' km' : ''));
                markers.push(marker);
            } catch (e) {
                console.warn('Marker creation failed:', e);
            }
        }

        const isInactive = offer.display_status === 'inactive' || offer.portions <= 0;
        const canRate = offer.can_rate > 0 && offer.already_rated === 0;
        const cardClass = isInactive ? 'meal-card inactive' : 'meal-card';
        const badgeClass = isInactive ? 'badge-inactive' : 'badge-active';
        const badgeText = isInactive ? 'Εξαντλημένο' : offer.portions + ' μερίδες';
        const distanceInfo = offer.distance_km !== undefined ? '<br>📏 Απόσταση: <strong>' + offer.distance_km + ' km</strong>' : '';
        const ratingDisplay = offer.rating_count > 0 ? '⭐ ' + parseFloat(offer.avg_rating).toFixed(1) : '-';

        return '\n            <div class="' + cardClass + '" id="meal-' + offer.id + '">\n                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">\n                    <h3 style="color: var(--secondary-color);">' + escapeHtml(offer.title) + '</h3>\n                    <span class="badge ' + badgeClass + '">' + badgeText + '</span>\n                </div>\n                ' + (offer.allergens ? '<p style="font-size:0.8rem; color:var(--text-muted); margin:0.5rem 0;"><strong>Αλλεργιογόνα:</strong> ' + escapeHtml(offer.allergens) + '</p>' : '') + '\n                <p style="margin-bottom: 0.5rem;">' + escapeHtml(offer.description) + '</p>\n                <p style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 0.5rem;">\n                    👨‍🍳 Μάγειρας: <strong>' + escapeHtml(offer.cook_name) + '</strong> (' + ratingDisplay + ')<br>\n                    📍 Σημείο: <strong>' + escapeHtml(locationName) + '</strong>' + distanceInfo + '<br>\n                    ⏰ Παραλαβή έως: ' + offer.pickup_time + '\n                </p>\n                <div style="display: flex; gap: 0.5rem; margin-top: 1rem;">\n                    ' + (!isInactive ? '<button class="btn btn-primary" onclick="makeRequest(' + offer.id + ')">Δέσμευση Μερίδας</button>' : '<button class="btn btn-outline" disabled style="opacity: 0.5;">Δεν υπάρχουν μερίδες</button>') + '\n                </div>\n            </div>\n        ';
    }).join('');

    // Fit map to markers if available
    try {
        if (markers.length > 0 && map) {
            const group = new L.featureGroup(markers);
            map.fitBounds(group.getBounds().pad(0.1));
        }
    } catch (e) {
        console.warn('Map fitBounds failed:', e);
    }
}

function updateFilterInfo(count) {
    const infoEl = document.getElementById('filter-info');
    if (!infoEl) return;

    let text = 'Βρέθηκαν <strong>' + count + '</strong> γεύματα';
    if (userLocation) {
        const maxDist = document.getElementById('max-distance')?.value || 10;
        text += ' εντός ' + maxDist + ' km από την θέση σας';
    }
    infoEl.innerHTML = text;
}

function resetFilters() {
    document.getElementById('user-location').value = '';
    document.getElementById('max-distance').value = '10';
    document.getElementById('sort-by').value = 'created_desc';
    document.getElementById('results-limit').value = '50';
    userLocation = null;
    loadFeed();
}

async function geocodeUserLocation() {
    const locationInput = document.getElementById('user-location');
    const location = locationInput.value.trim();
    if (!location) {
        UniBiteAuth.showToast('Εισάγετε μια τοποθεσία', 'error');
        return;
    }

    try {
        const response = await fetch('https://nominatim.openstreetmap.org/search?format=json&q=' + encodeURIComponent(location + ', Αθήνα, Ελλάδα') + '&limit=1');
        const data = await response.json();
        if (data.length > 0) {
            userLocation = {
                lat: parseFloat(data[0].lat),
                lon: parseFloat(data[0].lon),
                address: data[0].display_name
            };
            locationInput.value = data[0].display_name.split(',')[0];
            UniBiteAuth.showToast('Τοποθεσία βρέθηκε: ' + userLocation.address.split(',')[0], 'success');
            loadFeed();
        } else {
            UniBiteAuth.showToast('Δεν βρέθηκε η τοποθεσία', 'error');
        }
    } catch (e) {
        console.error('Geocoding error:', e);
        UniBiteAuth.showToast('Σφάλμα geocoding', 'error');
    }
}

async function loadMyRequests() {
    const result = await UniBiteAuth.apiCall('api/get_my_requests.php', {}, 'GET');
    const container = document.getElementById('my-requests');
    if (!container) return;

    if (!result.success) {
        container.innerHTML = '<p style="color: var(--danger-color);">' + (result.error || 'Σφάλμα φόρτωσης αιτημάτων.') + '</p>';
        return;
    }

    if (result.requests.length === 0) {
        container.innerHTML = '<p style="color: var(--text-muted);">Δεν έχετε κάνει καμία αίτηση ακόμα.</p>';
        return;
    }

    container.innerHTML = result.requests.map(req => {
        const statusClass = 'badge-' + req.status;
        const statusLabel = translateStatus(req.status);
        let actions = '';

        if (req.status === 'approved') {
            actions = '<button class="btn btn-primary" style="padding: 0.3rem 0.6rem; font-size: 0.8rem; background-color: var(--success-color);" onclick="markAsPickedUp(' + req.id + ')">Παραλάβει</button>';
        } else if (req.status === 'pending') {
            actions = '<button class="btn btn-danger" style="padding: 0.3rem 0.6rem; font-size: 0.8rem;" onclick="cancelRequest(' + req.id + ')">Ακύρωση</button>';
        }

        const locationName = getLocationName(req.pickup_location);

        return '\n            <div class="meal-card" style="border-left: 4px solid var(--primary-color);">\n                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">\n                    <h3 style="color: var(--secondary-color);">' + escapeHtml(req.title) + '</h3>\n                    <span class="badge ' + statusClass + '">' + statusLabel + '</span>\n                </div>\n                <p style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 0.5rem;">\n                    👨‍🍳 Μάγειρας: <strong>' + escapeHtml(req.cook_name) + '</strong><br>\n                    📍 Σημείο: <strong>' + escapeHtml(locationName) + '</strong><br>\n                    ⏰ Παραλαβή: ' + req.pickup_time + '\n                </p>\n                <div style="display: flex; gap: 0.5rem; margin-top: 1rem;">\n                    ' + actions + '\n                    ' + (req.status === 'delivered' && !req.rated ? '<button class="btn btn-outline" type="button" onclick="openRatingModal(event, ' + req.offer_id + ')">Αξιολόγηση</button>' : '') + '\n                </div>\n            </div>\n        ';
    }).join('');
}

async function makeRequest(offerId) {
    const result = await UniBiteAuth.apiCall('api/make_request.php', { offer_id: offerId }, 'POST');
    if (result.success) {
        UniBiteAuth.showToast(result.message, 'success');
        loadMyRequests();
        loadFeed();
        loadUserPoints();
    } else {
        UniBiteAuth.showToast(result.error || 'Σφάλμα δέσμευσης.', 'error');
    }
}

async function cancelRequest(requestId) {
    if (!confirm('Είστε σίγουρος ότι θέλετε να ακυρώσετε το αίτημα;')) return;
    const result = await UniBiteAuth.apiCall('api/cancel_request.php', { request_id: requestId }, 'POST');
    if (result.success) {
        UniBiteAuth.showToast(result.message, 'success');
        loadMyRequests();
        loadFeed();
        loadUserPoints();
    } else {
        UniBiteAuth.showToast(result.error || 'Σφάλμα ακύρωσης.', 'error');
    }
}

async function markAsPickedUp(requestId) {
    UniBiteAuth.showToast('Η παραλαβή επιβεβαιώνεται από τον μάγειρα.', 'success');
}

window.openRatingModal = function (event, offerId) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }

    currentRatingOfferId = offerId;
    const offerInput = document.getElementById('rating-offer-id');
    const starsInput = document.getElementById('rating-stars');
    const commentInput = document.getElementById('rating-comment');

    if (offerInput) offerInput.value = offerId;
    if (starsInput) starsInput.value = '5';
    if (commentInput) commentInput.value = '';

    const modal = document.getElementById('dashboard-rating-modal');
    if (modal) {
        modal.classList.add('active');
        // Αποτροπή άμεσου κλεισίματος από άλλα click events
        modal.dataset.justOpened = 'true';
        setTimeout(() => { modal.dataset.justOpened = 'false'; }, 200);
    }
};

function closeRatingModal() {
    const modal = document.getElementById('dashboard-rating-modal');
    if (!modal) return;
    
    if (modal.dataset.justOpened === 'true') {
        console.warn('Αποτράπηκε το άμεσο κλείσιμο του modal.');
        return; 
    }

    modal.classList.remove('active');
    currentRatingOfferId = null;
}

document.getElementById('rating-form')?.addEventListener('submit', async function (e) {
    e.preventDefault();
    const offerId = document.getElementById('rating-offer-id').value;
    const rating = parseInt(document.getElementById('rating-stars').value);
    const comment = document.getElementById('rating-comment').value.trim();

    const result = await UniBiteAuth.apiCall('api/submit_rating.php', { offer_id: offerId, rating, comment }, 'POST');
    if (result.success) {
        UniBiteAuth.showToast(result.message, 'success');
        closeRatingModal();
        loadFeed();
        loadMyRequests();
        loadUserPoints();
    } else {
        UniBiteAuth.showToast(result.error || 'Σφάλμα αξιολόγησης.', 'error');
    }
});

function translateStatus(status) {
    switch (status) {
        case 'pending': return 'Σε Αναμονή';
        case 'approved': return 'Εγκεκριμένο';
        case 'rejected': return 'Απορριφθέν';
        case 'delivered': return 'Παραδοθέν';
        case 'absent': return 'Απουσία';
        case 'cancelled': return 'Ακυρωμένο';
        default: return status;
    }
}

function escapeHtml(str) {
    return str.replace(/[&<>'"]/g, function (tag) {
        if (tag === '&') return '&';
        if (tag === '<') return '<';
        if (tag === '>') return '>';
        if (tag === "'") return '&apos;';
        if (tag === '"') return '"';
        return tag;
    });
}