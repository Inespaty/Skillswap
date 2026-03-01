/**
 * Access Control Helper
 * ONLY used on entry pages (admin.html, dashboard.html)
 * Sub-pages (admin-profile.html, profile.html, messages.html) don't need this
 */

// Check if user is admin - ONLY call this on admin.html
async function checkAdminAccess() {
    try {
        const response = await fetch('backend/auth/whoami.php');
        const data = await response.json();

        if (!data.ok || !data.user) {
            window.location.replace('login.html');
            return false;
        }

        if (!data.user.is_admin) {
            window.location.replace('dashboard.html');
            return false;
        }

        return true;
    } catch (error) {
        console.error('Error checking admin access:', error);
        window.location.replace('login.html');
        return false;
    }
}

// Check if user is regular user - ONLY call this on dashboard.html
async function checkUserAccess() {
    try {
        const response = await fetch('backend/auth/whoami.php');
        const data = await response.json();

        if (!data.ok || !data.user) {
            window.location.replace('login.html');
            return false;
        }

        if (data.user.is_admin === true || data.user.is_admin === 1) {
            window.location.replace('admin.html');
            return false;
        }

        return true;
    } catch (error) {
        console.error('Error checking user access:', error);
        window.location.replace('login.html');
        return false;
    }
}
