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
            
            // Update button states
            document.querySelectorAll('.tab-btn').forEach(b => {
                b.classList.remove('active');
                b.style.background = 'var(--card-bg)';
                b.style.color = 'var(--text-color)';
                b.style.border = '1px solid var(--border-color)';
            });
            btn.classList.add('active');
            btn.style.background = 'var(--primary-color)';
            btn.style.color = 'white';
            btn.style.border = 'none';
            
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

    const container = document.getElementById('stats-offers-list');
    if (!container) return;
    
    if (result.offers.length === 0) {
        container.innerHTML = '<p style="color: var(--text-muted);">Δεν έχετε δημιουργήσει καμία αγγελία ακόμα.</p>';
        return;
    }

    container.innerHTML = result.offers.map(offer => `
        <div class="offer-card" style="margin-bottom: 1rem; border-left: 4px solid ${getStatusColor(offer.status)};">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
                <h4 style="color: var(--secondary-color); margin: 0;">${escapeHtml(offer.title)}</h4>
                <span class="badge badge-${offer.status}">${translateStatus(offer.status)}</span>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-bottom: 1rem; font-size: 0.9rem; color: var(--text-muted);">
                <div>🥣 Μερίδες: <strong>${offer.portions}</strong></div>
                <div>📅 Δημιουργήθηκε: <strong>${formatDate(offer.created_at)}</strong></div>
                <div>📥 Αιτήματα: <strong>${offer.total_requests}</strong></div>
                <div>✅ Παραδόθηκαν: <strong>${offer.delivered_requests}</strong></div>
            </div>

            ${(offer.delivered_requests > 0 && offer.rating_count > 0) ? `
                <div style="border-top: 1px solid var(--border-color); padding-top: 1rem;">
                    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 0.5rem;">
                        <span style="font-size: 1.5rem; color: var(--warning-color);">⭐ ${offer.avg_rating.toFixed(1)}</span>
                        <span style="color: var(--text-muted);">(${offer.rating_count} αξιολογήσεις)</span>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        ${offer.ratings.map(r => `
                            <div style="background: var(--card-bg); padding: 0.75rem; border-radius: var(--radius); border: 1px solid var(--border-color);">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 0.25rem;">
                                    <span style="font-weight: 600;">${'⭐'.repeat(r.rating)}</span>
                                    <small style="color: var(--text-muted);">Πόντοι: ${r.rating >= 4 ? '+2' : '+1'}</small>
                                </div>
                                ${r.comment ? '<p style="margin: 0; font-style: italic; color: var(--text-muted);">"' + escapeHtml(r.comment) + '"</p>' : ''}
                            </div>
                        `).join('')}
                    </div>
                </div>
            ` : '<p style="color: var(--text-muted); font-size: 0.9rem;">Δεν υπάρχουν ακόμη αξιολογήσεις (διαθέσιμες μετά την παραλαβή).</p>'}
        </div>
    `).join('');
}

function getStatusColor(status) {
    switch(status) {
        case 'active': return 'var(--success-color)';
        case 'inactive': return 'var(--warning-color)';
        case 'completed': return 'var(--primary-color)';
        case 'cancelled': return 'var(--danger-color)';
        case 'expired': return 'var(--text-muted)';
        default: return 'var(--border-color)';
    }
}

function formatDate(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleDateString('el-GR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

async function loadMyOffers() {
    const container = document.getElementById('offers-list');
    if (!container) return;
    container.innerHTML = '<p style="color: var(--text-muted);">Φόρτωση αγγελιών...</p>';

    try {
        const result = await UniBiteAuth.apiCall('api/get_my_offers.php', {}, 'GET');
        
        if (!result.success) {
            container.innerHTML = '<p style="color: var(--danger-color);">' + (result.error || 'Σφάλμα φόρτωσης αγγελιών. Συνδεθείτε ως μάγειρας.') + '</p>';
            return;
        }

        if (result.offers.length === 0) {
            container.innerHTML = '<p style="color: var(--text-muted);">Δεν έχετε δημιουργήσει καμία αγγελία ακόμα.</p>';
            return;
        }

    container.innerHTML = result.offers.map(offer => `
        <div class="offer-card" id="offer-${offer.id}">
            ${offer.photo ? '<img src="' + escapeHtml(offer.photo.replace(/^Cook\//, '')) + '" alt="" style="width:100%; max-height:200px; object-fit:cover; border-radius:8px; margin-bottom:0.5rem;">' : ''}
            <div class="offer-header">
                <h3>${escapeHtml(offer.title)}</h3>
                <span class="badge badge-${offer.status}">${translateStatus(offer.status)}</span>
            </div>
            <p>${escapeHtml(offer.description)}</p>
            ${offer.allergens ? '<p style="font-size:0.8rem; color:var(--text-muted); margin:0.5rem 0;"><strong>Αλλεργιογόνα:</strong> ' + escapeHtml(offer.allergens) + '</p>' : ''}
            <p style="margin-top: 0.5rem; font-size: 0.9rem; color: var(--text-muted);">
                🥣 Μερίδες: <strong>${offer.portions}</strong> | 📍 Σημείο: <strong>${escapeHtml(offer.pickup_location)}</strong> | ⏰ ${offer.pickup_time}
            </p>
            <div style="margin-top: 1rem; display: flex; gap: 0.5rem;">
                <button class="btn btn-outline" onclick="openEditModal(${offer.id})">Επεξεργασία</button>
                <button class="btn btn-outline" onclick="deleteOffer(${offer.id})">Διαγραφή</button>
            </div>
            
            <div style="margin-top: 1rem; border-top: 1px solid var(--border-color); padding-top: 1rem;">
                <h4 style="font-size: 0.95rem; margin-bottom: 0.5rem; color: var(--secondary-color);">Αιτήματα Φοιτητών (${offer.requests.length})</h4>
                ${offer.requests.length === 0 ? '<p style="font-size: 0.85rem; color: var(--text-muted);">Κανένα αίτημα ακόμα.</p>' : 
                    offer.requests.map(req => `
                        <div class="request-item">
                            <div>
                                <strong>${escapeHtml(req.consumer_name)}</strong> 
                                <span style="font-size: 0.8rem; color: var(--text-muted);">[${req.status}]</span>
                            </div>
                            <div style="display: flex; gap: 0.3rem;">
                                ${req.status === 'pending' ? `
                                    <button class="btn btn-primary" style="padding: 0.3rem 0.6rem; font-size: 0.8rem;" onclick="handleRequest(${req.id}, 'approve')">Έγκριση</button>
                                    <button class="btn btn-danger" style="padding: 0.3rem 0.6rem; font-size: 0.8rem;" onclick="handleRequest(${req.id}, 'reject')">Απόρριψη</button>
                                ` : ''}
                                ${req.status === 'approved' ? `
                                    <button class="btn btn-primary" style="padding: 0.3rem 0.6rem; font-size: 0.8rem; background-color: var(--success-color);" onclick="handleRequest(${req.id}, 'delivered')">Παραδόθηκε</button>
                                    <button class="btn btn-danger" style="padding: 0.3rem 0.6rem; font-size: 0.8rem;" onclick="handleRequest(${req.id}, 'absent')">Απών</button>
                                ` : ''}
                            </div>
                        </div>
                    `).join('')}
            </div>
        </div>
    `).join('');
    } catch (error) {
        console.error('loadMyOffers error:', error);
        container.innerHTML = '<p style="color: var(--danger-color);">Σφάλμα σύνδεσης. Ελέγξτε αν είναι συνδεδεμένοι ως μάγειρας.</p>';
    }
}

async function handleCreateOffer(e) {
    e.preventDefault();

    const form = e.target;
    const formData = new FormData(form);

    // Add lat/lng from selected option
    const pickupSelect = document.getElementById('pickup_location');
    const selectedOption = pickupSelect.options[pickupSelect.selectedIndex];
    if (selectedOption.dataset.lat && selectedOption.dataset.lng) {
        formData.set('latitude', selectedOption.dataset.lat);
        formData.set('longitude', selectedOption.dataset.lng);
    }

    // Client-side validation for pickup_time
    const pickupTime = document.getElementById('pickup_time').value;
    if (!pickupTime) {
        UniBiteAuth.showToast('Η ώρα παραλαβής είναι υποχρεωτική.', 'error');
        return;
    }
    const now = new Date();
    const selectedTime = new Date(pickupTime);
    const maxTime = new Date(now.getTime() + (48 * 60 * 60 * 1000));
    if (selectedTime < now || selectedTime > maxTime) {
        UniBiteAuth.showToast('Η ώρα παραλαβής πρέπει να είναι μελλοντική και εντός 48 ωρών.', 'error');
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
                    document.getElementById('edit-pickup_time').value = cleanTime;
                }
                document.getElementById('edit-status').value = offer.status;
                
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
                    previewImg.src = offer.photo.replace(/^Cook\//, '');
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
    if (!pickupTime) {
        UniBiteAuth.showToast('Η ώρα παραλαβής είναι υποχρεωτική.', 'error');
        return;
    }
    const now = new Date();
    const selectedTime = new Date(pickupTime);
    const maxTime = new Date(now.getTime() + (48 * 60 * 60 * 1000));
    if (selectedTime < now || selectedTime > maxTime) {
        UniBiteAuth.showToast('Η ώρα παραλαβής πρέπει να είναι μελλοντική και εντός 48 ωρών.', 'error');
        return;
    }

    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
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
    return str.replace(/[&<>'"]/g, function(tag) {
        if (tag === '&') return '&';
        if (tag === '<') return '<';
        if (tag === '>') return '>';
        if (tag === "'") return '&apos;';
        if (tag === '"') return '"';
        return tag;
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
                <input type="checkbox" name="allergens" value="${allergen}" ${isChecked ? 'checked' : ''} style="width: 16px; height: 16px; accent-color: var(--primary-color);">
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
document.addEventListener('DOMContentLoaded', () => {
    populateAllergens('allergens-container');
    populateAllergens('edit-allergens-container');
   
    // Setup auto-fill for both create and edit forms
    setupPickupLocationAutofill('pickup_location', 'coordinates');
    setupPickupLocationAutofill('edit-pickup_location', 'edit-coordinates');
});