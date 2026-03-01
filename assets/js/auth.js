// Auth state management
let currentUser = JSON.parse(localStorage.getItem('currentUser')) || null;
let csrfToken = localStorage.getItem('csrfToken') || null;

// Monkey patch fetch to include CSRF token
const originalFetch = window.fetch;
window.fetch = function (input, init) {
    init = init || {};
    init.headers = init.headers || {};

    // Add CSRF token if available and not a GET/HEAD request
    if (csrfToken && (!init.method || !['GET', 'HEAD'].includes(init.method.toUpperCase()))) {
        if (init.headers instanceof Headers) {
            init.headers.append('X-CSRF-Token', csrfToken);
        } else if (Array.isArray(init.headers)) {
            init.headers.push(['X-CSRF-Token', csrfToken]);
        } else {
            init.headers['X-CSRF-Token'] = csrfToken;
        }
    }

    // Add credentials default
    if (!init.credentials) {
        init.credentials = 'include';
    }

    return originalFetch(input, init);
};

// Expose functions to global scope
window.Auth = {
    // Function to check if user is logged in
    isAuthenticated: function () {
        return currentUser !== null;
    },

    // Function to get current user
    getCurrentUser: function () {
        return currentUser;
    },

    // Function to set current user
    setCurrentUser: function (user) {
        currentUser = user;
        localStorage.setItem('currentUser', JSON.stringify(user));
        updateUI();
        // Also update navbar immediately when user data changes (e.g., profile picture update)
        if (window.Navbar && typeof window.Navbar.update === 'function') {
            window.Navbar.update();
        }
    },

    // Function to log out user (client + server)
    logout: function () {
        // Call backend to destroy server session; include credentials so cookies are sent
        fetch('backend/auth/logout.php', { method: 'POST', credentials: 'include' })
            .catch(function () {
                // ignore network errors; we'll still clear client state
            })
            .finally(() => {
                // Clear client-side auth state
                currentUser = null;
                localStorage.removeItem('currentUser');
                window.Auth.updateUI();

                // Redirect to the login page (use relative path to match hosting)
                const isOnAuthPage = window.location.pathname.endsWith('login.html') || window.location.pathname.endsWith('register.html');
                if (!isOnAuthPage) {
                    window.location.href = 'login.html';
                }
            });
    },

    // Function to update UI based on auth state
    updateUI: function () {
        const userLinks = document.getElementById('userLinks');
        const guestLinks = document.getElementById('guestLinks');
        const mobileUserLinks = document.getElementById('mobileUserLinks');
        const mobileGuestLinks = document.getElementById('mobileGuestLinks');
        const usernameDisplay = document.getElementById('usernameDisplay');

        if (this.isAuthenticated()) {
            // Show user links and hide guest links
            if (userLinks) userLinks.style.display = 'block';
            if (guestLinks) guestLinks.style.display = 'none';
            if (mobileUserLinks) mobileUserLinks.style.display = 'block';
            if (mobileGuestLinks) mobileGuestLinks.style.display = 'none';

            // Update username if element exists
            if (usernameDisplay && currentUser) {
                usernameDisplay.textContent = currentUser.firstName || 'User';
            }
        } else {
            // Re-render navbar in case it relies on whoami
            if (window.Navbar && typeof window.Navbar.update === 'function') {
                window.Navbar.update();
            }
            // Show guest links and hide user links
            if (userLinks) userLinks.style.display = 'none';
            if (guestLinks) guestLinks.style.display = 'block';
            if (mobileUserLinks) mobileUserLinks.style.display = 'none';
            if (mobileGuestLinks) mobileGuestLinks.style.display = 'block';
        }
    },

    // Function to protect routes
    // ONLY runs access checks on entry pages (admin.html, dashboard.html)
    // Sub-pages (admin-profile.html, profile.html, messages.html) are excluded from checks
    protectRoute: function () {
        const publicPages = ['index.html', 'about.html', 'login.html', 'register.html', 'skills.html'];
        // Sub-pages that should NOT have access checks - they're accessed from entry pages
        const subPages = ['admin-profile.html', 'profile.html', 'messages.html', 'skill-edit.html'];
        
        const currentPath = window.location.pathname;
        // Treat root path (/) as index.html (landing page)
        const isRootPath = currentPath === '/' || currentPath === '/index.html' || currentPath.endsWith('/index.html');
        const isPublicPage = isRootPath || publicPages.some(page => currentPath.endsWith(page));
        const isSubPage = subPages.some(page => currentPath.endsWith(page));
        
        // Skip access checks on sub-pages - they're accessed from entry pages
        if (isSubPage) {
            return;
        }
        
        // Root path and index.html are always public - no redirect needed
        if (isRootPath) {
            return;
        }
        
        if (!this.isAuthenticated() && !isPublicPage) {
            window.location.href = '/login.html?redirect=' + encodeURIComponent(window.location.pathname);
        } else if (this.isAuthenticated() && (currentPath.endsWith('/login.html') ||
            currentPath.endsWith('/register.html'))) {
            // Check if user is admin
            const user = this.getCurrentUser();
            if (user && user.is_admin) {
                window.location.href = '/admin.html';
            } else {
                window.location.href = '/dashboard.html';
            }
        }
    }
};

// Initialize auth functionality
document.addEventListener('DOMContentLoaded', function () {
    // Add event listeners for logout buttons
    const logoutBtn = document.getElementById('logoutBtn');
    const mobileLogoutBtn = document.getElementById('mobileLogoutBtn');

    if (logoutBtn) {
        logoutBtn.addEventListener('click', function (e) {
            e.preventDefault();
            logout();
        });
    }

    if (mobileLogoutBtn) {
        mobileLogoutBtn.addEventListener('click', function (e) {
            e.preventDefault();
            logout();
        });
    }

    // Toggle mobile menu
    const hamburger = document.getElementById('hamburger');
    const mobileMenu = document.getElementById('mobileMenu');

    if (hamburger && mobileMenu) {
        hamburger.addEventListener('click', function () {
            mobileMenu.classList.toggle('active');
            hamburger.classList.toggle('active');
        });
    }

    // Try to sync with server session (whoami) before updating UI/protection
    fetch('backend/auth/whoami.php', { method: 'GET', credentials: 'include' })
        .then(resp => resp.json())
        .then(data => {
            if (data && data.ok) {
                if (data.user) {
                    currentUser = data.user;
                    if (data.user.csrf_token) {
                        csrfToken = data.user.csrf_token;
                        localStorage.setItem('csrfToken', csrfToken);
                    }
                    localStorage.setItem('currentUser', JSON.stringify(currentUser));
                } else {
                    // Server indicates user is not authenticated
                    currentUser = null;
                    localStorage.removeItem('currentUser');
                }
            }
        })
        .catch(() => {
            // ignore errors; fallback to localStorage
        })
        .finally(() => {
            // Update UI based on auth state
            updateUI();
            
            // Only run protectRoute on entry pages, not sub-pages
            // Sub-pages are excluded to prevent redirect loops
            const currentPath = window.location.pathname;
            const subPages = ['admin-profile.html', 'profile.html', 'messages.html', 'skill-edit.html'];
            const isSubPage = subPages.some(page => currentPath.endsWith(page));
            
            // Skip protectRoute on sub-pages to prevent redirect loops
            if (!isSubPage) {
                protectRoute();
            }
        });
});

// Backwards-compatible global helpers used by legacy inline scripts
function updateUI() { return window.Auth.updateUI(); }
function protectRoute() { return window.Auth.protectRoute(); }
function logout() { return window.Auth.logout(); }
function getCurrentUser() { return window.Auth.getCurrentUser(); }