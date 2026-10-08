// Admin/js/admin.js

document.addEventListener('DOMContentLoaded', () => {
    loadStatistics();
    loadLeaderboard();
    loadUsers();
    loadAllOffers();
});

async function loadStatistics() {
    const result = await UniBiteAuth.apiCall('api/get_statistics.php', {}, 'GET');
    if (!result.success) return;

    document.getElementById('stat-portions').textContent = result.total_portions;
    document.getElementById('stat-offers').textContent = result.total_offers;
    document.getElementById('stat-requests').textContent = result.total_requests;
    document.getElementById('stat-delivered').textContent = result.delivered_requests;
}

async function loadLeaderboard() {
    const result = await UniBiteAuth.apiCall('api/get_leaderboard.php', {}, 'GET');
    if (!result.success) {
        const donorsBody = document.getElementById('top-donors-body');
        const mealsBody = document.getElementById('top-meals-body');
        donorsBody.innerHTML = '<tr><td colspan="3" style="text-align: center; color: var(--danger-color);">Σφάλμα φόρτωσης.</td></tr>';
        mealsBody.innerHTML = '<tr><td colspan="3" style="text-align: center; color: var(--danger-color);">Σφάλμα φόρτωσης.</td></tr>';
        return;
    }

    // Top Donors
    const donorsBody = document.getElementById('top-donors-body');
    if (result.top_donors.length === 0) {
        donorsBody.innerHTML = '<tr><td colspan="3" style="text-align: center; color: var(--text-muted);">Δεν υπάρχουν επαρκή δεδομένα.</td></tr>';
    } else {
        donorsBody.innerHTML = result.top_donors.map((d, index) => `
            <tr>
                <td>#${index + 1} ${escapeHtml(d.username)}</td>
                <td>${d.total_portions_offered}</td>
                <td>${d.offer_count}</td>
            </tr>
        `).join('');
    }

    // Top Rated Meals
    const mealsBody = document.getElementById('top-meals-body');
    if (result.top_rated_meals.length === 0) {
        mealsBody.innerHTML = '<tr><td colspan="3" style="text-align: center; color: var(--text-muted);">Δεν υπάρχουν επαρκή δεδομένα.</td></tr>';
    } else {
        mealsBody.innerHTML = result.top_rated_meals.map((m, index) => `
            <tr>
                <td>#${index + 1} ${escapeHtml(m.title)} <br><small style="color: var(--text-muted);">(${escapeHtml(m.cook_name)})</small></td>
                <td>⭐ ${parseFloat(m.avg_rating).toFixed(1)}</td>
                <td>${m.rating_count}</td>
            </tr>
        `).join('');
    }
}

async function loadUsers() {
    const result = await UniBiteAuth.apiCall('api/get_users.php', {}, 'GET');
    const tbody = document.getElementById('users-table-body');
    if (!result.success) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: var(--danger-color);">' + (result.error || 'Σφάλμα φόρτωσης.') + '</td></tr>';
        return;
    }

    if (result.users.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: var(--text-muted);">Δεν υπάρχουν χρήστες.</td></tr>';
        return;
    }

    tbody.innerHTML = result.users.map(u => `
        <tr>
            <td>${u.id}</td>
            <td>${escapeHtml(u.username)}</td>
            <td>${escapeHtml(u.email)}</td>
            <td>${u.role ? escapeHtml(u.role) : 'Χρήστης'}</td>
            <td>${u.created_at}</td>
            <td>
                <button class="btn btn-outline" style="padding: 0.3rem 0.6rem; font-size: 0.8rem;" onclick="deleteUser(${u.id})">Διαγραφή</button>
            </td>
        </tr>
    `).join('');
}

async function loadAllOffers() {
    const result = await UniBiteAuth.apiCall('api/get_all_offers.php', {}, 'GET');
    const tbody = document.getElementById('all-offers-body');
    if (!result.success) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; color: var(--danger-color);">' + (result.error || 'Σφάλμα φόρτωσης.') + '</td></tr>';
        return;
    }

    if (result.offers.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; color: var(--text-muted);">Δεν υπάρχουν αγγελίες.</td></tr>';
        return;
    }

    tbody.innerHTML = result.offers.map(o => `
        <tr>
            <td>${o.id}</td>
            <td>${escapeHtml(o.title)}</td>
            <td>${escapeHtml(o.cook_name)}</td>
            <td>${o.portions}</td>
            <td><span class="badge badge-${o.status}">${o.status}</span></td>
            <td>${o.created_at}</td>
            <td>
                <button class="btn btn-outline" style="padding: 0.3rem 0.6rem; font-size: 0.8rem;" onclick="adminDeleteOffer(${o.id})">Διαγραφή</button>
            </td>
        </tr>
    `).join('');
}

async function deleteUser(userId) {
    if (!confirm('Είστε σίγουρος ότι θέλετε να διαγράψετε αυτόν τον χρήστη;')) return;
    const result = await UniBiteAuth.apiCall('api/delete_user.php', { user_id: userId }, 'POST');
    if (result.success) {
        UniBiteAuth.showToast(result.message, 'success');
        loadUsers();
    } else {
        UniBiteAuth.showToast(result.error || 'Σφάλμα διαγραφής.', 'error');
    }
}

async function adminDeleteOffer(offerId) {
    if (!confirm('Είστε σίγουρος ότι θέλετε να διαγράψετε αυτή την αγγελία;')) return;
    const result = await UniBiteAuth.apiCall('api/admin_delete_offer.php', { offer_id: offerId }, 'POST');
    if (result.success) {
        UniBiteAuth.showToast(result.message, 'success');
        loadAllOffers();
    } else {
        UniBiteAuth.showToast(result.error || 'Σφάλμα διαγραφής.', 'error');
    }
}

function escapeHtml(str) {
    return str.replace(/[&<>'"]/g,
        tag => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[tag] || tag)
    );
}