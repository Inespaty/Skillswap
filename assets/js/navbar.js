/**
 * navbar.js
 * Initialize navbar with current user info and admin menu if applicable
 */
function initializeNavbar() {
  const navbarAuthButtons = document.getElementById('navbarAuthButtons');
  const navbarAdminMenu = document.getElementById('navbarAdminMenu');

  if (!navbarAuthButtons) {
    // If navbar not loaded yet, try again after a short delay
    setTimeout(initializeNavbar, 100);
    return;
  }

  function renderAuthenticatedNav(user) {
    // Show authenticated links: Dashboard
    const dashboardLink = document.getElementById('navbarDashboardLink');
    const aboutLink = document.getElementById('navbarAboutLink');

    // Show Dashboard for logged-in users
    if (dashboardLink) {
      dashboardLink.style.display = 'block';
      dashboardLink.style.visibility = 'visible';
    }
    // Hide About link for logged-in users
    if (aboutLink) {
      aboutLink.style.display = 'none';
      aboutLink.style.visibility = 'hidden';
    }

    // Handle profile picture path - ensure it's properly formatted
    let profilePicUrl = user.profile_pic || '';
    if (profilePicUrl && !profilePicUrl.startsWith('http') && !profilePicUrl.startsWith('/') && !profilePicUrl.startsWith('assets/')) {
      // If it's a relative path like "uploads/avatars/xxx.jpg", make it "./uploads/avatars/xxx.jpg"
      profilePicUrl = './' + profilePicUrl;
    }
    if (!profilePicUrl) {
      profilePicUrl = 'assets/img/default-avatar.png';
    }

    navbarAuthButtons.innerHTML = `
      <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false" style="color: #0F172A; padding: 0; gap: 0.5rem;">
          <img src="${profilePicUrl}" 
               alt="${user.name}" class="rounded-circle" 
               style="width: 28px; height: 28px; object-fit: cover; background-color: #e5e7eb;"
               onerror="this.onerror=null; this.src='assets/img/default-avatar.png';">
          <span style="font-weight: 600; color: #0F172A;">${user.name}</span>
        </a>
        <ul class="dropdown-menu dropdown-menu-end" id="userDropdownMenu" aria-labelledby="userDropdown" style="max-height: 400px; overflow-y: auto; background-color: #1e293b; border: none;">
          <li><a class="dropdown-item" href="profile.html" style="color: #FFFFFF;"><i class="fas fa-user me-2" style="color: #C11C84; width: 1rem;"></i>My Profile</a></li>
          <li><a class="dropdown-item" href="messages.html" style="color: #FFFFFF;"><i class="fas fa-envelope me-2" style="color: #C11C84; width: 1rem;"></i>Messages</a></li>
          <li><a class="dropdown-item" href="skill-edit.html" style="color: #FFFFFF;"><i class="fas fa-plus me-2" style="color: #C11C84; width: 1rem;"></i>Create Skill</a></li>
          <li><a class="dropdown-item" href="skills.html" style="color: #FFFFFF;"><i class="fas fa-search me-2" style="color: #C11C84; width: 1rem;"></i>Browse Skills</a></li>
          <li><a class="dropdown-item position-relative" href="dashboard.html#notifications" style="color: #FFFFFF;"><i class="fas fa-bell me-2" style="color: #C11C84; width: 1rem;"></i>Notifications<span id="notificationBadgeInDropdown" class="ms-2"></span></a></li>
          <li><a class="dropdown-item" href="transactions.html" style="color: #FFFFFF;"><i class="fas fa-history me-2" style="color: #C11C84; width: 1rem;"></i>Transactions</a></li>
          <li><hr class="dropdown-divider" style="background-color: rgba(255, 255, 255, 0.2);"></li>
          <li><a class="dropdown-item" href="#" onclick="event.preventDefault(); window.Auth.logout();" style="color: #FFFFFF;"><i class="fas fa-sign-out-alt me-2" style="width: 1rem;"></i>Logout</a></li>
        </ul>
      </li>
    `;

    // Add admin menu if user is admin
    if (user.is_admin) {
      navbarAdminMenu.style.display = 'block';
    } else {
      navbarAdminMenu.style.display = 'none';
    }
  }

  function renderUnauthenticatedNav() {
    // Show guest links: About, Log In, Sign Up
    const dashboardLink = document.getElementById('navbarDashboardLink');
    const messagesLink = document.getElementById('navbarMessagesLink');
    const profileLink = document.getElementById('navbarProfileLink');
    const aboutLink = document.getElementById('navbarAboutLink');

    // Hide authenticated links
    if (dashboardLink) {
      dashboardLink.style.display = 'none';
      dashboardLink.style.visibility = 'hidden';
    }
    // Show About link for guests
    if (aboutLink) {
      aboutLink.style.display = 'block';
      aboutLink.style.visibility = 'visible';
    }


    navbarAuthButtons.innerHTML = `
      <a class="nav-link fw-600" href="login.html" style="color: #1e293b; white-space: nowrap; margin-right: 0.5rem;">Log In</a>
      <a class="btn btn-primary btn-sm fw-600" href="register.html" style="padding: 0.5rem 1.25rem; white-space: nowrap; background-color: #C11C84; border-color: #C11C84;">Sign Up</a>
    `;
    // Ensure the container is visible
    navbarAuthButtons.style.display = 'flex';
    navbarAuthButtons.style.visibility = 'visible';
    navbarAdminMenu.style.display = 'none';
  }

  function loadNavbarUser() {
    // Show guest navbar by default (will be updated if user is authenticated)
    renderUnauthenticatedNav();

    // Use auth.js whoami integration if available
    if (window.Auth && window.Auth.isAuthenticated && window.Auth.isAuthenticated()) {
      const user = window.Auth.getCurrentUser && window.Auth.getCurrentUser();
      if (user) {
        renderAuthenticatedNav(user);
        return;
      }
    }

    // Fallback: fetch whoami directly
    fetch('backend/auth/whoami.php', { credentials: 'include' })
      .then(r => r.json())
      .then(data => {
        if (data && data.ok && data.user) {
          renderAuthenticatedNav(data.user);
        } else {
          renderUnauthenticatedNav();
        }
      })
      .catch(err => {
        console.error('Navbar whoami error', err);
        renderUnauthenticatedNav();
      });
  }

  // Notification badge logic
  function updateNotificationBadge() {
    if (!window.Auth || !window.Auth.isAuthenticated()) return;

    fetch('backend/notifications/get_notifications.php?unread_only=true', { credentials: 'include' })
      .then(r => r.json())
      .then(data => {
        if (data && data.success) {
          const count = data.unread_count || 0;
          renderNotificationBadge(count);

          // Also check for unread messages to badge that link specifically
          checkUnreadMessages();
        }
      })
      .catch(err => console.error('Notification check failed', err));
  }

  function checkUnreadMessages() {
    fetch('backend/chat/get_conversations.php', { credentials: 'include' })
      .then(r => r.json())
      .then(data => {
        if (data && data.success && Array.isArray(data.conversations)) {
          const unreadMsgs = data.conversations.reduce((sum, c) => sum + (parseInt(c.unread_count) || 0), 0);
          const msgLink = document.querySelector('#userDropdownMenu a[href="messages.html"]');
          if (msgLink) {
            if (unreadMsgs > 0) {
              msgLink.innerHTML = `<i class="fas fa-envelope me-2" style="color: #C11C84; width: 1rem;"></i>Messages <span class="badge bg-danger ms-1" style="font-size: 0.6rem;">${unreadMsgs}</span>`;
            } else {
              msgLink.innerHTML = '<i class="fas fa-envelope me-2" style="color: #C11C84; width: 1rem;"></i>Messages';
            }
          }
        }
      })
      .catch(e => console.error('Message check failed', e));
  }

  function renderNotificationBadge(count) {
    // Show notifications badge in the user dropdown menu
    const notificationBadgeInDropdown = document.getElementById('notificationBadgeInDropdown');

    if (notificationBadgeInDropdown) {
      if (count > 0) {
        notificationBadgeInDropdown.innerHTML = `<span class="badge bg-danger rounded-pill" style="font-size: 0.65rem;">${count > 99 ? '99+' : count}</span>`;
      } else {
        notificationBadgeInDropdown.innerHTML = '';
      }
    }
  }

  // Load navbar user state
  loadNavbarUser();

  // Poll for notifications every 30 seconds
  setInterval(updateNotificationBadge, 30000);
  // Initial check after short delay
  setTimeout(updateNotificationBadge, 1000);

  // Expose a global helper to update navbar externally
  window.Navbar = window.Navbar || {};
  window.Navbar.update = loadNavbarUser;
  window.Navbar.updateNotificationBadge = updateNotificationBadge;
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function () {
  initializeNavbar();
});

// Also allow manual initialization if called after DOM load
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initializeNavbar);
} else {
  // DOM already loaded, initialize immediately
  initializeNavbar();
}

// System Toast Notification Helper
window.showToast = function (message, type = 'info') {
  let container = document.getElementById('toast-container');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toast-container';
    container.className = 'toast-container position-fixed top-0 end-0 p-3';
    container.style.zIndex = '9999';
    document.body.appendChild(container);
  }
  const bgClass = type === 'error' || type === 'danger' ? 'text-bg-danger' : (type === 'success' ? 'text-bg-success' : 'text-bg-primary');
  const icon = type === 'error' || type === 'danger' ? 'fa-exclamation-circle' : (type === 'success' ? 'fa-check-circle' : 'fa-info-circle');
  const html = `
        <div class="toast align-items-center ${bgClass} border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body"><i class="fas ${icon} me-2"></i> ${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    `;
  const t = document.createElement('div');
  t.innerHTML = html.trim();
  const toastEl = t.firstChild;
  container.appendChild(toastEl);
  const bsToast = new bootstrap.Toast(toastEl, { delay: 3000 });
  bsToast.show();
  toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
};
