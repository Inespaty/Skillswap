/**
 * Admin Dashboard - Load real metrics from database
 */
document.addEventListener('DOMContentLoaded', function () {
    // Access control is handled by access-control.js
    // Just check if authenticated
    if (!window.Auth || !window.Auth.isAuthenticated || !window.Auth.isAuthenticated()) {
        window.location.href = 'login.html';
        return;
    }

    // --- State ---
    let currentView = 'dashboard';

    // --- Tab Switching ---
    window.switchView = function (viewName) {
        // Update nav links
        document.querySelectorAll('.sidebar .nav-link').forEach(el => {
            el.classList.remove('active');
            if (el.dataset.view === viewName) el.classList.add('active');
        });

        // Hide all views
        document.getElementById('view-dashboard').style.display = 'none';
        document.getElementById('view-users').style.display = 'none';
        document.getElementById('view-skills').style.display = 'none';
        const categoriesView = document.getElementById('view-categories');
        if (categoriesView) categoriesView.style.display = 'none';
        const logsView = document.getElementById('view-logs');
        if (logsView) logsView.style.display = 'none';

        // Show selected
        const target = document.getElementById(`view-${viewName}`);
        if (target) {
            target.style.display = 'block';
            currentView = viewName;

            // Load view specific data
            if (viewName === 'users') loadUsers();
            if (viewName === 'skills') loadSkills();
            if (viewName === 'categories') loadCategories();
            if (viewName === 'logs') loadSystemLogs();
            if (viewName === 'dashboard') loadAdminMetrics();
        }
    };

    // ... (rest of dashboard logic) ...

    // --- Skills Logic ---
    window.loadSkills = function () {
        const tbody = document.getElementById('skillsTableBody');
        if (tbody) tbody.innerHTML = '<tr><td colspan="5" class="text-center">Loading...</td></tr>';

        fetch('backend/admin/get_pending_skills.php', { credentials: 'include' })
            .then(r => r.json())
            .then(data => {
                const tbody = document.getElementById('skillsTableBody');
                if (!tbody) return;

                if (data.success && data.skills && data.skills.length > 0) {
                    tbody.innerHTML = data.skills.map(skill => `
                        <tr>
                            <td>
                                <div class="fw-bold">${escapeHtml(skill.title)}</div>
                                <small class="text-muted">${escapeHtml(skill.description)}</small>
                            </td>
                            <td>${escapeHtml(skill.user_name)}</td>
                            <td>${escapeHtml(skill.category_name || 'Uncategorized')}</td>
                            <td>${formatDate(skill.created_at)}</td>
                            <td>
                                <button class="btn btn-sm btn-success me-1" onclick="approveSkill(${skill.skill_id}, 'approve')" title="Approve">
                                    <i class="fas fa-check"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="approveSkill(${skill.skill_id}, 'reject')" title="Reject">
                                    <i class="fas fa-times"></i>
                                </button>
                            </td>
                        </tr>
                    `).join('');
                } else {
                    if (data.error) {
                        console.error('API Error:', data.error);
                        showToast(data.error, 'error');
                    }
                    tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No pending skills found.</td></tr>';
                }
            })
            .catch(e => console.error(e));
    }

    window.approveSkill = function (skillId, action) {
        let reason = '';
        if (action === 'reject') {
            reason = prompt('Enter rejection reason:');
            if (reason === null) return; // Cancelled
        }

        fetch('backend/admin/approve_skill.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ skill_id: skillId, action: action, rejection_reason: reason }),
            credentials: 'include'
        })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    loadSkills(); // Refresh
                    loadAdminMetrics(); // Refresh stats
                } else {
                    showToast(data.error || 'Action failed', 'error');
                }
            })
            .catch(e => {
                console.error(e);
                showToast('System error', 'error');
            });
    }

    function getStatusColor(status) {
        switch (status) {
            case 'open': return 'danger';
            case 'investigating': return 'warning';
            case 'resolved': return 'success';
            case 'closed': return 'secondary';
            default: return 'primary';
        }
    }

    // --- Categories Logic ---
    window.loadCategories = function () {
        const tbody = document.getElementById('categoriesTableBody');
        if (tbody) tbody.innerHTML = '<tr><td colspan="4" class="text-center">Loading...</td></tr>';

        // Attach form handler when categories view is loaded
        attachCategoryFormHandler();

        fetch('backend/api/categories_list.php', { credentials: 'include' })
            .then(r => r.json())
            .then(data => {
                const tbody = document.getElementById('categoriesTableBody');
                if (!tbody) return;

                if (data.ok && data.categories && data.categories.length > 0) {
                    tbody.innerHTML = data.categories.map(cat => {
                        // Escape for HTML display and for JavaScript string
                        const nameEscaped = escapeHtml(cat.name || '');
                        // For JavaScript onclick, we need to escape quotes
                        const nameForJs = (cat.name || '').replace(/'/g, "\\'").replace(/"/g, '\\"');
                        return `
                        <tr>
                            <td>${cat.id || ''}</td>
                            <td><strong>${nameEscaped}</strong></td>
                            <td>${cat.created_at ? formatDate(cat.created_at) : 'N/A'}</td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-primary" onclick="editCategory(${cat.id}, '${nameForJs}')" title="Edit">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button class="btn btn-outline-danger" onclick="deleteCategory(${cat.id})" title="Delete">
                                        <i class="fas fa-trash-alt"></i> Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `;
                    }).join('');
                } else {
                    tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No categories found.</td></tr>';
                }
            })
            .catch(e => {
                console.error(e);
                if (tbody) tbody.innerHTML = '<tr><td colspan="4" class="text-center text-danger">Error loading categories</td></tr>';
            });
    }

    function attachCategoryFormHandler() {
        const createCategoryForm = document.getElementById('createCategoryForm');
        if (!createCategoryForm) return;

        // Remove any existing listeners to avoid duplicates
        const newForm = createCategoryForm.cloneNode(true);
        createCategoryForm.parentNode.replaceChild(newForm, createCategoryForm);

        // Attach new listener
        document.getElementById('createCategoryForm').addEventListener('submit', function (e) {
            e.preventDefault();
            e.stopPropagation();

            const nameInput = document.getElementById('newCategoryName');
            const messageDiv = document.getElementById('categoryMessage');

            const name = nameInput.value.trim();
            if (!name) {
                if (messageDiv) {
                    messageDiv.innerHTML = '<div class="text-danger">Please enter a category name</div>';
                }
                return false;
            }

            fetch('backend/api/categories_create.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name: name }),
                credentials: 'include'
            })
                .then(r => r.json())
                .then(data => {
                    if (data.ok) {
                        if (messageDiv) {
                            messageDiv.innerHTML = '<div class="text-success">Category created successfully!</div>';
                        }
                        nameInput.value = '';
                        // Refresh categories list from database
                        loadCategories();
                        // Refresh skills view if open (categories might have changed)
                        if (currentView === 'skills') {
                            loadSkills();
                        }
                        setTimeout(() => {
                            if (messageDiv) messageDiv.innerHTML = '';
                        }, 3000);
                    } else {
                        if (messageDiv) {
                            messageDiv.innerHTML = '<div class="text-danger">' + (data.error || 'Failed to create category') + '</div>';
                        }
                    }
                })
                .catch(e => {
                    console.error(e);
                    if (messageDiv) {
                        messageDiv.innerHTML = '<div class="text-danger">Network error</div>';
                    }
                });

            return false;
        });
    }

    window.editCategory = function (categoryId, currentName) {
        // Populate edit modal - decode HTML entities
        document.getElementById('editCategoryId').value = categoryId;
        document.getElementById('editCategoryName').value = decodeHtml(currentName);

        // Clear any previous messages
        const messageDiv = document.getElementById('editCategoryMessage');
        if (messageDiv) messageDiv.innerHTML = '';

        // Show modal
        const editModal = new bootstrap.Modal(document.getElementById('editCategoryModal'));
        editModal.show();
    }

    function decodeHtml(html) {
        if (!html) return '';
        const txt = document.createElement('textarea');
        txt.innerHTML = html;
        return txt.value;
    }

    window.saveCategoryEdit = function () {
        const categoryId = document.getElementById('editCategoryId').value;
        const name = document.getElementById('editCategoryName').value.trim();
        const messageDiv = document.getElementById('editCategoryMessage');

        if (!name) {
            if (messageDiv) {
                messageDiv.innerHTML = '<div class="text-danger">Please enter a category name</div>';
            }
            return;
        }

        if (messageDiv) {
            messageDiv.innerHTML = '<div class="text-info">Saving...</div>';
        }

        fetch('backend/api/categories_update.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: parseInt(categoryId),
                name: name
            }),
            credentials: 'include'
        })
            .then(r => r.json())
            .then(data => {
                if (data.ok) {
                    if (messageDiv) {
                        messageDiv.innerHTML = '<div class="text-success">Category updated successfully!</div>';
                    }
                    // Close modal
                    const editModal = bootstrap.Modal.getInstance(document.getElementById('editCategoryModal'));
                    if (editModal) editModal.hide();

                    // Refresh categories list from database
                    loadCategories();

                    // Refresh skills view if open (categories might have changed)
                    if (currentView === 'skills') {
                        loadSkills();
                    }

                    // Clear message after 2 seconds
                    setTimeout(() => {
                        if (messageDiv) messageDiv.innerHTML = '';
                    }, 2000);
                } else {
                    if (messageDiv) {
                        messageDiv.innerHTML = '<div class="text-danger">' + (data.error || 'Failed to update category') + '</div>';
                    }
                }
            })
            .catch(e => {
                console.error(e);
                if (messageDiv) {
                    messageDiv.innerHTML = '<div class="text-danger">Network error</div>';
                }
            });
    }

    window.deleteCategory = function (categoryId) {
        if (!confirm('Delete this category? Associated skills will have their category set to NULL.')) return;

        fetch('backend/api/categories_delete.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: categoryId }),
            credentials: 'include'
        })
            .then(r => r.json())
            .then(data => {
                if (data.ok) {
                    showToast(data.message || 'Category deleted successfully', 'success');
                    // Refresh categories list from database
                    loadCategories();
                    // Refresh skills view if open (categories might have changed)
                    if (currentView === 'skills') {
                        loadSkills();
                    }
                } else {
                    showToast(data.error || 'Failed to delete category', 'error');
                }
            })
            .catch(e => {
                console.error(e);
                showToast('System error', 'error');
            });
    }


    // ... (rest of helpers like escapeHtml, formatDate) ...
    document.querySelectorAll('.sidebar .nav-link').forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const viewName = link.dataset.view;
            if (viewName) switchView(viewName);
        });
    });

    // --- Dashboard logic ---
    function loadAdminMetrics() {
        fetch('backend/admin/get_metrics.php', { credentials: 'include' })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.metrics) {
                    updateMetrics(data.metrics);
                    updateCharts(data.metrics);
                    updateDashboardRecentUsers(data.metrics.recentUsers);
                }
            })
            .catch(error => console.error('Error loading metrics:', error));
    }

    function updateMetrics(metrics) {
        // Update stat cards
        const totalUsersEl = document.querySelector('.stat-card.primary .value');
        if (totalUsersEl) totalUsersEl.textContent = metrics.totalUsers.toLocaleString();

        const activeSkillsEl = document.querySelector('.stat-card.success .value');
        if (activeSkillsEl) activeSkillsEl.textContent = metrics.activeSkills.toLocaleString();

        const pendingApprovalsEl = document.querySelector('.stat-card.warning .value');
        if (pendingApprovalsEl) pendingApprovalsEl.textContent = metrics.pendingApprovals.toLocaleString();

        const totalTransactionsEl = document.querySelector('.stat-card.danger .value');
        if (totalTransactionsEl) totalTransactionsEl.textContent = metrics.totalTransactions.toLocaleString();
    }

    function updateCharts(metrics) {
        // Update user growth chart
        if (window.ApexCharts && document.getElementById('userGrowthChart')) {
            // Clear existing if any (simple implementation: clear container content or check instance)
            document.getElementById('userGrowthChart').innerHTML = '';
            const growthOptions = {
                series: [{ name: 'New Users', data: metrics.userGrowth.map(item => item.count) }],
                chart: { height: 350, type: 'line', zoom: { enabled: false }, toolbar: { show: false } },
                dataLabels: { enabled: false },
                stroke: { curve: 'smooth', width: 3, colors: ['#C11C84'] },
                xaxis: { categories: metrics.userGrowth.map(item => item.month) },
                colors: ['#C11C84']
            };
            new ApexCharts(document.querySelector("#userGrowthChart"), growthOptions).render();
        }

        // Update user distribution chart
        if (window.ApexCharts && document.getElementById('userDistributionChart')) {
            document.getElementById('userDistributionChart').innerHTML = '';
            const distributionOptions = {
                series: [metrics.totalUsers, (metrics.totalUsers * 0.1)], // Mock inactive for now
                labels: ['Active Users', 'Inactive Users'],
                chart: { type: 'donut', height: 300 },
                colors: ['#C11C84', '#BFD6FF'],
                legend: { position: 'bottom' }
            };
            new ApexCharts(document.querySelector("#userDistributionChart"), distributionOptions).render();
        }
    }

    function updateDashboardRecentUsers(users) {
        const tbody = document.querySelector('#dashboardUsersTable tbody');
        if (!tbody || !users) return;

        tbody.innerHTML = users.map(user => `
            <tr>
                <td>
                    <div class="d-flex align-items-center">
                        <img src="${user.profile_pic || 'assets/img/default-avatar.svg'}" alt="User" class="user-avatar me-2" style="width:32px;height:32px;border-radius:50%;object-fit:cover;">
                        <span>${escapeHtml(user.name)}</span>
                    </div>
                </td>
                <td>${escapeHtml(user.email)}</td>
                <td>${user.is_admin ? '<span class="badge bg-purple">Admin</span>' : 'User'}</td>
                <td><span class="status-badge ${user.is_banned ? 'status-inactive' : 'status-active'}">${user.is_banned ? 'Banned' : 'Active'}</span></td>
                <td>${formatDate(user.created_at)}</td>
            </tr>
        `).join('');
    }

    // --- Users View Logic ---
    window.loadUsers = function (page = 1) {
        const searchCtx = document.getElementById('userSearchInput');
        const search = searchCtx ? searchCtx.value : '';

        const tbody = document.getElementById('usersTableBody');
        if (tbody) tbody.innerHTML = '<tr><td colspan="6" class="text-center">Loading...</td></tr>';

        fetch(`backend/admin/get_users.php?page=${page}&limit=10&search=${encodeURIComponent(search)}`, { credentials: 'include' })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    renderUsersTable(data.users);
                    renderPagination(data.pagination, 'usersPagination', 'loadUsers');
                } else {
                    if (tbody) tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Failed to load users</td></tr>';
                }
            })
            .catch(e => {
                console.error(e);
                if (tbody) tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Error loading users</td></tr>';
            });
    }

    function renderUsersTable(users) {
        const tbody = document.getElementById('usersTableBody');
        if (!tbody) return;

        if (users.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No users found.</td></tr>';
            return;
        }

        tbody.innerHTML = users.map(user => `
            <tr>
                <td>
                    <div class="d-flex align-items-center">
                        <img src="${user.profile_pic || 'assets/img/default-avatar.svg'}" class="user-avatar me-2" style="width:36px;height:36px;border-radius:50%;object-fit:cover;">
                        <div>
                            <div class="fw-bold">${escapeHtml(user.name)}</div>
                            <small class="text-muted">ID: ${user.id}</small>
                        </div>
                    </div>
                </td>
                <td>${user.credits}</td>
                <td>${user.is_admin ? '<span class="badge bg-primary">Admin</span>' : 'User'}</td>
                <td>
                    <span class="badge ${user.status === 'active' ? 'bg-success' : (user.status === 'suspended' ? 'bg-warning' : 'bg-secondary')}">
                        ${user.status}
                    </span>
                    ${user.is_banned ? '<span class="badge bg-danger ms-1">Banned</span>' : ''}
                </td>
                <td>${new Date(user.created_at).toLocaleDateString()}</td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-warning" onclick="manageUser(${user.id}, 'deactivate')" title="Deactivate User">
                            <i class="fas fa-user-slash"></i> Deactivate
                        </button>
                        <button class="btn btn-outline-danger" onclick="manageUser(${user.id}, 'delete')" title="Delete User">
                            <i class="fas fa-trash-alt"></i> Delete
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
    }

    window.manageUser = function (userId, action) {
        let confirmMessage = '';
        let reason = '';

        if (action === 'delete') {
            confirmMessage = 'WARNING: This will permanently delete the user account. This action cannot be undone. Are you sure?';
            if (!confirm(confirmMessage)) return;
            reason = prompt('Please enter a reason for deletion (optional):') || '';
        } else if (action === 'deactivate') {
            confirmMessage = 'Are you sure you want to deactivate this user account?';
            if (!confirm(confirmMessage)) return;
            reason = prompt('Please enter a reason for deactivation (optional):') || '';
        } else {
            if (!confirm(`Are you sure you want to perform: ${action}?`)) return;
        }

        fetch('backend/admin/manage_users.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                user_id: userId,
                action: action,
                reason: reason
            }),
            credentials: 'include'
        })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message || 'Action completed successfully', 'success');
                    // Refresh list
                    loadUsers();
                    // Refresh dashboard stats
                    if (typeof loadAdminMetrics === 'function') {
                        loadAdminMetrics();
                    }
                } else {
                    showToast(data.error || 'Action failed', 'error');
                }
            })
            .catch(e => {
                console.error(e);
                showToast('System error: ' + e.message, 'error');
            });
    }

    function renderPagination(pagination, containerId, loadersName) {
        const container = document.getElementById(containerId);
        if (!container) return;

        // Normalize pagination properties (backend might return 'pages' or 'total_pages', 'page' or 'current_page')
        const currentPage = pagination.page || pagination.current_page || 1;
        const totalPages = pagination.pages || pagination.total_pages || 1;

        let html = '<nav><ul class="pagination pagination-sm mb-0 justify-content-end">';

        // Prev
        html += `<li class="page-item ${currentPage <= 1 ? 'disabled' : ''}">
            <button class="page-link" onclick="${loadersName}(${currentPage - 1})">Prev</button>
        </li>`;

        // Page info
        html += `<li class="page-item disabled"><span class="page-link">Page ${currentPage} of ${totalPages}</span></li>`;

        // Next
        html += `<li class="page-item ${currentPage >= totalPages ? 'disabled' : ''}">
            <button class="page-link" onclick="${loadersName}(${currentPage + 1})">Next</button>
        </li>`;

        html += '</ul></nav>';
        container.innerHTML = html;
    }

    // --- Helpers ---
    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>"']/g, function (m) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]; });
    }

    function formatDate(dateString) {
        if (!dateString) return 'N/A';
        const d = new Date(dateString);
        return d.toLocaleDateString();
    }

    // Initial load
    const savedView = sessionStorage.getItem('adminView');
    if (savedView) {
        switchView(savedView);
        // Optional: sessionStorage.removeItem('adminView'); 
    } else {
        loadAdminMetrics();
    }
    // --- System Logs Logic ---
    window.loadSystemLogs = function (page = 1) {
        const actionFilter = document.getElementById('logActionFilter');
        const action = actionFilter ? actionFilter.value : '';

        const tbody = document.getElementById('logsTableBody');
        if (tbody) tbody.innerHTML = '<tr><td colspan="7" class="text-center">Loading logs...</td></tr>';

        fetch(`backend/admin/audit_logs.php?page=${page}&limit=20&action=${encodeURIComponent(action)}`, { credentials: 'include' })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    renderLogsTable(data.logs);
                    renderPagination(data.pagination, 'logsPagination', 'loadSystemLogs');
                } else {
                    if (tbody) tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger">Failed to load logs</td></tr>';
                }
            })
            .catch(e => {
                console.error(e);
                if (tbody) tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger">Error loading logs</td></tr>';
            });
    }

    function renderLogsTable(logs) {
        const tbody = document.getElementById('logsTableBody');
        if (!tbody) return;

        if (!logs || logs.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No logs found.</td></tr>';
            return;
        }

        tbody.innerHTML = logs.map(log => {
            let detailsStr = '';
            try {
                // If it's a string, parse it to see if it's valid JSON, then format it
                const details = typeof log.details === 'string' ? JSON.parse(log.details) : log.details;
                detailsStr = `<small class="text-muted" style="font-family:monospace; white-space:pre-wrap;">${escapeHtml(JSON.stringify(details, null, 1)).replace(/\n/g, '')}</small>`;
                // Truncate if too long
                if (detailsStr.length > 100) {
                    detailsStr = `<span title="${escapeHtml(JSON.stringify(details))}">${detailsStr.substring(0, 100)}...</span>`;
                }
            } catch (e) {
                detailsStr = escapeHtml(String(log.details || ''));
            }

            return `
            <tr>
                <td>${log.log_id}</td>
                <td>
                    <div class="fw-bold">${escapeHtml(log.user_name || 'System')}</div>
                    <small class="text-muted">ID: ${log.user_id || '-'}</small>
                </td>
                <td><span class="badge bg-light text-dark border">${escapeHtml(log.action)}</span></td>
                <td>
                    ${escapeHtml(log.entity_type)}
                    <span class="text-muted ps-1">#${log.entity_id}</span>
                </td>
                <td>${detailsStr}</td>
                <td><small>${escapeHtml(log.ip_address)}</small></td>
                <td>${formatDate(log.created_at)} <small class="text-muted">${new Date(log.created_at).toLocaleTimeString()}</small></td>
            </tr>
        `;
        }).join('');
    }

});

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

