document.addEventListener('DOMContentLoaded', function () {
    // Auth Check
    if (!window.Auth || !window.Auth.isAuthenticated || !window.Auth.isAuthenticated()) {
        window.location.href = 'login.html';
        return;
    }

    const currentUser = window.Auth.getCurrentUser();
    const transactionsList = document.getElementById('transactionsList');
    const typeFilter = document.getElementById('typeFilter');
    const periodFilter = document.getElementById('periodFilter');
    const searchInput = document.getElementById('searchInput');
    const paginationContainer = document.getElementById('paginationContainer');
    const pagination = document.getElementById('pagination');

    let currentPage = 1;
    let allTransactions = [];
    let filteredTransactions = [];

    function loadTransactions() {
        transactionsList.innerHTML = `
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2 text-muted">Loading transactions...</p>
            </div>
        `;

        fetch('backend/users/get_transactions.php?limit=1000', {
            credentials: 'include'
        })
            .then(r => r.json())
            .then(data => {
                if (data.success && Array.isArray(data.transactions)) {
                    allTransactions = data.transactions;
                    applyFilters();
                    updateStatistics();
                } else {
                    showError('Failed to load transactions');
                }
            })
            .catch(e => {
                console.error('Error loading transactions:', e);
                showError('Network error occurred');
            });
    }

    function updateStatistics() {
        const totalTransactions = allTransactions.length;
        let totalReceived = 0;
        let totalSpent = 0;

        allTransactions.forEach(t => {
            const credits = parseInt(t.credits) || 0;
            if (t.to_user_id === currentUser.id) {
                // I received credits
                totalReceived += credits;
            } else if (t.from_user_id === currentUser.id) {
                // I spent credits
                totalSpent += credits;
            }
        });

        document.getElementById('totalTransactions').textContent = totalTransactions;
        document.getElementById('totalReceived').textContent = totalReceived;
        document.getElementById('totalSpent').textContent = totalSpent;
    }

    function applyFilters() {
        const typeValue = typeFilter.value;
        const periodValue = periodFilter.value;
        const searchValue = searchInput.value.toLowerCase().trim();

        filteredTransactions = allTransactions.filter(t => {
            // Type filter
            if (typeValue && t.type !== typeValue) return false;

            // Period filter
            if (periodValue) {
                const days = parseInt(periodValue);
                const transactionDate = new Date(t.created_at);
                const cutoffDate = new Date();
                cutoffDate.setDate(cutoffDate.getDate() - days);
                if (transactionDate < cutoffDate) return false;
            }

            // Search filter
            if (searchValue) {
                const description = (t.description || '').toLowerCase();
                const otherUserName = (t.other_user_name || '').toLowerCase();
                if (!description.includes(searchValue) && !otherUserName.includes(searchValue)) {
                    return false;
                }
            }

            return true;
        });

        currentPage = 1;
        renderTransactions();
    }

    function renderTransactions() {
        if (filteredTransactions.length === 0) {
            transactionsList.innerHTML = `
                <div class="upwork-card">
                    <div class="upwork-card-body">
                        <div class="empty-state">
                            <div class="empty-state-icon"><i class="fas fa-receipt"></i></div>
                            <div class="empty-state-title">No transactions found</div>
                            <div class="empty-state-text">Try adjusting your filters</div>
                        </div>
                    </div>
                </div>
            `;
            paginationContainer.style.display = 'none';
            return;
        }

        const itemsPerPage = 10;
        const startIndex = (currentPage - 1) * itemsPerPage;
        const endIndex = startIndex + itemsPerPage;
        const pageTransactions = filteredTransactions.slice(startIndex, endIndex);

        transactionsList.innerHTML = pageTransactions.map(t => {
            const isCredit = parseInt(t.to_user_id) === parseInt(currentUser.id);
            const otherUserName = t.other_party || 'Unknown User';
            const amount = parseInt(t.credits) || 0;
            const date = new Date(t.created_at).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });

            let typeIcon = 'fa-exchange-alt';
            let typeName = 'Transfer';
            if (t.type === 'skill_exchange') {
                typeIcon = 'fa-handshake';
                typeName = 'Skill Exchange';
            } else if (t.type === 'system_credit') {
                typeIcon = 'fa-gift';
                typeName = 'System Credit';
            }

            return `
                <div class="transaction-card">
                    <div class="transaction-header">
                        <div class="transaction-type">
                            <i class="fas ${typeIcon} me-2"></i>${typeName}
                        </div>
                        <div class="transaction-amount ${isCredit ? 'credit' : 'debit'}">
                            ${isCredit ? '+' : '-'}${amount} <i class="fas fa-coins"></i>
                        </div>
                    </div>
                    <div class="transaction-details">
                        ${t.description || 'No description'}
                    </div>
                    <div class="transaction-meta">
                        <span>
                            <i class="fas fa-user me-1"></i>
                            ${isCredit ? 'From' : 'To'}: <strong>${otherUserName}</strong>
                        </span>
                        <span>
                            <i class="fas fa-clock me-1"></i>${date}
                        </span>
                    </div>
                </div>
            `;
        }).join('');

        // Render pagination
        renderPagination(itemsPerPage);
    }

    function renderPagination(itemsPerPage) {
        const totalPages = Math.ceil(filteredTransactions.length / itemsPerPage);

        if (totalPages <= 1) {
            paginationContainer.style.display = 'none';
            return;
        }

        paginationContainer.style.display = 'block';
        pagination.innerHTML = '';

        // Previous button
        const prevLi = document.createElement('li');
        prevLi.className = `page-item ${currentPage === 1 ? 'disabled' : ''}`;
        prevLi.innerHTML = `<a class="page-link" href="#" data-page="${currentPage - 1}">Previous</a>`;
        pagination.appendChild(prevLi);

        // Page numbers
        for (let i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= currentPage - 2 && i <= currentPage + 2)) {
                const li = document.createElement('li');
                li.className = `page-item ${i === currentPage ? 'active' : ''}`;
                li.innerHTML = `<a class="page-link" href="#" data-page="${i}">${i}</a>`;
                pagination.appendChild(li);
            } else if (i === currentPage - 3 || i === currentPage + 3) {
                const li = document.createElement('li');
                li.className = 'page-item disabled';
                li.innerHTML = '<span class="page-link">...</span>';
                pagination.appendChild(li);
            }
        }

        // Next button
        const nextLi = document.createElement('li');
        nextLi.className = `page-item ${currentPage === totalPages ? 'disabled' : ''}`;
        nextLi.innerHTML = `<a class="page-link" href="#" data-page="${currentPage + 1}">Next</a>`;
        pagination.appendChild(nextLi);

        // Add click handlers
        pagination.querySelectorAll('a.page-link').forEach(link => {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                const page = parseInt(this.dataset.page);
                if (page >= 1 && page <= totalPages) {
                    currentPage = page;
                    renderTransactions();
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            });
        });
    }

    function showError(msg) {
        transactionsList.innerHTML = `
            <div class="upwork-card">
                <div class="upwork-card-body">
                    <div class="empty-state">
                        <div class="empty-state-icon text-danger"><i class="fas fa-exclamation-triangle"></i></div>
                        <div class="empty-state-title">Error</div>
                        <div class="empty-state-text">${msg}</div>
                    </div>
                </div>
            </div>
        `;
    }

    // Event listeners
    typeFilter.addEventListener('change', applyFilters);
    periodFilter.addEventListener('change', applyFilters);

    let searchTimer;
    searchInput.addEventListener('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(applyFilters, 300);
    });

    // Initial load
    loadTransactions();
});
