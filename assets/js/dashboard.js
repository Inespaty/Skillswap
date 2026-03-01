/**
 * dashboard.js
 * Load current user dashboard with stats, recent skills, and activity
 */
document.addEventListener('DOMContentLoaded', async function () {
  // Require authentication
  if (!window.Auth || !window.Auth.isAuthenticated || !window.Auth.isAuthenticated()) {
    window.location.href = 'login.html';
    return;
  }

  const currentUser = window.Auth.getCurrentUser && window.Auth.getCurrentUser();
  if (!currentUser || !currentUser.id) {
    window.location.href = 'login.html';
    return;
  }

  const dashboardContainer = document.getElementById('dashboardContainer');

  if (!dashboardContainer) return;

  function renderStats(user, skillCount) {
    const statsHTML = `
      <div class="upwork-card mb-4">
        <div class="upwork-card-body">
          <div class="row g-3">
            <div class="col-md-3 col-6">
              <div class="d-flex align-items-center">
                <div class="me-3" style="width: 48px; height: 48px; background: #F7C5E5; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                  <i class="fas fa-coins" style="color: #C11C84; font-size: 1.5rem;"></i>
                </div>
                <div>
                  <div class="stat-value-upwork mb-0" style="font-size: 1.5rem;">${user.credits || 0}</div>
                  <div class="stat-label-upwork" style="font-size: 0.75rem; margin-top: 0.25rem;">Credits</div>
                </div>
              </div>
            </div>
            <div class="col-md-3 col-6">
              <div class="d-flex align-items-center">
                <div class="me-3" style="width: 48px; height: 48px; background: #F7C5E5; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                  <i class="fas fa-graduation-cap" style="color: #C11C84; font-size: 1.5rem;"></i>
                </div>
                <div>
                  <div class="stat-value-upwork mb-0" style="font-size: 1.5rem;">${skillCount || 0}</div>
                  <div class="stat-label-upwork" style="font-size: 0.75rem; margin-top: 0.25rem;">Skills</div>
                </div>
              </div>
            </div>
            <div class="col-md-3 col-6">
              <div class="d-flex align-items-center">
                <div class="me-3" style="width: 48px; height: 48px; background: #F7C5E5; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                  <i class="fas fa-star" style="color: #C11C84; font-size: 1.5rem;"></i>
                </div>
                <div>
                  <div class="stat-value-upwork mb-0" style="font-size: 1.5rem;">${user.reputation_score ? user.reputation_score.toFixed(1) : '0.0'}</div>
                  <div class="stat-label-upwork" style="font-size: 0.75rem; margin-top: 0.25rem;">Reputation</div>
                </div>
              </div>
            </div>
            <div class="col-md-3 col-6">
              <div class="d-flex align-items-center">
                <div class="me-3" style="width: 48px; height: 48px; background: #F7C5E5; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                  <i class="fas fa-envelope" style="color: #C11C84; font-size: 1.5rem;"></i>
                </div>
                <div>
                  <div class="stat-value-upwork mb-0" style="font-size: 1.5rem;">0</div>
                  <div class="stat-label-upwork" style="font-size: 0.75rem; margin-top: 0.25rem;">Messages</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    `;
    return statsHTML;
  }

  function renderSkillItem(skill) {
    // Determine status badge
    let statusBadge = '';
    if (skill.approval_status === 'pending') {
        statusBadge = '<span class="badge bg-warning text-dark">Pending Approval</span>';
    } else if (skill.approval_status === 'rejected') {
        statusBadge = '<span class="badge bg-danger">Rejected</span>';
    } else if (skill.active_status === 1 && skill.approval_status === 'approved') {
        statusBadge = '<span class="badge bg-success">Active</span>';
    } else {
        statusBadge = '<span class="badge bg-secondary">Inactive</span>';
    }
    return `
      <tr>
        <td>
          <div class="d-flex align-items-center">
            ${skill.image ? `<img src="${skill.image}" alt="${skill.title}" class="me-2" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;">` : ''}
            <strong>${skill.title}</strong>
          </div>
        </td>
        <td><span class="badge-upwork badge-upwork-primary">${skill.category_name || 'Uncategorized'}</span></td>
        <td>${statusBadge}</td>
        <td>
          <div class="d-flex gap-2">
            <a href="skill-edit.html?id=${skill.id}" class="btn btn-upwork-secondary btn-sm">Edit</a>
            <button onclick="SkillUtils.deleteSkill(${skill.id}, event)" class="btn btn-sm" style="background-color: #1e293b; color: #FFFFFF; border: none;">
              <i class="fas fa-trash"></i>
            </button>
          </div>
        </td>
      </tr>
    `;
  }

  async function loadDashboard() {
    try {
      // Parallel data fetching
      const [profileRes, messagesRes, requestsRes] = await Promise.all([
        fetch(`backend/api/user_profile.php?id=${currentUser.id}`, { credentials: 'include' }),
        fetch(`backend/chat/get_conversations.php`, { credentials: 'include' }),
        fetch(`backend/requests/get_user_requests.php?limit=5`, { credentials: 'include' })
      ]);

      const profileData = await profileRes.json();
      const messagesData = await messagesRes.json();
      const requestsData = await requestsRes.json();

      if (profileData && profileData.ok && profileData.user) {
        const user = profileData.user;
        const skills = Array.isArray(profileData.skills) ? profileData.skills : [];
        const skillCount = skills.length;

        // Calculate unread messages
        let unreadMessages = 0;
        if (messagesData.success && Array.isArray(messagesData.conversations)) {
          unreadMessages = messagesData.conversations.reduce((sum, conv) => sum + (parseInt(conv.unread_count) || 0), 0);
        }

        dashboardContainer.innerHTML = `
          <div class="container">
            ${renderStats(user, skillCount, unreadMessages)}

            <div class="row">
              <div class="col-lg-8">
                <!-- Requests Section -->
                <div class="upwork-card mb-4">
                  <div class="upwork-card-header d-flex justify-content-between align-items-center">
                    <h5 class="upwork-card-title mb-0">Recent Requests</h5>
                    <a href="requests.html" class="btn btn-upwork-secondary btn-sm">View All</a>
                  </div>
                  <div class="upwork-card-body">
                    ${renderRequests(requestsData.requests)}
                  </div>
                </div>

                <!-- Skills Section -->
                <div class="upwork-card">
                  <div class="upwork-card-header">
                    <h5 class="upwork-card-title mb-0">Your Skills</h5>
                    <a href="skill-edit.html" class="btn btn-upwork-primary btn-sm">
                      <i class="fas fa-plus me-1"></i> Add New
                    </a>
                  </div>
                  <div class="upwork-card-body">
                    ${skills.length > 0 ? `
                      <div class="table-responsive">
                        <table class="table-upwork table">
                          <thead>
                            <tr>
                              <th>Skill</th>
                              <th>Category</th>
                              <th>Status</th>
                              <th>Actions</th>
                            </tr>
                          </thead>
                          <tbody>
                            ${skills.slice(0, 5).map(renderSkillItem).join('')}
                          </tbody>
                        </table>
                      </div>
                      ${skills.length > 5 ? `<div class="text-center mt-3"><a href="skills.html" class="btn btn-upwork-secondary">View All Skills</a></div>` : ''}
                    ` : `
                      <div class="empty-state">
                        <div class="empty-state-icon"><i class="fas fa-graduation-cap"></i></div>
                        <div class="empty-state-title">No skills yet</div>
                        <div class="empty-state-text">Start sharing your knowledge with others</div>
                        <a href="skill-edit.html" class="btn btn-upwork-primary mt-3">
                          <i class="fas fa-plus me-1"></i> Create Your First Skill
                        </a>
                      </div>
                    `}
                  </div>
                </div>
              </div>

              <div class="col-lg-4">
                <div class="upwork-card mb-3">
                  <div class="upwork-card-header">
                    <h6 class="upwork-card-title mb-0">Quick Actions</h6>
                  </div>
                  <div class="upwork-card-body">
                    <a href="skill-edit.html" class="btn btn-upwork-primary w-100 mb-2">
                      <i class="fas fa-plus me-1"></i> Share a Skill
                    </a>
                    <a href="profile.html" class="btn btn-upwork-secondary w-100 mb-2">
                      <i class="fas fa-user me-1"></i> View Profile
                    </a>
                    <a href="skills.html" class="btn btn-upwork-secondary w-100 mb-2">
                      <i class="fas fa-search me-1"></i> Browse Skills
                    </a>
                    <a href="messages.html" class="btn btn-upwork-secondary w-100">
                      <i class="fas fa-envelope me-1"></i> Messages
                      ${unreadMessages > 0 ? `<span class="badge bg-danger ms-2">${unreadMessages}</span>` : ''}
                    </a>
                  </div>
                </div>

                ${currentUser.is_admin ? `
                  <div class="upwork-card">
                    <div class="upwork-card-header">
                      <h6 class="upwork-card-title mb-0">Admin Tools</h6>
                    </div>
                    <div class="upwork-card-body">
                      <a href="admin.html" class="btn btn-upwork-secondary w-100">
                        <i class="fas fa-cog me-1"></i> Admin Panel
                      </a>
                    </div>
                  </div>
                ` : ''}
              </div>
            </div>
          </div>
        `;
      } else {
        const errorMsg = profileData.error || 'Failed to load dashboard.';
        dashboardContainer.innerHTML = `<p class="alert alert-danger">${errorMsg}</p>`;
      }
    } catch (err) {
      console.error('Load dashboard error', err);
      dashboardContainer.innerHTML = '<p class="alert alert-danger">Error loading dashboard.</p>';
    }
  }

  function renderRequests(requests) {
    if (!requests || requests.length === 0) {
      return '<p class="text-muted text-center py-3">No recent requests.</p>';
    }

    return `
      <div class="list-group list-group-flush">
        ${requests.map(req => {
      const isIncoming = req.to_user_id == currentUser.id;
      const statusBadge = getStatusBadge(req.status);
      const otherName = req.other_user_name || 'User';
      
      // Show accept/reject buttons for incoming pending requests
      let actions = '';
      if (isIncoming && req.status === 'pending') {
        actions = `
          <div class="d-flex gap-2 mt-2">
            <button class="btn btn-sm btn-success" onclick="handleDashboardRequest(${req.request_id}, 'accept')">
              <i class="fas fa-check me-1"></i> Accept
            </button>
            <button class="btn btn-sm btn-outline-danger" onclick="handleDashboardRequest(${req.request_id}, 'reject')">
              <i class="fas fa-times me-1"></i> Reject
            </button>
          </div>
        `;
      }

      return `
            <div class="list-group-item px-0 py-3">
              <div class="d-flex justify-content-between align-items-start">
                <div class="d-flex align-items-center flex-grow-1">
                  <div class="me-3">
                    <i class="fas ${isIncoming ? 'fa-arrow-down text-success' : 'fa-arrow-up text-primary'}"></i>
                  </div>
                  <div class="flex-grow-1">
                    <h6 class="mb-0 fw-bold" style="color: #222222;">${escapeHtml(req.skill_title || 'Untitled Skill')}</h6>
                    <small class="text-muted" style="color: #666666;">
                      ${isIncoming ? 'From' : 'To'} ${escapeHtml(otherName)} • ${req.hours_required || 1} ${req.hours_required == 1 ? 'hr' : 'hrs'}
                    </small>
                    ${actions}
                  </div>
                </div>
                <div>
                   ${statusBadge}
                </div>
              </div>
            </div>
          `;
    }).join('')}
      </div>
    `;
  }

  // Handle accept/reject actions for requests on dashboard
  window.handleDashboardRequest = function (requestId, action) {
    if (!confirm(`Are you sure you want to ${action} this request?`)) return;

    fetch('backend/requests/respond_request.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ request_id: requestId, action: action }),
      credentials: 'include'
    })
      .then(r => r.json())
      .then(data => {
        if (data.success || data.ok) {
          // Reload the dashboard to show updated requests
          loadDashboard();
        } else {
          alert(data.error || 'Action failed');
        }
      })
      .catch(e => {
        console.error(e);
        alert('System error');
      });
  };

  let notificationPollInterval = null;

  async function loadNotifications(showLoading = true) {
    try {
      if (!dashboardContainer) {
        console.error('Dashboard container not found');
        return;
      }

      // Show loading state only on initial load
      if (showLoading) {
        dashboardContainer.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div><p class="mt-3 text-muted">Loading notifications...</p></div>';
      }

      // Load notifications only
      const notificationsRes = await fetch('backend/notifications/get_notifications.php?limit=50', { credentials: 'include' });
      const notificationsData = await notificationsRes.json();

      const notifications = notificationsData.success ? notificationsData.notifications || [] : [];

      // Only update HTML if we're on the notifications page
      if (window.location.hash === '#notifications') {
        const notificationsListContainer = document.getElementById('notificationsListContainer');
        
        if (notificationsListContainer && !showLoading) {
          // Update only the list if container already exists (polling refresh)
          notificationsListContainer.innerHTML = renderNotificationsList(notifications);
        } else {
          // Full page render (initial load)
          dashboardContainer.innerHTML = `
            <div class="container">
              <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                  <h2 class="page-title-upwork mb-1">Notifications</h2>
                  <p class="page-subtitle-upwork">All your notifications and updates</p>
                </div>
                <a href="dashboard.html" class="btn btn-upwork-secondary">
                  <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
                </a>
              </div>

              <div class="row">
                <div class="col-lg-8">
                  <!-- All Notifications -->
                  <div class="upwork-card">
                    <div class="upwork-card-header">
                      <h5 class="upwork-card-title mb-0">All Notifications</h5>
                    </div>
                    <div class="upwork-card-body" id="notificationsListContainer">
                      ${renderNotificationsList(notifications)}
                    </div>
                  </div>
                </div>
              </div>
            </div>
          `;

          // Scroll to top only on initial load
          if (showLoading) {
            window.scrollTo(0, 0);
          }
        }
      }
    } catch (err) {
      console.error('Load notifications error', err);
      if (window.location.hash === '#notifications') {
        dashboardContainer.innerHTML = '<p class="alert alert-danger">Error loading notifications.</p>';
      }
    }
  }

  function renderNotificationsList(notifications) {
    if (!notifications || notifications.length === 0) {
      return '<p class="text-muted text-center py-3">No notifications yet.</p>';
    }

    return `
      <div class="list-group list-group-flush">
        ${notifications.map(notif => {
          const isUnread = !notif.is_read && notif.read_status === 0;
          const timeAgo = formatTimeAgo(notif.created_at);
          const notificationId = notif.notification_id;
          const actionUrl = notif.action_url || '#';
          
          return `
            <div class="list-group-item px-0 py-3 notification-item ${isUnread ? 'bg-light' : ''}" 
                 data-notification-id="${notificationId}"
                 style="border-left: ${isUnread ? '4px solid #C11C84' : 'none'}; cursor: pointer;"
                 onclick="handleNotificationClick(${notificationId}, '${escapeHtml(actionUrl)}', event)">
              <div class="d-flex justify-content-between align-items-start">
                <div class="flex-grow-1">
                  <div class="d-flex align-items-center mb-1">
                    <i class="fas ${getNotificationIcon(notif.type)} me-2" style="color: #C11C84;"></i>
                    <h6 class="mb-0 ${isUnread ? 'fw-bold' : 'fw-normal'}" style="color: #222222;">${escapeHtml(notif.title || notif.message)}</h6>
                  </div>
                  <p class="mb-1 text-muted small" style="color: #666666;">${escapeHtml(notif.message || '')}</p>
                  <small class="text-muted">${timeAgo}</small>
                </div>
                ${isUnread ? '<span class="badge bg-danger ms-2" id="badge-' + notificationId + '">New</span>' : ''}
              </div>
            </div>
          `;
        }).join('')}
      </div>
    `;
  }

  // Handle notification click - mark as read and navigate if action_url exists
  window.handleNotificationClick = async function(notificationId, actionUrl, event) {
    // Prevent double clicks
    if (event.target.closest('.notification-item').hasAttribute('data-processing')) {
      return;
    }
    event.target.closest('.notification-item').setAttribute('data-processing', 'true');

    try {
      // Mark notification as read
      const markReadRes = await fetch('backend/notifications/mark_read.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ notification_id: notificationId }),
        credentials: 'include'
      });

      const markReadData = await markReadRes.json();
      
      if (markReadData.success) {
        // Remove "New" badge immediately
        const badge = document.getElementById('badge-' + notificationId);
        if (badge) {
          badge.remove();
        }

        // Update notification item styling
        const notificationItem = document.querySelector(`[data-notification-id="${notificationId}"]`);
        if (notificationItem) {
          notificationItem.classList.remove('bg-light');
          notificationItem.style.borderLeft = 'none';
          const heading = notificationItem.querySelector('h6');
          if (heading) {
            heading.classList.remove('fw-bold');
            heading.classList.add('fw-normal');
          }
        }

        // Update navbar notification badge
        if (window.Navbar && typeof window.Navbar.updateNotificationBadge === 'function') {
          window.Navbar.updateNotificationBadge();
        }

        // Navigate to action URL if it exists and is not just '#'
        if (actionUrl && actionUrl !== '#' && !actionUrl.startsWith('javascript:')) {
          // Small delay to show the UI update before navigation
          setTimeout(() => {
            window.location.href = actionUrl;
          }, 200);
        }
      }
    } catch (err) {
      console.error('Error marking notification as read:', err);
    } finally {
      event.target.closest('.notification-item').removeAttribute('data-processing');
    }
  };

  function getNotificationIcon(type) {
    const icons = {
      'request_received': 'fa-handshake',
      'request_accepted': 'fa-check-circle',
      'request_rejected': 'fa-times-circle',
      'message': 'fa-envelope',
      'skill_approved': 'fa-check',
      'skill_rejected': 'fa-times',
      'default': 'fa-bell'
    };
    return icons[type] || icons.default;
  }

  function formatTimeAgo(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const seconds = Math.floor((now - date) / 1000);
    
    if (seconds < 60) return 'Just now';
    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) return `${minutes}m ago`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours}h ago`;
    const days = Math.floor(hours / 24);
    if (days < 7) return `${days}d ago`;
    return date.toLocaleDateString();
  }

  function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  // Make escapeHtml available in renderRequests scope
  window.escapeHtml = escapeHtml;

  function getStatusBadge(status) {
    switch (status) {
      case 'pending': return '<span class="badge bg-warning text-dark">Pending</span>';
      case 'accepted': return '<span class="badge bg-success">Active</span>';
      case 'rejected': return '<span class="badge bg-danger">Rejected</span>';
      case 'completed': return '<span class="badge bg-info text-dark">Completed</span>';
      default: return '<span class="badge bg-secondary">' + status + '</span>';
    }
  }

  function renderStats(user, skillCount, unreadMessages) {
    const statsHTML = `
      <div class="upwork-card mb-4">
        <div class="upwork-card-body">
          <div class="row g-3">
            <div class="col-md-3 col-6">
              <div class="d-flex align-items-center">
                <div class="me-3" style="width: 48px; height: 48px; background: #F7C5E5; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                  <i class="fas fa-coins" style="color: #C11C84; font-size: 1.5rem;"></i>
                </div>
                <div>
                  <div class="stat-value-upwork mb-0" style="font-size: 1.5rem;">${user.credits || 0}</div>
                  <div class="stat-label-upwork" style="font-size: 0.75rem; margin-top: 0.25rem;">Credits</div>
                </div>
              </div>
            </div>
            <div class="col-md-3 col-6">
              <div class="d-flex align-items-center">
                <div class="me-3" style="width: 48px; height: 48px; background: #E0F2FE; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                  <i class="fas fa-graduation-cap" style="color: #0EA5E9; font-size: 1.5rem;"></i>
                </div>
                <div>
                  <div class="stat-value-upwork mb-0" style="font-size: 1.5rem;">${skillCount || 0}</div>
                  <div class="stat-label-upwork" style="font-size: 0.75rem; margin-top: 0.25rem;">Skills</div>
                </div>
              </div>
            </div>
            <div class="col-md-3 col-6">
              <div class="d-flex align-items-center">
                <div class="me-3" style="width: 48px; height: 48px; background: #FDE68A; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                  <i class="fas fa-star" style="color: #D97706; font-size: 1.5rem;"></i>
                </div>
                <div>
                  <div class="stat-value-upwork mb-0" style="font-size: 1.5rem;">${user.reputation_score ? parseFloat(user.reputation_score).toFixed(1) : '0.0'}</div>
                  <div class="stat-label-upwork" style="font-size: 0.75rem; margin-top: 0.25rem;">Reputation</div>
                </div>
              </div>
            </div>
            <div class="col-md-3 col-6">
              <div class="d-flex align-items-center">
                <div class="me-3" style="width: 48px; height: 48px; background: #DCFCE7; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                  <i class="fas fa-envelope" style="color: #16A34A; font-size: 1.5rem;"></i>
                </div>
                <div>
                  <div class="stat-value-upwork mb-0" style="font-size: 1.5rem;">${unreadMessages}</div>
                  <div class="stat-label-upwork" style="font-size: 0.75rem; margin-top: 0.25rem;">Messages</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    `;
    return statsHTML;
  }

  // Start/stop polling for notifications
  function startNotificationPolling() {
    // Clear any existing interval
    if (notificationPollInterval) {
      clearInterval(notificationPollInterval);
    }

    // Poll every 5 seconds for real-time updates
    notificationPollInterval = setInterval(() => {
      if (window.location.hash === '#notifications') {
        loadNotifications(false); // Don't show loading spinner on refresh
      }
    }, 5000);
  }

  function stopNotificationPolling() {
    if (notificationPollInterval) {
      clearInterval(notificationPollInterval);
      notificationPollInterval = null;
    }
  }

  // Handle hash changes for notifications
  window.addEventListener('hashchange', function() {
    if (window.location.hash === '#notifications') {
      loadNotifications(true);
      startNotificationPolling();
    } else {
      stopNotificationPolling();
      loadDashboard();
    }
  });

  // Initial load - check hash first
  if (window.location.hash === '#notifications') {
    loadNotifications(true);
    startNotificationPolling();
  } else {
    loadDashboard();
  }

  // Cleanup on page unload
  window.addEventListener('beforeunload', () => {
    stopNotificationPolling();
  });
});
