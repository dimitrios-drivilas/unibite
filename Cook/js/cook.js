// Cook/js/cook.js

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

            // Update button states - just toggle classes, let CSS handle styling
            document.querySelectorAll('.tab-btn').forEach(b => {
                b.classList.remove('active');
            });
            btn.classList.add('active');

            // Show/hide panels
            document.querySelectorAll('.tab-panel').forEach(panel => {
                panel.style.display = 'none';
            });
            document.getElementById('tab-' + tab).style.display = 'block';

            // Load stats when switching to stats tab
            if (tab === 'stats') {
                loadCookStats();
            }
        });
    });
}

async function loadCookStats() {
    const result = await UniBiteAuth.apiCall('api/get_cook_stats.php', {}, 'GET');
    if (!result.success) return;

    document.getElementById('stat-total-points').textContent = result.stats.total_points;
    document.getElementById('stat-total-offers').textContent = result.stats.total_offers;
    document.getElementById('stat-total-portions').textContent = result.stats.total_portions;
    const avgRating = result.stats.avg_rating;
    const ratingCount = result.stats.rating_count;
    console.log('DEBUG avgRating:', avgRating, 'ratingCount:', ratingCount);
    const ratingVal = (avgRating !== null && avgRating !== undefined && ratingCount > 0)
        ? parseFloat(avgRating).toFixed(1)
        : '-';
    console.log('DEBUG ratingVal:', ratingVal);
    document.getElementById('stat-avg-rating').textContent = ratingVal;

    // New stats
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
                    '<span><strong>📍 Σημείο:</strong> ' + escapeHtml(offer.pickup_location) + '</span>' +
                    '<span><strong>⏰</strong> ' + offer.pickup_time + '</span>' +
                '</p>' +
            '</div>' +
            '<div class="offer-right">' +
                '<div class="requests-section">' +
                    '<h4>Αξιολογήσεις (' + (offer.rating_count || 0) + ')</h4>' +
                    ((offer.ratings || []).length === 0
                        ? '<p style="font-size: 0.85rem; color: var(--dp-color-ink-secondary);">Κανένα αξιολόγηση ακόμα.</p>'
                        : '<div class="requests-list"><div class="requests-grid">' + requestsHtml + '</div></div>') +
                '</div>' +
            '</div>' +
        '</div>';
    }).join('');
}

function getStatusColor(status) {
    switch(status) {
        case 'active': return 'var(--dp-color-success)';
        case 'inactive': return 'var(--dp-color-warning)';
        case 'completed': return 'var(--dp-color-accent)';
        case 'cancelled': return 'var(--dp-color-danger)';
        case 'expired': return 'var(--dp-color-ink-secondary)';
        default: return 'var(--dp-color-rule)';
    }
}

function formatDate(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleDateString('el-GR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

// Terminal states that cannot be edited once set
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

        // Show all offers except cancelled and expired ones
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
                        '<span><strong>📍 Σημείο:</strong> ' + escapeHtml(offer.pickup_location) + '</span>' +
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
        container.innerHTML = '<p style="color: var(--dp-color-danger);">Σφάλμα σύνδεσης. Ελέγξτε αν είναι συνδεδεμένοι ως μάγειρας.</p>';
    }
}

// ... rest of the file remains the same

async function handleCreateOffer(e) {
    e.preventDefault();

    const form = e.target;
    const formData = new FormData(form);

    // Collect allergens from checkboxes (name="allergens" without [] sends only last value in PHP)
    const allergenCheckboxes = document.querySelectorAll('#allergens-container input[name="allergens"]:checked');
    const allergensArray = Array.from(allergenCheckboxes).map(cb => cb.value);
    formData.set('allergens', allergensArray.join(', '));

    // Add lat/lng from selected option
    const pickupSelect = document.getElementById('pickup_location');
    const selectedOption = pickupSelect.options[pickupSelect.selectedIndex];
    if (selectedOption.dataset.lat && selectedOption.dataset.lng) {
        formData.set('latitude', selectedOption.dataset.lat);
        formData.set('longitude', selectedOption.dataset.lng);
    }

    // Only validate that pickup_time is provided (server will validate future date)
    const pickupTime = document.getElementById('pickup_time').value;
    if (!pickupTime) {
        UniBiteAuth.showToast('Η ώρα παραλαβής είναι υποχρεωτική.', 'error');
        return;
    }

    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.disabled = true;
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
            document.getElementById('coordinates').value = '';
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
                document.getElementById('edit-pickup_location').value = offer.pickup_location;
                document.getElementById('edit-coordinates').value = offer.latitude + ', ' + offer.longitude;
                if (offer.pickup_time) {
                    const cleanTime = offer.pickup_time.replace(' ', 'T').slice(0, 16);
                    const editTimeInput = document.getElementById('edit-pickup_time');
                    editTimeInput.value = cleanTime;
                    // Set min to the original pickup_time so the current value is always valid
                    // This prevents HTML5 validation errors when editing offers with past pickup times
                    editTimeInput.min = cleanTime;
                    // Store original pickup_time for edit validation
                    editTimeInput.dataset.originalTime = cleanTime;
                }
                                document.getElementById('edit-status').value = offer.status;

                                // Show/hide warning banner for terminal statuses in edit modal
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
                                        const existingWarning = document.getElementById('terminal-status-warning');
                                        if (existingWarning) existingWarning.remove();
                                    }
                                }

                                // Initial check
                                updateTerminalWarning();

                                // Listen for status changes
                                statusSelect.addEventListener('change', updateTerminalWarning);

                                // Populate allergens checkboxes in edit modal
                const existingAllergens = offer.allergens ? offer.allergens.split(',').map(a => a.trim()) : [];
                populateAllergens('edit-allergens-container', existingAllergens);

                // Parse existing pickup_location ID and populate edit modal dropdown
                if (typeof parsePickupLocationForEdit === 'function') {
                    parsePickupLocationForEdit(offer.pickup_location);
                } else {
                    // Fallback: set value directly and trigger change for coordinates
                    const editSelect = document.getElementById('edit-pickup_location');
                    if (editSelect) {
                        editSelect.value = offer.pickup_location;
                        editSelect.dispatchEvent(new Event('change'));
                    }
                }

                // Show existing photo preview
                const previewDiv = document.getElementById('edit-photo-preview');
                const previewImg = document.getElementById('edit-photo-img');
                if (offer.photo) {
                    previewImg.src = offer.photo.replace('Cook/', '');
                    previewDiv.style.display = 'block';
                } else {
                    previewDiv.style.display = 'none';
                }

                // Clear file input
                document.getElementById('edit-photo').value = '';

                document.getElementById('edit-modal').classList.add('active');
            }
        }
    });
}

function closeEditModal() {
    document.getElementById('edit-modal').classList.remove('active');
}

async function handleEditOffer(e) {
    e.preventDefault();
    const offerId = document.getElementById('edit-offer-id').value;

    const form = e.target;
    const formData = new FormData(form);

    // Collect allergens from edit modal checkboxes (name="allergens" without [] sends only last value in PHP)
    const allergenCheckboxes = document.querySelectorAll('#edit-allergens-container input[name="allergens"]:checked');
    const allergensArray = Array.from(allergenCheckboxes).map(cb => cb.value);
    formData.set('allergens', allergensArray.join(', '));

    // Add lat/lng from selected option
    const pickupSelect = document.getElementById('edit-pickup_location');
    const selectedOption = pickupSelect.options[pickupSelect.selectedIndex];
    if (selectedOption.dataset.lat && selectedOption.dataset.lng) {
        formData.set('latitude', selectedOption.dataset.lat);
        formData.set('longitude', selectedOption.dataset.lng);
    }

    // Client-side validation for pickup_time
    const pickupTime = document.getElementById('edit-pickup_time').value;
    const editTimeInput = document.getElementById('edit-pickup_time');
    const originalTime = editTimeInput.dataset.originalTime;

    if (!pickupTime) {
        UniBiteAuth.showToast('Η ώρα παραλαβής είναι υποχρεωτική.', 'error');
        return;
    }
    // Normalize both times for comparison (datetime-local is YYYY-MM-DDTHH:MM, DB is YYYY-MM-DD HH:MM:SS)
    const normalizeForCompare = (dt) => dt.replace(' ', 'T').slice(0, 16);
    const isTimeChanged = normalizeForCompare(pickupTime) !== normalizeForCompare(originalTime);

    // Only validate future/48h if the time was CHANGED from original
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

    // Check if status is being changed to a terminal state
    const newStatus = formData.get('status');
    const currentStatus = document.getElementById('edit-status').value; // This is the original value before form submission
    if (TERMINAL_STATUSES.includes(newStatus) && newStatus !== currentStatus) {
        const confirmed = confirm('⚠️ Προσοχή: Η αλλαγή της κατάστασης σε «Ολοκληρωμένη» ή «Ακυρωμένη» είναι οριστική (τερματική κατάσταση). Μετά την αποθήκευση ΔΕΝ θα μπορείτε να επεξεργαστείτε ξανά την αγγελία ή να την επαναφέρετε σε ενεργή. Θέλετε σίγουρα να συνεχίσετε;');
        if (!confirmed) {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
            return;
        }
    }

    submitBtn.disabled = true;
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

function fileToBase64(file) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(reader.result);
        reader.onerror = reject;
        reader.readAsDataURL(file);
    });
}

document.getElementById('edit-modal')?.addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
});

// 14 common allergens (EU regulation)
const ALLERGENS = [
    'Γλούτη (Gluten)', 'Καραβίδες (Crustaceans)', 'Αυγά (Eggs)', 'Ψάρια (Fish)',
    'Αράχιδες (Peanuts)', 'Σογιά (Soybeans)', 'Γάλα (Milk)', 'Ξηροί καρποί (Nuts)',
    'Σελήρι (Celery)', 'Μουστάρδα (Mustard)', 'Σουσάμι (Sesame)', 'Διοξείδιο του θείου (Sulphites)',
    'Λουπίνα (Lupin)', 'Μαλάκια (Molluscs)'
];

// Populate allergens checkboxes
function populateAllergens(containerId, selectedAllergens = []) {
    const container = document.getElementById(containerId);
    if (!container) return;

    container.innerHTML = ALLERGENS.map(allergen => {
        const isChecked = selectedAllergens.some(a => a.toLowerCase() === allergen.toLowerCase());
        const id = `allergen-${allergen.replace(/[^a-zA-Z0-9α-ωΑ-Ω]/g, '-')}`;
        return `
            <label style="display: inline-flex; align-items: center; gap: 0.3rem; font-size: 0.85rem; cursor: pointer;">
                <input type="checkbox" name="allergens" value="${allergen}" ${isChecked ? 'checked' : ''} style="width: 16px; height: 16px; accent-color: var(--dp-color-accent);">
                <span>${allergen}</span>
            </label>
        `;
    }).join('');
}

// Auto-fill coordinates from pickup location dropdown
function setupPickupLocationAutofill(selectId, coordinatesId) {
    const select = document.getElementById(selectId);
    const coordinatesInput = document.getElementById(coordinatesId);
    if (!select || !coordinatesInput) return;

    select.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        if (selectedOption && selectedOption.dataset.lat && selectedOption.dataset.lng) {
            coordinatesInput.value = `${selectedOption.dataset.lat}, ${selectedOption.dataset.lng}`;
        } else {
            coordinatesInput.value = '';
        }
    });
}

// Parse existing pickup_location ID and populate edit modal dropdown
function parsePickupLocationForEdit(location) {
    const editSelect = document.getElementById('edit-pickup_location');
    if (!editSelect || !location) return;

    // Try to match by value (location ID)
    for (let option of editSelect.options) {
        if (option.value === location) {
            editSelect.value = location;
            // Trigger change to update coordinates
            editSelect.dispatchEvent(new Event('change'));
            return;
        }
    }

    // Fallback: try to match by text content (legacy format)
    const legacyMap = {
        'Φοιτητική Εστία Α': 'fep-building-a',
        'Φοιτητική Εστία Β': 'fep-building-b',
        'Φοιτητική Εστία Γ': 'fep-building-c',
        'Φοιτητική Εστία Ζ': 'fep-building-z',
        'Φοιτητική Εστία Η': 'fep-building-h',
        'Φοιτητική Εστία Θ': 'fep-building-th',
        'Κτίριο Α - Διοίκηση': 'admin-building-a',
        'Κτίριο Β': 'building-b',
        'CEID': 'ceid-building',
        'ECE': 'ece-building',
        'Μηχανολόγοι': 'mech-aero-building',
        'Πολιτικοί Μηχανικοί': 'civil-eng-building',
        'Χημικοί Μηχανικοί': 'chem-eng-building',
        'Ιατρική': 'med-preclinical-building',
        'Φυσική': 'physics-math-building',
        'Χημεία': 'chemistry-building-north',
        'Βιολογία': 'biology-building',
        'Γεωλογία': 'geology-building',
        'Εκπαίδευση': 'education-social-work-building',
        'Βιβλιοθήκη': 'central-library',
        'Συνεδριακό': 'conference-center',
        'Πάρκο Ειρήνης': 'peace-park',
        'Φοιτητική Λέσχη': 'fep-canteen',
        'Κουκούλι': 'koukouli-dormitory'
    };

    for (const [key, value] of Object.entries(legacyMap)) {
        if (location.includes(key)) {
            editSelect.value = value;
            editSelect.dispatchEvent(new Event('change'));
            return;
        }
    }
}

// Initialize on page load
// (DOMContentLoaded listener is at the top of the file)