// Shared/js/auth.js

class UniBiteAuth {
    static showToast(message, type = 'success') {
        let toast = document.getElementById('unibite-toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'unibite-toast';
            toast.className = 'toast';
            document.body.appendChild(toast);
        }
        toast.textContent = message;
        toast.className = `toast ${type} show`;
        setTimeout(() => {
            toast.className = `toast ${type}`;
        }, 3000);
    }

    static async apiCall(url, data = {}, method = 'POST') {
        try {
            const options = {
                method: method,
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json'
                }
            };
            if (method !== 'GET' && method !== 'HEAD') {
                options.body = JSON.stringify(data);
            }
            const response = await fetch(url, options);
            const result = await response.json();
            return result;
        } catch (error) {
            console.error('API Error:', error);
            return { success: false, error: 'Σφάλμα επικοινωνίας με τον server.' };
        }
    }

    // NEW: login() method - calls Shared/api/login.php and redirects by role
    static async login(identifier, password) {
        const result = await this.apiCall('Shared/api/login.php', {
            email: identifier,
            username: identifier,
            password: password
        }, 'POST');

        if (result.success && result.roles) {
            this.showToast('Επιτυχής σύνδεση! Μεταφορά...', 'success');
            setTimeout(() => { window.location.href = 'index.php'; }, 300);
        } else {
            this.showToast(result.error || 'Σφάλμα σύνδεσης.', 'error');
        }

        return result;
    }

    // UPDATED: logout() with dynamic relative path calculation
    static async logout(redirectUrl = '../index.php') {
        const pathDepth = this._getPathDepth();
        const logoutUrl = `${pathDepth}Shared/api/logout.php`;

        const result = await this.apiCall(logoutUrl, {}, 'POST');
        if (result.success) {
            window.location.href = redirectUrl;
        } else {
            this.showToast(result.error || 'Σφάλμα αποσύνδεσης.', 'error');
        }
    }

    // Helper: calculates '../' depth from window.location.pathname
    static _getPathDepth() {
        const path = window.location.pathname;
        const parts = path.split('/').filter(Boolean);
        const depth = Math.max(0, parts.length - 1);
        return '../'.repeat(depth);
    }
}

// Global logout function for inline onclick handlers
async function logout() {
    await UniBiteAuth.logout('../index.php');
}