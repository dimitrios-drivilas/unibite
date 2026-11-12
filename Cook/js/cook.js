// Cook/js/cook.js

// Global references for Leaflet Map Pin Pickers
let homeMapInstance = null;
let homeMarkerInstance = null;
let editHomeMapInstance = null;
let editHomeMarkerInstance = null;

document.addEventListener('DOMContentLoaded', () => {
    loadMyOffers();
    loadCookStats();
    loadUserPoints();
    setupTabs();
    setupPickupTimeLimits();

    const form = document.getElementById('create-offer-form');
    if (form) {
        form.addEventListener('submit', handleCreateOffer);
    }

    const editForm = document.getElementById('edit-offer-form');
    if (editForm) {
        editForm.addEventListener('submit', handleEditOffer);
    }

    // Initialize allergens checkboxes
    populateAllergens('allergens-container');
    populateAllergens('edit-allergens-container');

    // Setup auto-fill for pickup location coordinates
    setupPickupLocationAutofill('pickup_location', 'coordinates');
    setupPickupLocationAutofill('edit-pickup_location', 'edit-coordinates');

    // Setup custom location field visibility & Map Pin Picker
    setupCustomLocationFields('pickup_location', 'dorm-custom-field', 'home-custom-fields', 'home-notes-field', 'pickup-hint');
    setupCustomLocationFields('edit-pickup_location', 'edit-dorm-custom-field', 'edit-home-custom-fields', 'edit-home-notes-field', 'edit-pickup-hint');
});

function setupPickupTimeLimits() {
    const now = new Date();
    const minTime = new Date(now.getTime() - now.getTimezoneOffset() * 60000).toISOString().slice(0, 16);

    const maxDate = new Date(now.getTime() + (48 * 60 * 60 * 1000));
    const maxTime = new Date(maxDate.getTime() - maxDate.getTimezoneOffset() * 60000).toISOString().slice(0, 16);

    const pickupInput = document.getElementById('pickup_time');
    if (pickupInput) { pickupInput.min = minTime; pickupInput.max = maxTime; }

    const editPickupInput = document.getElementById('edit-pickup_time');
    if (editPickupInput) { editPickupInput.min = minTime; editPickupInput.max = maxTime; }
}

async function loadUserPoints() {
    const result = await UniBiteAuth.apiCall('api/get_points.php', {}, 'GET');
    if (result.success) {
        const pointsElem = document.getElementById('user-points');
        if (pointsElem) pointsElem.textContent = result.points;
    }
}

function setupTabs() {
    const tabBtns = document.querySelectorAll('.tab-btn');
    tabBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const tab = btn.dataset.tab;

            document.querySelectorAll('.tab-btn').forEach(b => {
                b.classList.remove('active');
            });
            btn.classList.add('active');

            document.querySelectorAll('.tab-panel').forEach(panel => {
                panel.style.display = 'none';
            });
            document.getElementById('tab-' + tab).style.display = 'block';

            if (tab === 'stats') {
                loadCookStats();
            }
        });
    });
}

function formatPickupLocationDisplay(loc) {
    if (!loc) return '';
    if (loc.startsWith('dorm_custom:')) {
        return '🏢 Εστίες: ' + loc.replace('dorm_custom:', '').trim();
    }
    if (loc.startsWith('home_custom:')) {
        return '🏠 Σπίτι: ' + loc.replace('home_custom:', '').trim();
    }
    return loc;
}

async function loadCookStats() {
    const result = await UniBiteAuth.apiCall('api/get_cook_stats.php', {}, 'GET');
    if (!result.success) return;

    document.getElementById('stat-total-points').textContent = result.stats.total_points;
    document.getElementById('stat-total-offers').textContent = result.stats.total_offers;
    document.getElementById('stat-total-portions').textContent = result.stats.total_portions;
    const avgRating = result.stats.avg_rating;
    const ratingCount = result.stats.rating_count;
    const ratingVal = (avgRating !== null && avgRating !== undefined && ratingCount > 0)
        ? parseFloat(avgRating).toFixed(1)
        : '-';
    document.getElementById('stat-avg-rating').textContent = ratingVal;

    document.getElementById('stat-points-from-offers').textContent = result.stats.points_from_offers > 0 ? '+' + result.stats.points_from_offers : '0';

    const penaltiesEl = document.getElementById('stat-penalties-received');
    const penalties = result.stats.penalties_received;
    penaltiesEl.textContent = penalties;
    if (penalties > 0) {
        penaltiesEl.style.color = 'var(--dp-color-danger)';
    } else {
        penaltiesEl.style.color = 'var(--dp-color-ink)';
    }

    const container = document.getElementById('stats-offers-list');
    if (!container) return;

    if (result.offers.length === 0) {
        container.innerHTML = '<p style="color: var(--dp-color-ink-secondary);">Δεν έχετε δημιουργήσει καμία αγγελία ακόμα.</p>';
        return;
    }

    container.innerHTML = result.offers.map(offer => {
        const photoHtml = offer.photo ? '<img src="' + escapeHtml(offer.photo.replace('Cook/', '')) + '" alt="" style="width:100%; height:120px; object-fit:cover; border-radius:8px; border: var(--dp-border-hairline) solid var(--dp-color-rule);">' : '';

        const requestsHtml = (offer.ratings || []).map(req => {
            const hasRating = req.rating !== null && req.rating !== undefined && req.rating !== '';
            let ratingHtml = '';
            if (hasRating) {
                const starStr = '⭐'.repeat(parseInt(req.rating));
                const pointsStr = req.rating >= 4 ? '+2' : '+1';
                const commentHtml = (req.comment && req.comment.trim() !== '')
                    ? '<span class="rating-comment">"' + escapeHtml(req.comment) + '"</span>'
                    : '<span class="rating-comment" style="font-style: italic; opacity: 0.75;">Δεν υπάρχουν διαθέσιμα σχόλια.</span>';
                const ratingDate = req.rating_created_at ? req.rating_created_at.split('T')[0] : '';
                ratingHtml = '<div class="request-rating">' +
                    '<span class="rating-stars">' + starStr + '</span>' +
                    '<span class="rating-points">Πόντοι: ' + pointsStr + '</span>' +
                    (req.consumer_name ? '<span class="rating-consumer">από ' + escapeHtml(req.consumer_name) + '</span>' : '') +
                    commentHtml +
                    (ratingDate ? '<span class="rating-date">' + ratingDate + '</span>' : '') +
                '</div>';
            }

            return '<div class="request-item">' +
                (req.consumer_name ? '<div class="request-item-header"><span class="consumer-name">' + escapeHtml(req.consumer_name) + '</span></div>' : '') +
                ratingHtml +
            '</div>';
        }).join('');

        return '<div class="offer-card" id="stats-offer-' + offer.id + '">' +
            '<div class="offer-left">' +
                photoHtml +
                '<div class="offer-header">' +
                    '<div class="offer-header-left">' +
                        '<h3>' + escapeHtml(offer.title) + '</h3>' +
                        '<span class="badge badge-' + offer.status + '">' + translateStatus(offer.status) + '</span>' +
                    '</div>' +
                '</div>' +
                '<p>' + escapeHtml(offer.description || '') + '</p>' +
                (offer.allergens ? '<p class="offer-allergens"><strong>Αλλεργιογόνα:</strong> ' + escapeHtml(offer.allergens) + '</p>' : '') +
                '<p class="offer-meta">' +
                    '<span><strong>🥣 Μερίδες:</strong> ' + offer.portions + '</span>' +
                    '<span><strong>📍 Σημείο:</strong> ' + escapeHtml(formatPickupLocationDisplay(offer.pickup_location)) + '</span>' +
                    (offer.pickup_time ? '<span><strong>⏰</strong> ' + offer.pickup_time + '</span>' : '') +
                '</p>' +
            '</div>' +
            '<div class="offer-right">' +
                '<div class="requests-section">' +
                    '<h4>Αξιολογήσεις (' + (offer.rating_count || 0) + ')</h4>' +
                    ((offer.ratings || []).length === 0
                        ? '<p style="font-size: 0.85rem; color: var(--dp-color-ink-secondary);">Δεν υπάρχουν αξιολογήσεις ακόμα.</p>'
                        : '<div class="requests-list"><div class="requests-grid">' + requestsHtml + '</div></div>') +
                '</div>' +
            '</div>' +
        '</div>';
    }).join('');
}

const TERMINAL_STATUSES = ['completed', 'cancelled', 'expired'];

async function loadMyOffers() {
    const container = document.getElementById('offers-list');
    if (!container) return;
    container.innerHTML = '<p style="color: var(--dp-color-ink-secondary);">Φόρτωση αγγελιών...</p>';

    try {
        const result = await UniBiteAuth.apiCall('api/get_my_offers.php', {}, 'GET');

        if (!result.success) {
            container.innerHTML = '<p style="color: var(--dp-color-danger);">' + (result.error || 'Σφάλμα φόρτωσης αγγελιών. Συνδεθείτε ως μάγειρας.') + '</p>';
            return;
        }

        const visibleOffers = (result.offers || []).filter(o => o.status !== 'cancelled' && o.status !== 'expired');

        if (visibleOffers.length === 0) {
            container.innerHTML = '<p style="color: var(--dp-color-ink-secondary);">Δεν έχετε δημιουργήσει καμία αγγελία ακόμα.</p>';
            return;
        }

        container.innerHTML = visibleOffers.map(offer => {
            const photoHtml = offer.photo ? '<img src="' + escapeHtml(offer.photo.replace('Cook/', '')) + '" alt="" style="width:100%; height:120px; object-fit:cover; border-radius:8px; border: var(--dp-border-hairline) solid var(--dp-color-rule);">' : '';

            const requestsHtml = offer.requests.map(req => {
                const hasRating = req.rating !== null && req.rating !== undefined && req.rating !== '';
                const isPenalized = req.penalized == 1 || req.penalized === '1';
                const isDelivered = req.status === 'delivered';

                let ratingHtml = '';
                if (hasRating) {
                    const starStr = '⭐'.repeat(parseInt(req.rating));
                    const pointsStr = req.rating >= 4 ? '+2' : '+1';
                    const commentHtml = req.comment ? '<span class="rating-comment">"' + escapeHtml(req.comment) + '"</span>' : '';
                    const ratingDate = req.rating_created_at ? req.rating_created_at.split('T')[0] : '';
                    ratingHtml = '<div class="request-rating">' +
                        '<span class="rating-stars">' + starStr + '</span>' +
                        '<span class="rating-points">Πόντοι: ' + pointsStr + '</span>' +
                        '<span class="rating-consumer">από ' + escapeHtml(req.consumer_name) + '</span>' +
                        commentHtml +
                        (ratingDate ? '<span class="rating-date">' + ratingDate + '</span>' : '') +
                    '</div>';
                } else if (isDelivered && !isPenalized) {
                    ratingHtml = '<div class="request-rating"><span class="rating-pending">(Εκκρεμεί αξιολόγηση)</span></div>';
                } else if (isDelivered && isPenalized) {
                    ratingHtml = '<div class="request-rating"><span class="rating-penalized">(Έληξε η προθεσμία αξιολόγησης)</span></div>';
                }

                const actionsHtml = req.status === 'pending'
                    ? '<button class="btn btn-primary" style="padding: 4px 10px; font-size: 0.75rem;" onclick="handleRequest(' + req.id + ', \'approve\')">Έγκριση</button>' +
                      '<button class="btn btn-danger" style="padding: 4px 10px; font-size: 0.75rem;" onclick="handleRequest(' + req.id + ', \'reject\')">Απόρριψη</button>'
                    : (req.status === 'approved'
                        ? '<button class="btn btn-primary" style="padding: 4px 10px; font-size: 0.75rem; background-color: var(--dp-color-success);" onclick="handleRequest(' + req.id + ', \'delivered\')">Παραδόθηκε</button>' +
                          '<button class="btn btn-danger" style="padding: 4px 10px; font-size: 0.75rem;" onclick="handleRequest(' + req.id + ', \'absent\')">Απών</button>'
                        : '');

                return '<div class="request-item">' +
                    '<div class="request-item-header">' +
                        '<span class="consumer-name">' + escapeHtml(req.consumer_name) + '</span>' +
                        '<span class="request-status">' + req.status + '</span>' +
                    '</div>' +
                    '<div class="request-actions">' + actionsHtml + '</div>' +
                    ratingHtml +
                '</div>';
            }).join('');

            return '<div class="offer-card" id="offer-' + offer.id + '">' +
                '<div class="offer-left">' +
                    photoHtml +
                    '<div class="offer-header">' +
                        '<div class="offer-header-left">' +
                            '<h3>' + escapeHtml(offer.title) + '</h3>' +
                            '<span class="badge badge-' + offer.status + '">' + translateStatus(offer.status) + '</span>' +
                        '</div>' +
                        '<div class="offer-actions">' +
                            (!TERMINAL_STATUSES.includes(offer.status) ? '<button class="btn btn-outline" onclick="openEditModal(' + offer.id + ')">Επεξεργασία</button>' : '') +
                            '<button class="btn btn-outline" onclick="deleteOffer(' + offer.id + ')">Διαγραφή</button>' +
                        '</div>' +
                    '</div>' +
                    '<p>' + escapeHtml(offer.description) + '</p>' +
                    (offer.allergens ? '<p class="offer-allergens"><strong>Αλλεργιογόνα:</strong> ' + escapeHtml(offer.allergens) + '</p>' : '') +
                    '<p class="offer-meta">' +
                        '<span><strong>🥣 Μερίδες:</strong> ' + offer.portions + '</span>' +
                        '<span><strong>📍 Σημείο:</strong> ' + escapeHtml(formatPickupLocationDisplay(offer.pickup_location)) + '</span>' +
                        '<span><strong>⏰</strong> ' + offer.pickup_time + '</span>' +
                    '</p>' +
                '</div>' +
                '<div class="offer-right">' +
                    '<div class="requests-section">' +
                        '<h4>Αιτήματα Φοιτητών (' + offer.requests.length + ')</h4>' +
                        (offer.requests.length === 0
                            ? '<p style="font-size: 0.85rem; color: var(--dp-color-ink-secondary);">Κανένα αίτημα ακόμα.</p>'
                            : '<div class="requests-list"><div class="requests-grid">' + requestsHtml + '</div></div>') +
                    '</div>' +
                '</div>' +
            '</div>';
        }).join('');
    } catch (error) {
        console.error('loadMyOffers error:', error);
        container.innerHTML = '<p style="color: var(--dp-color-danger);">Σφάλμα σύνδεσης. Ελέγξτε αν είστε συνδεδεμένοι ως μάγειρας.</p>';
    }
}

async function handleCreateOffer(e) {
    e.preventDefault();

    const form = e.target;
    const formData = new FormData(form);

    const allergenCheckboxes = document.querySelectorAll('#allergens-container input[name="allergens"]:checked');
    const allergensArray = Array.from(allergenCheckboxes).map(cb => cb.value);
    formData.set('allergens', allergensArray.join(', '));

    const pickupSelect = document.getElementById('pickup_location');
    const pickupValue = pickupSelect.value;
    let finalPickupLocation = pickupValue;
    let finalLatitude = 38.2466;
    let finalLongitude = 21.7346;

    if (pickupValue === 'dorm_custom') {
        const dormBuilding = (document.getElementById('dorm-building')?.value || '').trim();
        const dormNumber = (document.getElementById('dorm-number')?.value || '').trim();
        const legacyDormRoom = (document.getElementById('dorm-room')?.value || '').trim();

        let dormRoom = '';
        if (dormBuilding && dormNumber) {
            dormRoom = `${dormBuilding}, Δωμάτιο ${dormNumber}`;
        } else if (legacyDormRoom) {
            dormRoom = legacyDormRoom;
        } else {
            UniBiteAuth.showToast('Συμπληρώστε το κτίριο και τον αριθμό δωματίου της εστίας.', 'error');
            return;
        }

        finalPickupLocation = `dorm_custom: ${dormRoom}`;
        const selectedOption = pickupSelect.options[pickupSelect.selectedIndex];
        finalLatitude = selectedOption.dataset.lat || 38.2865;
        finalLongitude = selectedOption.dataset.lng || 21.7862;
    } else if (pickupValue === 'home_custom') {
        const homeStreet = (document.getElementById('home-street')?.value || '').trim();
        const homeNumber = (document.getElementById('home-number')?.value || '').trim();
        const homeFloor = (document.getElementById('home-floor')?.value || '').trim();
        const homeBell = (document.getElementById('home-bell')?.value || '').trim();
        const homeNotes = (document.getElementById('home-notes')?.value || '').trim();
        const coordsVal = (document.getElementById('coordinates')?.value || '').trim();

        if (!homeStreet || !homeNumber) {
            UniBiteAuth.showToast('Επιλέξτε σημείο στον χάρτη ή συμπληρώστε Οδό και Αριθμό.', 'error');
            return;
        }

        let locationParts = [`${homeStreet} ${homeNumber}`];
        if (homeFloor) {
            locationParts.push(`Όροφος: ${homeFloor}`);
        }
        if (homeBell) {
            locationParts.push(`Κουδούνι: ${homeBell}`);
        }
        if (!homeFloor && !homeBell && homeNotes) {
            locationParts.push(homeNotes);
        }
        finalPickupLocation = `home_custom: ${locationParts.join(', ')}`;

        if (coordsVal && coordsVal.includes(',')) {
            const [latStr, lngStr] = coordsVal.split(',');
            finalLatitude = parseFloat(latStr.trim()) || 38.2466;
            finalLongitude = parseFloat(lngStr.trim()) || 21.7346;
        }
    } else {
        const selectedOption = pickupSelect.options[pickupSelect.selectedIndex];
        if (selectedOption && selectedOption.dataset.lat && selectedOption.dataset.lng) {
            finalLatitude = selectedOption.dataset.lat;
            finalLongitude = selectedOption.dataset.lng;
        }
    }

    formData.set('pickup_location', finalPickupLocation);
    formData.set('latitude', finalLatitude);
    formData.set('longitude', finalLongitude);

    const pickupTime = document.getElementById('pickup_time').value;
    if (!pickupTime) {
        UniBiteAuth.showToast('Η ώρα παραλαβής είναι υποχρεωτική.', 'error');
        return;
    }

    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.disabled = true;

    // Client-side image compression for photo upload
    const photoInput = document.getElementById('photo');
    if (photoInput && photoInput.files && photoInput.files[0]) {
        const originalFile = photoInput.files[0];
        if (originalFile.type.startsWith('image/')) {
            submitBtn.textContent = 'Συμπίεση εικόνας...';
            try {
                const compressedFile = await compressImage(originalFile, 1200, 1200, 0.8);
                formData.set('photo', compressedFile, compressedFile.name);
            } catch (err) {
                console.warn('Image compression failed, uploading original:', err);
            }
        }
    }

    submitBtn.textContent = 'Δημιουργία...';

    try {
        const response = await fetch('api/create_offer.php', {
            method: 'POST',
            credentials: 'include',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            UniBiteAuth.showToast(result.message, 'success');
            form.reset();
            document.getElementById('coordinates').value = '38.2466, 21.7346';
            document.getElementById('pickup_location').dispatchEvent(new Event('change'));
            loadMyOffers();
            loadCookStats();
        } else {
            UniBiteAuth.showToast(result.error || 'Σφάλμα δημιουργίας.', 'error');
        }
    } catch (err) {
        console.error(err);
        UniBiteAuth.showToast('Σφάλμα δικτύου.', 'error');
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
    }
}

function openEditModal(offerId) {
    UniBiteAuth.apiCall('api/get_my_offers.php', {}, 'GET').then(result => {
        if (result.success) {
            const offer = result.offers.find(o => o.id === offerId);
            if (offer) {
                document.getElementById('edit-offer-id').value = offer.id;
                document.getElementById('edit-title').value = offer.title;
                document.getElementById('edit-description').value = offer.description;
                document.getElementById('edit-portions').value = offer.portions;
                document.getElementById('edit-coordinates').value = (offer.latitude || 38.2466) + ', ' + (offer.longitude || 21.7346);

                if (offer.pickup_time) {
                    const cleanTime = offer.pickup_time.replace(' ', 'T').slice(0, 16);
                    const editTimeInput = document.getElementById('edit-pickup_time');
                    editTimeInput.value = cleanTime;
                    editTimeInput.min = cleanTime;
                    editTimeInput.dataset.originalTime = cleanTime;
                }
                document.getElementById('edit-status').value = offer.status;

                const statusSelect = document.getElementById('edit-status');
                const existingWarning = document.getElementById('terminal-status-warning');
                if (existingWarning) existingWarning.remove();

                function updateTerminalWarning() {
                    const selectedStatus = statusSelect.value;
                    if (TERMINAL_STATUSES.includes(selectedStatus)) {
                        if (!document.getElementById('terminal-status-warning')) {
                            const warningDiv = document.createElement('div');
                            warningDiv.id = 'terminal-status-warning';
                            warningDiv.style.cssText = 'margin-top: 0.75rem; padding: 0.75rem; background: #FEF2F2; border: 1px solid #FECACA; border-radius: 8px; color: #991B1B; font-size: 0.875rem;';
                            warningDiv.innerHTML = '⚠️ <strong>Προσοχή:</strong> Αυτή η κατάσταση είναι τερματική. Αν αποθηκεύσετε, δεν θα μπορείτε να επαναφέρετε ή να επεξεργαστείτε ξανά την αγγελία.';
                            statusSelect.parentNode.insertBefore(warningDiv, statusSelect.nextSibling);
                        }
                    } else {
                        const w = document.getElementById('terminal-status-warning');
                        if (w) w.remove();
                    }
                }

                updateTerminalWarning();
                statusSelect.onchange = updateTerminalWarning;

                const existingAllergens = offer.allergens ? offer.allergens.split(',').map(a => a.trim()) : [];
                populateAllergens('edit-allergens-container', existingAllergens);

                parsePickupLocationForEdit(offer.pickup_location, offer.latitude, offer.longitude);

                const previewDiv = document.getElementById('edit-photo-preview');
                const previewImg = document.getElementById('edit-photo-img');
                if (offer.photo) {
                    previewImg.src = offer.photo.replace('Cook/', '');
                    previewDiv.style.display = 'block';
                } else {
                    previewDiv.style.display = 'none';
                }

                document.getElementById('edit-photo').value = '';
                openModal('edit-modal');

                if (editHomeMapInstance) {
                    setTimeout(() => editHomeMapInstance.invalidateSize(), 200);
                }
            }
        }
    });
}

function closeEditModal() {
    closeModal('edit-modal');
}

async function handleEditOffer(e) {
    e.preventDefault();

    const form = e.target;
    const formData = new FormData(form);

    const allergenCheckboxes = document.querySelectorAll('#edit-allergens-container input[name="allergens"]:checked');
    const allergensArray = Array.from(allergenCheckboxes).map(cb => cb.value);
    formData.set('allergens', allergensArray.join(', '));

    const pickupSelect = document.getElementById('edit-pickup_location');
    const pickupValue = pickupSelect.value;
    let finalPickupLocation = pickupValue;
    let finalLatitude = 38.2466;
    let finalLongitude = 21.7346;

    if (pickupValue === 'dorm_custom') {
        const dormBuilding = (document.getElementById('edit-dorm-building')?.value || '').trim();
        const dormNumber = (document.getElementById('edit-dorm-number')?.value || '').trim();
        const legacyDormRoom = (document.getElementById('edit-dorm-room')?.value || '').trim();

        let dormRoom = '';
        if (dormBuilding && dormNumber) {
            dormRoom = `${dormBuilding}, Δωμάτιο ${dormNumber}`;
        } else if (legacyDormRoom) {
            dormRoom = legacyDormRoom;
        } else {
            UniBiteAuth.showToast('Συμπληρώστε το κτίριο και τον αριθμό δωματίου.', 'error');
            return;
        }

        finalPickupLocation = `dorm_custom: ${dormRoom}`;
        const selectedOption = pickupSelect.options[pickupSelect.selectedIndex];
        finalLatitude = selectedOption.dataset.lat || 38.2865;
        finalLongitude = selectedOption.dataset.lng || 21.7862;
    } else if (pickupValue === 'home_custom') {
        const homeStreet = (document.getElementById('edit-home-street')?.value || '').trim();
        const homeNumber = (document.getElementById('edit-home-number')?.value || '').trim();
        const homeFloor = (document.getElementById('edit-home-floor')?.value || '').trim();
        const homeBell = (document.getElementById('edit-home-bell')?.value || '').trim();
        const homeNotes = (document.getElementById('edit-home-notes')?.value || '').trim();
        const coordsVal = (document.getElementById('edit-coordinates')?.value || '').trim();

        if (!homeStreet || !homeNumber) {
            UniBiteAuth.showToast('Επιλέξτε σημείο στον χάρτη ή συμπληρώστε Οδό και Αριθμό.', 'error');
            return;
        }

        let locationParts = [`${homeStreet} ${homeNumber}`];
        if (homeFloor) {
            locationParts.push(`Όροφος: ${homeFloor}`);
        }
        if (homeBell) {
            locationParts.push(`Κουδούνι: ${homeBell}`);
        }
        if (!homeFloor && !homeBell && homeNotes) {
            locationParts.push(homeNotes);
        }
        finalPickupLocation = `home_custom: ${locationParts.join(', ')}`;

        if (coordsVal && coordsVal.includes(',')) {
            const [latStr, lngStr] = coordsVal.split(',');
            finalLatitude = parseFloat(latStr.trim()) || 38.2466;
            finalLongitude = parseFloat(lngStr.trim()) || 21.7346;
        }
    } else {
        const selectedOption = pickupSelect.options[pickupSelect.selectedIndex];
        if (selectedOption && selectedOption.dataset.lat && selectedOption.dataset.lng) {
            finalLatitude = selectedOption.dataset.lat;
            finalLongitude = selectedOption.dataset.lng;
        }
    }

    formData.set('pickup_location', finalPickupLocation);
    formData.set('latitude', finalLatitude);
    formData.set('longitude', finalLongitude);

    const pickupTime = document.getElementById('edit-pickup_time').value;
    const editTimeInput = document.getElementById('edit-pickup_time');
    const originalTime = editTimeInput.dataset.originalTime || '';

    if (!pickupTime) {
        UniBiteAuth.showToast('Η ώρα παραλαβής είναι υποχρεωτική.', 'error');
        return;
    }
    const normalizeForCompare = (dt) => (dt || '').replace(' ', 'T').slice(0, 16);
    const isTimeChanged = normalizeForCompare(pickupTime) !== normalizeForCompare(originalTime);

    if (isTimeChanged) {
        const now = new Date();
        const selectedTime = new Date(pickupTime);
        const maxTime = new Date(now.getTime() + (48 * 60 * 60 * 1000));
        if (selectedTime < now || selectedTime > maxTime) {
            UniBiteAuth.showToast('Η ώρα παραλαβής πρέπει να είναι μελλοντική και εντός 48 ωρών.', 'error');
            return;
        }
    }

    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;

    const newStatus = formData.get('status');
    if (TERMINAL_STATUSES.includes(newStatus)) {
        const confirmed = confirm('⚠️ Προσοχή: Η αλλαγή της κατάστασης σε «Ολοκληρωμένη» ή «Ακυρωμένη» είναι οριστική (τερματική κατάσταση). Θέλετε σίγουρα να συνεχίσετε;');
        if (!confirmed) {
            return;
        }
    }

    submitBtn.disabled = true;

    // Client-side image compression for photo upload (edit modal)
    const editPhotoInput = document.getElementById('edit-photo');
    if (editPhotoInput && editPhotoInput.files && editPhotoInput.files[0]) {
        const originalFile = editPhotoInput.files[0];
        if (originalFile.type.startsWith('image/')) {
            submitBtn.textContent = 'Συμπίεση εικόνας...';
            try {
                const compressedFile = await compressImage(originalFile, 1200, 1200, 0.8);
                formData.set('photo', compressedFile, compressedFile.name);
            } catch (err) {
                console.warn('Image compression failed, uploading original:', err);
            }
        }
    }

    submitBtn.textContent = 'Αποθήκευση...';

    try {
        const response = await fetch('api/edit_offer.php', {
            method: 'POST',
            credentials: 'include',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            UniBiteAuth.showToast(result.message, 'success');
            closeEditModal();
            loadMyOffers();
            loadCookStats();
        } else {
            UniBiteAuth.showToast(result.error || 'Σφάλμα ενημέρωσης.', 'error');
        }
    } catch (err) {
        console.error(err);
        UniBiteAuth.showToast('Σφάλμα δικτύου.', 'error');
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
    }
}

async function deleteOffer(offerId) {
    if (!confirm('Είστε σίγουρος ότι θέλετε να διαγράψετε αυτή την αγγελία;')) return;
    const result = await UniBiteAuth.apiCall('api/delete_offer.php', { offer_id: offerId }, 'POST');
    if (result.success) {
        UniBiteAuth.showToast(result.message, 'success');
        loadMyOffers();
        loadCookStats();
    } else {
        UniBiteAuth.showToast(result.error || 'Σφάλμα διαγραφής.', 'error');
    }
}

async function handleRequest(requestId, action) {
    const result = await UniBiteAuth.apiCall('api/handle_request.php', { request_id: requestId, action }, 'POST');
    if (result.success) {
        UniBiteAuth.showToast(result.message, 'success');
        loadMyOffers();
        loadCookStats();
    } else {
        UniBiteAuth.showToast(result.error || 'Σφάλμα ενημέρωσης.', 'error');
    }
}

function translateStatus(status) {
    switch(status) {
        case 'active': return 'Ενεργή';
        case 'inactive': return 'Ανενεργή';
        case 'completed': return 'Ολοκληρωμένη';
        case 'cancelled': return 'Ακυρωμένη';
        case 'expired': return 'Ληγμένη';
        default: return status;
    }
}

function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str).replace(/[&<>\"']/g, function(tag) {
        const chars = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
        return chars[tag] || tag;
    });
}

document.getElementById('edit-modal')?.addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
});

const ALLERGENS = [
    'Γλούτη (Gluten)', 'Καραβίδες (Crustaceans)', 'Αυγά (Eggs)', 'Ψάρια (Fish)',
    'Αράχιδες (Peanuts)', 'Σογιά (Soybeans)', 'Γάλα (Milk)', 'Ξηροί καρποί (Nuts)',
    'Σελήρι (Celery)', 'Μουστάρδα (Mustard)', 'Σουσάμι (Sesame)', 'Διοξείδιο του θείου (Sulphites)',
    'Λουπίνα (Lupin)', 'Μαλάκια (Molluscs)'
];

function populateAllergens(containerId, selectedAllergens = []) {
    const container = document.getElementById(containerId);
    if (!container) return;

    container.innerHTML = ALLERGENS.map(allergen => {
        const isChecked = selectedAllergens.some(a => a.toLowerCase() === allergen.toLowerCase());
        return `
            <label style="display: inline-flex; align-items: center; gap: 0.3rem; font-size: 0.85rem; cursor: pointer;">
                <input type="checkbox" name="allergens" value="${allergen}" ${isChecked ? 'checked' : ''} style="width: 16px; height: 16px; accent-color: var(--dp-color-accent);">
                <span>${allergen}</span>
            </label>
        `;
    }).join('');
}

function setupPickupLocationAutofill(selectId, coordinatesId) {
    const select = document.getElementById(selectId);
    const coordinatesInput = document.getElementById(coordinatesId);
    if (!select || !coordinatesInput) return;

    select.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        if (selectedOption && selectedOption.dataset.lat && selectedOption.dataset.lng) {
            coordinatesInput.value = `${selectedOption.dataset.lat}, ${selectedOption.dataset.lng}`;
        }
    });
}

function setupCustomLocationFields(selectId, dormFieldId, homeFieldsId, homeNotesFieldId, hintId) {
    const select = document.getElementById(selectId);
    const dormField = document.getElementById(dormFieldId);
    const homeFields = document.getElementById(homeFieldsId);
    const homeNotesField = document.getElementById(homeNotesFieldId);
    const hint = document.getElementById(hintId);
    if (!select) return;

    function updateFields() {
        const value = select.value;
        const isDormCustom = value === 'dorm_custom';
        const isHomeCustom = value === 'home_custom';

        if (dormField) dormField.style.display = isDormCustom ? 'block' : 'none';
        if (homeFields) homeFields.style.display = isHomeCustom ? 'block' : 'none';
        if (homeNotesField) homeNotesField.style.display = isHomeCustom ? 'block' : 'none';

        const dormBuildingInput = dormField ? dormField.querySelector('input[name="dorm_building"]') : null;
        const dormNumberInput = dormField ? dormField.querySelector('input[name="dorm_number"]') : null;
        const legacyDormInput = dormField ? dormField.querySelector('input[name="dorm_room"]') : null;
        const homeStreetInput = homeFields ? homeFields.querySelector('input[name="home_street"]') : null;
        const homeNumberInput = homeFields ? homeFields.querySelector('input[name="home_number"]') : null;

        if (dormBuildingInput) dormBuildingInput.required = isDormCustom;
        if (dormNumberInput) dormNumberInput.required = isDormCustom;
        if (legacyDormInput) legacyDormInput.required = isDormCustom;
        if (homeStreetInput) homeStreetInput.required = isHomeCustom;
        if (homeNumberInput) homeNumberInput.required = isHomeCustom;

        if (hint) {
            if (isDormCustom) {
                hint.textContent = 'Συμπληρώστε το κτίριο/πτέρυγα και τον αριθμό δωματίου της εστίας';
            } else if (isHomeCustom) {
                hint.textContent = 'Κάντε κλικ στον χάρτη ή σύρετε την πινέζα στο σπίτι σας και ελέγξτε τον αριθμό';
            } else {
                hint.textContent = 'Επιλέξτε ένα από τα επίσημα σημεία παράδοσης του Πανεπιστημίου Πατρών';
            }
        }

        if (isHomeCustom) {
            if (homeFieldsId === 'home-custom-fields') {
                setTimeout(() => initHomeMap('home-map', 'home-street', 'home-number', 'coordinates'), 120);
            } else if (homeFieldsId === 'edit-home-custom-fields') {
                setTimeout(() => initHomeMap('edit-home-map', 'edit-home-street', 'edit-home-number', 'edit-coordinates'), 120);
            }
        }
    }

    select.addEventListener('change', updateFields);
    updateFields();
}

function initHomeMap(mapContainerId, streetInputId, numberInputId, coordinatesInputId) {
    if (typeof L === 'undefined') {
        console.warn('Leaflet not loaded');
        return;
    }

    const mapContainer = document.getElementById(mapContainerId);
    const streetInput = document.getElementById(streetInputId);
    const numberInput = document.getElementById(numberInputId);
    const coordinatesInput = document.getElementById(coordinatesInputId);

    if (!mapContainer) return;

    if (mapContainerId === 'home-map' && homeMapInstance) {
        setTimeout(() => homeMapInstance.invalidateSize(), 100);
        return;
    }
    if (mapContainerId === 'edit-home-map' && editHomeMapInstance) {
        setTimeout(() => editHomeMapInstance.invalidateSize(), 100);
        return;
    }

    const PATRAS_CENTER = [38.2466, 21.7346];

    const map = L.map(mapContainerId, {
        center: PATRAS_CENTER,
        zoom: 14,
        zoomControl: true,
        scrollWheelZoom: true
    });

    L.tileLayer('https://mt1.google.com/vt/lyrs=m&x={x}&y={y}&z={z}', {
        maxZoom: 20,
        attribution: '&copy; Google Maps'
    }).addTo(map);

    const marker = L.marker(PATRAS_CENTER, {
        draggable: true,
        title: 'Σύρετε την πινέζα στο σπίτι σας'
    }).addTo(map);

    function reverseGeocode(lat, lng) {
        if (coordinatesInput) {
            coordinatesInput.value = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
        }
        fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&accept-language=el`)
            .then(response => response.json())
            .then(data => {
                if (data && data.address) {
                    const address = data.address;
                    const road = address.road || address.pedestrian || address.footway || address.neighbourhood || '';
                    const houseNumber = address.house_number || '';
                    const suburb = address.suburb || address.city_district || '';
                    const city = address.city || address.town || address.municipality || 'Πάτρα';

                    let streetValue = road;
                    if (suburb && suburb !== city && suburb !== road) {
                        streetValue += (streetValue ? ', ' : '') + suburb;
                    }
                    if (city && !streetValue.includes(city)) {
                        streetValue += (streetValue ? ', ' : '') + city;
                    }

                    if (streetInput && streetValue) {
                        streetInput.value = streetValue;
                    }
                    if (numberInput && houseNumber) {
                        numberInput.value = houseNumber;
                    } else if (numberInput && !numberInput.value) {
                        numberInput.focus();
                    }
                }
            })
            .catch(err => console.warn('Reverse geocoding failed:', err));
    }

    marker.on('dragend', function() {
        const pos = marker.getLatLng();
        reverseGeocode(pos.lat, pos.lng);
    });

    map.on('click', function(e) {
        marker.setLatLng(e.latlng);
        reverseGeocode(e.latlng.lat, e.latlng.lng);
    });

    setTimeout(() => {
        map.invalidateSize();
    }, 150);

    if (mapContainerId === 'home-map') {
        homeMapInstance = map;
        homeMarkerInstance = marker;
    } else if (mapContainerId === 'edit-home-map') {
        editHomeMapInstance = map;
        editHomeMarkerInstance = marker;
    }
}

function parsePickupLocationForEdit(location, lat, lng) {
    const editSelect = document.getElementById('edit-pickup_location');
    if (!editSelect || !location) return;

    if (location.startsWith('dorm_custom:')) {
        editSelect.value = 'dorm_custom';
        editSelect.dispatchEvent(new Event('change'));

        const dormRoom = location.replace('dorm_custom:', '').trim();
        const parts = dormRoom.split(',').map(p => p.trim());
        let building = '';
        let number = '';
        for (const part of parts) {
            if (part.startsWith('Δωμάτιο')) {
                number = part.replace('Δωμάτιο', '').trim();
            } else {
                building = building ? `${building}, ${part}` : part;
            }
        }
        const dormBuildingInput = document.getElementById('edit-dorm-building');
        const dormNumberInput = document.getElementById('edit-dorm-number');
        const legacyDormInput = document.getElementById('edit-dorm-room');
        if (dormBuildingInput) dormBuildingInput.value = building;
        if (dormNumberInput) dormNumberInput.value = number;
        if (legacyDormInput) legacyDormInput.value = dormRoom;
        return;
    }

    if (location.startsWith('home_custom:')) {
        editSelect.value = 'home_custom';
        editSelect.dispatchEvent(new Event('change'));

        const raw = location.replace('home_custom:', '').trim();
        const parts = raw.split(',').map(p => p.trim());
        const homeStreetInput = document.getElementById('edit-home-street');
        const homeNumberInput = document.getElementById('edit-home-number');
        const homeFloorInput = document.getElementById('edit-home-floor');
        const homeBellInput = document.getElementById('edit-home-bell');

        if (parts.length > 0) {
            const firstPart = parts[0];
            const match = firstPart.match(/^(.+?)\s+(\d+[a-zA-ZΑ-Ωα-ω]?)$/);
            if (match) {
                if (homeStreetInput) homeStreetInput.value = match[1] + (parts[1] && !parts[1].startsWith('Όροφος:') && !parts[1].startsWith('Κουδούνι:') ? ', ' + parts[1] : '');
                if (homeNumberInput) homeNumberInput.value = match[2];
            } else {
                if (homeStreetInput) homeStreetInput.value = firstPart;
                if (homeNumberInput && parts[1] && !parts[1].startsWith('Όροφος:') && !parts[1].startsWith('Κουδούνι:')) {
                    homeNumberInput.value = parts[1];
                }
            }

            let floor = '';
            let bell = '';
            for (let i = 1; i < parts.length; i++) {
                if (parts[i].startsWith('Όροφος:')) {
                    floor = parts[i].replace('Όροφος:', '').trim();
                } else if (parts[i].startsWith('Κουδούνι:')) {
                    bell = parts[i].replace('Κουδούνι:', '').trim();
                }
            }
            if (homeFloorInput) homeFloorInput.value = floor;
            if (homeBellInput) homeBellInput.value = bell;
        }

        if (lat && lng) {
            setTimeout(() => {
                if (editHomeMapInstance && editHomeMarkerInstance) {
                    const pos = [parseFloat(lat), parseFloat(lng)];
                    editHomeMarkerInstance.setLatLng(pos);
                    editHomeMapInstance.setView(pos, 16);
                }
            }, 250);
        }
        return;
    }

    for (let option of editSelect.options) {
        if (option.value === location) {
            editSelect.value = location;
            editSelect.dispatchEvent(new Event('change'));
            return;
        }
    }
}

// Client-side image compression for mobile uploads
function compressImage(file, maxWidth = 1200, maxHeight = 1200, quality = 0.8) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.readAsDataURL(file);
        reader.onload = e => {
            const img = new Image();
            img.src = e.target.result;
            img.onload = () => {
                let width = img.width, height = img.height;
                if (width > maxWidth || height > maxHeight) {
                    if (width > height) {
                        height = Math.round(height * maxWidth / width);
                        width = maxWidth;
                    } else {
                        width = Math.round(width * maxHeight / height);
                        height = maxHeight;
                    }
                }
                const canvas = document.createElement('canvas');
                canvas.width = width;
                canvas.height = height;
                canvas.getContext('2d').drawImage(img, 0, 0, width, height);
                canvas.toBlob(blob => {
                    if (!blob) return reject(new Error('Canvas toBlob failed'));
                    const newName = file.name.replace(/\.[^/.]+$/, "") + ".jpg";
                    resolve(new File([blob], newName, { type: 'image/jpeg' }));
                }, 'image/jpeg', quality);
            };
            img.onerror = reject;
        };
        reader.onerror = reject;
    });
}