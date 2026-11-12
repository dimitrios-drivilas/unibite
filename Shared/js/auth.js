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

    static async login(identifier, password) {
        const result = await this.apiCall('Shared/api/login.php', {
            email: identifier,
            username: identifier,
            password: password
        }, 'POST');

        if (result.success && result.roles) {
            this.showToast('Επιτυχής σύνδεση! Μεταφορά...', 'success');
            setTimeout(() => { 
                window.location.href = result.redirect || 'index.php'; 
            }, 300);
        } else {
            this.showToast(result.error || 'Σφάλμα σύνδεσης.', 'error');
        }

        return result;
    }

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

    static _getPathDepth() {
        const path = window.location.pathname;
        const parts = path.split('/').filter(Boolean);
        const depth = Math.max(0, parts.length - 1);
        return '../'.repeat(depth);
    }
}

async function logout() {
    await UniBiteAuth.logout('../index.php');
}

// ==========================================================================
// Clean Focus Trap for Modals (Keyboard Only - Tab & Escape)
// No inert, no aria-hidden - works on iOS Safari
// ==========================================================================

function getActiveModalOverlay() {
    return document.querySelector('.modal-overlay.active, .modal-overlay[style*="flex"], .modal-overlay[style*="block"]');
}

function getFocusableElements(modal) {
    const selectors = 'select:not([disabled]), input:not([type="hidden"]):not([disabled]), textarea:not([disabled]), button:not([disabled]), a[href]';
    return Array.from(modal.querySelectorAll(selectors)).filter(el => {
        const style = window.getComputedStyle(el);
        return style.display !== 'none' && style.visibility !== 'hidden' && el.offsetParent !== null;
    });
}

function openModal(modalId, firstFocusSelector = null) {
    const overlay = document.getElementById(modalId);
    if (!overlay) return;
    
    // Close any other open modal first
    const otherOverlay = getActiveModalOverlay();
    if (otherOverlay && otherOverlay !== overlay) {
        closeModal(otherOverlay.id);
    }
    
    // Show modal
    overlay.classList.add('active');
    overlay.style.display = 'flex';
    
    // Focus first element (but NOT on mobile to avoid native picker issues)
    if (firstFocusSelector && !isMobile()) {
        const modal = overlay.querySelector('.modal');
        if (modal) {
            const firstEl = modal.querySelector(firstFocusSelector);
            if (firstEl) {
                setTimeout(() => firstEl.focus(), 50);
            }
        }
    }
}

function closeModal(modalId) {
    const overlay = document.getElementById(modalId);
    if (!overlay) return;
    
    overlay.classList.remove('active');
    overlay.style.display = 'none';
}

function isMobile() {
    return /Mobi|Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
}

// Global keydown handler for focus trapping (Tab & Escape only)
document.addEventListener('keydown', function(e) {
    const overlay = getActiveModalOverlay();
    if (!overlay) return;
    
    const modal = overlay.querySelector('.modal');
    if (!modal) return;
    
    // Escape - close modal
    if (e.key === 'Escape') {
        e.preventDefault();
        const closeBtn = modal.querySelector('[onclick*="close"], [onclick*="Close"], .btn-outline, .btn-secondary');
        if (closeBtn) {
            closeBtn.click();
        } else {
            closeModal(overlay.id);
        }
        return;
    }
    
    // Tab - trap focus inside modal
    if (e.key !== 'Tab') return;
    
    const focusable = getFocusableElements(modal);
    if (focusable.length === 0) return;
    
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    const active = document.activeElement;
    
    // Check if focus is outside modal or on last element going forward
    if (!e.shiftKey) {
        if (active === last || !modal.contains(active)) {
            e.preventDefault();
            first.focus();
        }
    } else {
        // Shift + Tab - going backward
        if (active === first) {
            e.preventDefault();
            last.focus();
        }
    }
});

// Close modal on overlay click (outside modal content)
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        closeModal(e.target.id);
    }
});