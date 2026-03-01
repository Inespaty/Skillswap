document.addEventListener('DOMContentLoaded', function () {
    // Auth Check
    if (!window.Auth || !window.Auth.isAuthenticated || !window.Auth.isAuthenticated()) {
        window.location.href = 'login.html';
        return;
    }

    const currentUser = window.Auth.getCurrentUser();
    const receivedList = document.getElementById('receivedRequestsList');
    const sentList = document.getElementById('sentRequestsList');

    function loadRequests() {
        // Load both received and sent requests separately
        Promise.all([
            fetch('backend/requests/get_user_requests.php?type=received&status=all&limit=100', { credentials: 'include' }).then(r => r.json()),
            fetch('backend/requests/get_user_requests.php?type=sent&status=all&limit=100', { credentials: 'include' }).then(r => r.json())
        ])
            .then(([receivedData, sentData]) => {
                console.log('Received data:', receivedData);
                console.log('Sent data:', sentData);

                // Render each list separately since they're already filtered by the API
                if (receivedData.success && Array.isArray(receivedData.requests)) {
                    renderList(receivedList, receivedData.requests, 'received');
                } else {
                    console.error('Failed to load received requests:', receivedData);
                    receivedList.innerHTML = `<div class="alert alert-danger m-3">Failed to load received requests: ${receivedData.error || 'Unknown error'}</div>`;
                }

                if (sentData.success && Array.isArray(sentData.requests)) {
                    renderList(sentList, sentData.requests, 'sent');
                } else {
                    console.error('Failed to load sent requests:', sentData);
                    sentList.innerHTML = `<div class="alert alert-danger m-3">Failed to load sent requests: ${sentData.error || 'Unknown error'}</div>`;
                }
            })
            .catch(err => {
                console.error('Error loading requests:', err);
                console.error(err.stack); // Log stack trace
                showError('Network error occurred. Please check your connection and try again.');
            });
    }

    function renderList(container, items, type) {
        console.log(`Rendering ${type} list with ${items.length} items`);
        if (items.length === 0) {
            container.innerHTML = `
                <div class="text-center py-5">
                    <div class="mb-3"><i class="fas fa-inbox fa-3x text-muted opacity-50"></i></div>
                    <h5 class="text-muted">No ${type} requests found</h5>
                </div>`;
            return;
        }

        container.innerHTML = items.map(req => {
            const isReceived = type === 'received';
            const otherUser = escapeHtml(req.other_user_name || 'User');
            const otherUserId = req.other_user_id;

            // Handle avatar path
            let avatar = req.other_user_avatar || req.other_user_pic || 'assets/img/default-avatar.png';
            if (avatar && !avatar.startsWith('http') && !avatar.startsWith('/') && !avatar.startsWith('assets/')) {
                avatar = './' + avatar;
            }

            const skillTitle = escapeHtml(req.skill_title);
            const note = req.note ? escapeHtml(req.note) : '';

            let actions = '';
            let statusBadge = getStatusBadge(req.status);

            if (req.status === 'pending' && isReceived) {
                // For received pending requests, show Accept/Reject buttons
                actions = `
                    <button class="btn btn-sm btn-success me-2" onclick="handleRequest(${req.request_id}, 'accept')"><i class="fas fa-check me-1"></i> Accept</button>
                    <button class="btn btn-sm btn-outline-danger" onclick="handleRequest(${req.request_id}, 'reject')"><i class="fas fa-times me-1"></i> Reject</button>
                `;
            } else if (req.status === 'accepted') {
                const messageLink = `messages.html?user_id=${otherUserId}`;

                // Check if helper marked complete but requester hasn't confirmed
                // Parse as int to handle string "0" or "1" from API
                const completedByHelper = parseInt(req.completed_by_helper) === 1;
                if (completedByHelper && !req.requester_confirmed_at) {
                    statusBadge = '<span class="badge bg-warning text-dark">Pending Confirmation</span>';

                    if (isReceived) {
                        // Helper view: already marked complete, waiting for requester
                        actions = `
                            <a href="${messageLink}" class="btn btn-sm btn-upwork-primary me-2"><i class="fas fa-comment me-1"></i> Message ${otherUser}</a>
                            <span class="text-muted small"><i class="fas fa-clock me-1"></i> Waiting for requester confirmation</span>
                        `;
                    } else {
                        // Requester view: needs to confirm completion
                        actions = `
                            <a href="${messageLink}" class="btn btn-sm btn-upwork-primary me-2"><i class="fas fa-comment me-1"></i> Message ${otherUser}</a>
                            <button class="btn btn-sm btn-success" onclick="handleRequest(${req.request_id}, 'confirm')"><i class="fas fa-check-circle me-1"></i> Confirm Completion</button>
                        `;
                    }
                } else {
                    // Normal accepted state - not yet marked complete by helper
                    if (isReceived) {
                        // Helper can mark complete
                        actions = `
                            <a href="${messageLink}" class="btn btn-sm btn-upwork-primary me-2"><i class="fas fa-comment me-1"></i> Message ${otherUser}</a>
                            <button class="btn btn-sm btn-primary" onclick="handleRequest(${req.request_id}, 'complete')"><i class="fas fa-check-double me-1"></i> Mark Complete</button>
                        `;
                    } else {
                        // Requester waiting for helper to mark complete
                        actions = `
                            <a href="${messageLink}" class="btn btn-sm btn-upwork-primary me-2"><i class="fas fa-comment me-1"></i> Message ${otherUser}</a>
                            <span class="text-muted small"><i class="fas fa-hourglass-half me-1"></i> In progress</span>
                        `;
                    }
                }
            } else if (req.status === 'completed') {
                // Show rate button for completed requests
                actions = `
                    <button class="btn btn-sm btn-warning" onclick="handleRate(${req.request_id}, ${otherUserId}, '${escapeHtml(otherUser)}', '${escapeHtml(skillTitle)}')"><i class="fas fa-star me-1"></i> Rate Experience</button>
                `;
            }

            return `
                <div class="list-group-item p-4">
                    <div class="d-flex align-items-start">
                        <img src="${avatar}" class="rounded-circle me-3" width="50" height="50" style="object-fit: cover; background-color: #e5e7eb;" onerror="this.onerror=null; this.src='assets/img/default-avatar.png';">
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <h5 class="mb-0 fw-bold" style="color: #222222;">${skillTitle}</h5>
                                ${statusBadge}
                            </div>
                            <p class="mb-2 text-muted" style="color: #666666;">
                                ${isReceived ? 'Request from' : 'Request to'} <strong>${otherUser}</strong>
                                &bull; ${req.hours_required || 1} Hour${req.hours_required > 1 ? 's' : ''} 
                                &bull; ${new Date(req.created_at).toLocaleDateString()}
                            </p>
                            ${note ? `<p class="mb-2 bg-light p-2 rounded small" style="color: #555555;">"${note}"</p>` : ''}
                            
                            <div class="mt-3">
                                ${actions}
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }

    function escapeHtml(text) {
        if (!text) return text;
        return text
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    // Generic System Alerts
    let statusModal = null;
    let onStatusConfirm = null;

    function initStatusModal() {
        if (!statusModal) {
            const el = document.getElementById('statusModal');
            if (el) {
                statusModal = new bootstrap.Modal(el);

                // Handle Confirm Click
                document.getElementById('statusModalConfirm').addEventListener('click', function () {
                    statusModal.hide();
                    if (onStatusConfirm) {
                        onStatusConfirm();
                        onStatusConfirm = null;
                    }
                });
            }
        }
    }

    function showSystemAlert(message, title = 'Notification') {
        initStatusModal();
        if (!statusModal) return showToast(message, 'info'); // Fallback

        document.getElementById('statusModalTitle').textContent = title;
        document.getElementById('statusModalMessage').textContent = message;
        document.getElementById('statusModalCancel').style.display = 'none';
        document.getElementById('statusModalConfirm').textContent = 'OK';
        onStatusConfirm = null; // No callback for alerts

        statusModal.show();
    }

    function showSystemConfirm(message, onConfirm, title = 'Confirmation', confirmText = 'Yes, Proceed') {
        initStatusModal();
        if (!statusModal) {
            if (confirm(message)) onConfirm();
            return;
        }

        document.getElementById('statusModalTitle').textContent = title;
        document.getElementById('statusModalMessage').textContent = message;
        document.getElementById('statusModalCancel').style.display = 'inline-block';
        document.getElementById('statusModalConfirm').textContent = confirmText;
        onStatusConfirm = onConfirm;

        statusModal.show();
    }

    window.handleRequest = function (requestId, action) {
        initStatusModal(); // Ensure initialized

        const actionText = action === 'accept' ? 'accept' :
            action === 'reject' ? 'reject' :
                action === 'complete' ? 'mark as complete' :
                    action === 'confirm' ? 'confirm completion of' : 'process';

        // Use new System Confirm
        showSystemConfirm(
            `Are you sure you want to ${actionText} this request?`,
            function () {
                // Determine endpoint based on action
                let endpoint = 'backend/requests/respond_request.php';
                let body = { request_id: requestId, action: action };

                if (action === 'complete') {
                    endpoint = 'backend/requests/complete_request.php';
                    body = { request_id: requestId };
                } else if (action === 'confirm') {
                    endpoint = 'backend/requests/confirm_completion.php';
                    body = { request_id: requestId };
                }

                fetch(endpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(body),
                    credentials: 'include'
                })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success || data.ok) {
                            // Show success message
                            let successMsg = '';
                            if (action === 'accept') {
                                successMsg = 'Request accepted! You can now message the user.';
                            } else if (action === 'reject') {
                                successMsg = 'Request rejected.';
                            } else if (action === 'complete') {
                                successMsg = 'Request marked as complete! Waiting for requester confirmation.';
                            } else if (action === 'confirm') {
                                successMsg = data.message || 'Completion confirmed! Credits transferred.';
                            }

                            // Use System Alert instead of native alert
                            if (action === 'confirm') {
                                // Special flow for confirmation success - show alert then reload/rate
                                showSystemAlert(successMsg, 'Success');
                                // Wait a bit then reload
                                setTimeout(() => loadRequests(), 1500);
                            } else {
                                showSystemAlert(successMsg, 'Success');
                                loadRequests(); // Reload UI
                            }
                        } else {
                            showSystemAlert(data.error || 'Action failed. Please try again.', 'Error');
                        }
                    })
                    .catch(e => {
                        console.error('Error handling request:', e);
                        showSystemAlert('An error occurred. Please try again.', 'Error');
                    });
            },
            'Confirm Action',
            'Yes, Do It'
        );
    }

    // Update Rating success to use system alert
    // Note: Previously simple alert('Thank you...')
    // We need to inject into the rating modal logic too? 
    // Yes, but I can't overwrite just a part easily. I will leave rating modal logic 
    // mostly as is, just replacing alerts.

    // ... (rest of the file content) ...


    function getStatusBadge(status) {
        const badges = {
            'pending': 'bg-warning text-dark',
            'accepted': 'bg-success',
            'rejected': 'bg-danger',
            'completed': 'bg-info text-dark',
            'cancelled': 'bg-secondary'
        };
        return `<span class="badge ${badges[status] || 'bg-secondary'}">${status.charAt(0).toUpperCase() + status.slice(1)}</span>`;
    }

    function showError(msg) {
        receivedList.innerHTML = `<div class="alert alert-danger m-3">${msg}</div>`;
        sentList.innerHTML = `<div class="alert alert-danger m-3">${msg}</div>`;
    }

    // Handle rating with Modal
    let currentRatingRequestId = null;
    let ratingModal = null; // Will be initialized after DOM load

    window.handleRate = function (requestId, otherUserId, otherUserName, skillTitle) {
        // Initialize modal if not already done (in case simple DOMContentLoaded missed it)
        if (!ratingModal) {
            const modalEl = document.getElementById('ratingModal');
            if (modalEl) {
                ratingModal = new bootstrap.Modal(modalEl);
            } else {
                console.error('Rating modal element not found');
                showToast('Error loading rating interface.', 'error');
                return;
            }
        }

        currentRatingRequestId = requestId;
        document.getElementById('ratingUserName').textContent = otherUserName;
        document.getElementById('ratingSkillTitle').textContent = skillTitle;
        document.getElementById('selectedRating').value = 0;
        document.getElementById('ratingComment').value = '';
        document.getElementById('ratingError').style.display = 'none';
        document.getElementById('ratingText').textContent = '';

        updateStars(0);
        ratingModal.show();
    }

    // Star Rating Logic
    const starContainer = document.getElementById('starContainer');
    if (starContainer) {
        starContainer.addEventListener('click', function (e) {
            if (e.target.tagName === 'I') {
                const rating = parseInt(e.target.dataset.rating);
                document.getElementById('selectedRating').value = rating;
                document.getElementById('ratingError').style.display = 'none';
                updateStars(rating);
            }
        });

        // Hover effect
        starContainer.addEventListener('mouseover', function (e) {
            if (e.target.tagName === 'I') {
                const hoverRating = parseInt(e.target.dataset.rating);
                highlightStars(hoverRating);
            }
        });

        starContainer.addEventListener('mouseout', function () {
            const selected = parseInt(document.getElementById('selectedRating').value) || 0;
            updateStars(selected);
        });
    }

    function updateStars(rating) {
        highlightStars(rating);
        const labels = ['', 'Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];
        const ratingText = document.getElementById('ratingText');
        if (ratingText) ratingText.textContent = rating > 0 ? labels[rating] : '';
    }

    function highlightStars(rating) {
        const stars = starContainer.querySelectorAll('i');
        stars.forEach(star => {
            const starRating = parseInt(star.dataset.rating);
            if (starRating <= rating) {
                star.style.color = '#ffc107'; // Gold
            } else {
                star.style.color = '#E0E0E0'; // Gray
            }
        });
    }

    // Submit Rating
    const submitRatingBtn = document.getElementById('submitRatingBtn');
    if (submitRatingBtn) {
        submitRatingBtn.addEventListener('click', function () {
            const rating = parseInt(document.getElementById('selectedRating').value);
            const comment = document.getElementById('ratingComment').value;

            if (!rating || rating < 1) {
                document.getElementById('ratingError').style.display = 'block';
                return;
            }

            // Disable button
            submitRatingBtn.disabled = true;
            submitRatingBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...';

            fetch('backend/reviews/post_review.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    request_id: currentRatingRequestId,
                    rating: rating,
                    comment: comment
                }),
                credentials: 'include'
            })
                .then(r => r.json())
                .then(data => {
                    submitRatingBtn.disabled = false;
                    submitRatingBtn.textContent = 'Submit Review';

                    if (data.success) {
                        ratingModal.hide();
                        showSystemAlert('Thank you for your rating!', 'Success');
                        loadRequests(); // Reload UI
                    } else {
                        showSystemAlert(data.error || 'Failed to submit rating. Please try again.', 'Error');
                    }
                })
                .catch(e => {
                    console.error('Error submitting rating:', e);
                    submitRatingBtn.disabled = false;
                    submitRatingBtn.textContent = 'Submit Review';
                    showSystemAlert('An error occurred. Please try again.', 'Error');
                });
        });
    }

    // Initial Load
    loadRequests();
});
