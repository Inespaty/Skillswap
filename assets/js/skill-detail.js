document.addEventListener('DOMContentLoaded', function () {
    const urlParams = new URLSearchParams(window.location.search);
    const skillId = urlParams.get('id');

    if (!skillId) {
        showError('Invalid skill ID');
        return;
    }

    loadSkillDetail(skillId);
});

// Make loadSkillDetail available globally
function loadSkillDetail(skillId) {
    fetch(`backend/api/skill_detail.php?id=${skillId}`, { credentials: 'include' })
        .then(r => {
            if (!r.ok) {
                throw new Error(`HTTP error! status: ${r.status}`);
            }
            return r.text().then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Failed to parse JSON:', text);
                    throw new Error('Invalid JSON response from server');
                }
            });
        })
        .then(data => {
            console.log('Skill detail response:', data);
            if (data && data.ok && data.skill) {
                renderSkill(data.skill);
            } else {
                console.error('Invalid skill data:', data);
                showError(data.error || 'Failed to load skill');
            }
        })
        .catch(err => {
            console.error('Error loading skill:', err);
            showError('Network error loading skill: ' + err.message);
        });
}

// Global variables for review eligibility (scoped to skill detail page)
let canReview = false;
let completedRequests = [];

function renderSkill(skill) {
    // Hide loading, show content
    document.getElementById('loadingState').style.display = 'none';
    document.getElementById('skillContent').style.display = 'block';

    // Set page title
    document.title = `${skill.title} - SkillSwap`;

    // Update breadcrumb
    document.getElementById('skillBreadcrumb').textContent = skill.title;

    // Skill Image
    const imageContainer = document.getElementById('skillImageContainer');
    if (skill.image) {
        let imageUrl = skill.image;
        if (!imageUrl.startsWith('http') && !imageUrl.startsWith('/') && !imageUrl.startsWith('assets/')) {
            imageUrl = './' + imageUrl;
        }
        imageContainer.innerHTML = `<img src="${imageUrl}" alt="${skill.title}" class="skill-image">`;
    } else {
        imageContainer.innerHTML = `
            <div class="skill-image-placeholder">
                <i class="fas fa-graduation-cap"></i>
            </div>
        `;
    }

    // Skill Title and Category
    document.getElementById('skillTitle').textContent = skill.title;
    document.getElementById('skillCategory').textContent = skill.category_name || 'Uncategorized';

    // Skill Date
    const createdDate = new Date(skill.created_at);
    const dateStr = createdDate.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
    document.getElementById('skillDate').textContent = dateStr;

    // Description
    document.getElementById('skillDescription').textContent = skill.description || 'No description provided.';

    // Owner Info
    const owner = skill.owner;
    let ownerAvatar = owner.avatar || 'assets/img/default-avatar.png';
    if (ownerAvatar && !ownerAvatar.startsWith('http') && !ownerAvatar.startsWith('/') && !ownerAvatar.startsWith('assets/')) {
        ownerAvatar = './' + ownerAvatar;
    }

    document.getElementById('ownerInfo').innerHTML = `
        <img src="${ownerAvatar}" alt="${owner.name}" class="rounded-circle mb-3" 
             style="width: 100px; height: 100px; object-fit: cover; border: 3px solid #E0E0E0;">
        <h5 class="mb-1">${owner.name}</h5>
        ${owner.location ? `<p class="text-muted small mb-2"><i class="fas fa-map-marker-alt me-1"></i>${owner.location}</p>` : ''}
        <div class="mb-3">
            <span class="badge bg-warning text-dark">
                <i class="fas fa-star me-1"></i>${owner.reputation.toFixed(1)} Reputation
            </span>
        </div>
        <a href="profile.html?id=${owner.id}" class="btn btn-upwork-secondary btn-sm w-100">View Profile</a>
    `;

    // Stats card was removed - stats no longer displayed

    // Reviews
    const reviewsContainer = document.getElementById('reviewsContainer');
    if (skill.reviews && skill.reviews.length > 0) {
        document.getElementById('reviewCount').textContent = skill.reviews.length;
        reviewsContainer.innerHTML = skill.reviews.map(review => {
            const stars = '★'.repeat(review.rating) + '☆'.repeat(5 - review.rating);
            let reviewerAvatar = review.reviewer_avatar || 'assets/img/default-avatar.png';
            if (reviewerAvatar && !reviewerAvatar.startsWith('http') && !reviewerAvatar.startsWith('/') && !reviewerAvatar.startsWith('assets/')) {
                reviewerAvatar = './' + reviewerAvatar;
            }
            const reviewDate = new Date(review.created_at).toLocaleDateString();

            return `
                <div class="review-card">
                    <div class="d-flex align-items-start mb-2">
                        <img src="${reviewerAvatar}" alt="${review.reviewer_name}" 
                             class="rounded-circle me-3" style="width: 50px; height: 50px; object-fit: cover;">
                        <div class="flex-grow-1">
                            <h6 class="mb-1">${review.reviewer_name}</h6>
                            <div class="star-rating mb-2">${stars}</div>
                            <p class="mb-0" style="color: #333;">${review.comment || 'No comment provided.'}</p>
                            <small class="text-muted">${reviewDate}</small>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    } else {
        document.getElementById('reviewCount').textContent = '0';
        reviewsContainer.innerHTML = '<p class="text-muted text-center py-3">No reviews yet. Be the first to review this skill!</p>';
    }

    // Action Buttons - Only show Edit/Delete to owner, Review/Request to others
    // IMPORTANT: Edit/Delete buttons should ONLY be visible to the skill owner
    // Review button should ONLY be visible if user has completed an exchange
    const currentUser = window.Auth && window.Auth.getCurrentUser ? window.Auth.getCurrentUser() : null;

    // Compare user IDs as numbers to ensure proper comparison
    // Default to false (not owner) if any ID is missing or invalid
    const currentUserId = currentUser ? parseInt(currentUser.id) : null;
    const ownerId = parseInt(owner.id);
    const isOwner = currentUserId && ownerId && currentUserId === ownerId;

    const actionButtons = document.getElementById('actionButtons');
    const sidebarActions = document.getElementById('sidebarActions');

    // Function to check if user has completed requests for this skill
    async function checkCompletedRequests() {
        if (isOwner || !currentUser) {
            return; // Buttons already rendered
        }

        try {
            // Fetch sent requests (where user is requester)
            const sentRes = await fetch(`backend/requests/get_user_requests.php?type=sent&status=completed`, {
                credentials: 'include'
            });
            const sentData = await sentRes.json();

            // Fetch received requests (where user is provider)
            const receivedRes = await fetch(`backend/requests/get_user_requests.php?type=received&status=completed`, {
                credentials: 'include'
            });
            const receivedData = await receivedRes.json();

            // Combine and filter by this skill
            const allRequests = [];
            if (sentData.success && sentData.requests) {
                allRequests.push(...sentData.requests);
            }
            if (receivedData.success && receivedData.requests) {
                allRequests.push(...receivedData.requests);
            }

            // Filter for this skill
            const skillRequests = allRequests.filter(req => req.skill_id == skill.id);

            // If user has any completed requests for this skill, they can review
            // (The backend will check if they already reviewed when submitting)
            renderActionButtons(skillRequests.length > 0, skillRequests);
        } catch (e) {
            console.error('Error checking completed requests:', e);
            renderActionButtons(false, []);
        }
    }

    function renderActionButtons(userCanReview, reviewRequests) {
        canReview = userCanReview;
        completedRequests = reviewRequests;
        // Owner sees Edit and Delete buttons - only if we're 100% sure they're the owner
        if (isOwner) {
            // Owner sees edit and delete buttons
            actionButtons.innerHTML = `
                <div class="d-flex gap-2">
                    <a href="skill-edit.html?id=${skill.id}" class="btn btn-upwork-primary btn-sm">
                        <i class="fas fa-edit me-1"></i> Edit Skill
                    </a>
                    <button onclick="SkillUtils.deleteSkill(${skill.id}, event)" class="btn btn-sm" style="background-color: #1e293b; color: #FFFFFF; border: none;">
                        <i class="fas fa-trash me-1"></i> Delete
                    </button>
                </div>
            `;
            sidebarActions.innerHTML = `
                <a href="skill-edit.html?id=${skill.id}" class="btn btn-upwork-primary mb-2">
                    <i class="fas fa-edit me-1"></i> Edit Skill
                </a>
                <button onclick="SkillUtils.deleteSkill(${skill.id}, event)" class="btn w-100" style="background-color: #1e293b; color: #FFFFFF; border: none;">
                    <i class="fas fa-trash me-1"></i> Delete Skill
                </button>
            `;

            // Delete buttons now use onclick with SkillUtils.deleteSkill - no need for event listeners
        } else if (currentUser) {
            // Other logged-in users see Review (if eligible) and Request buttons
            const reviewButtonHtml = canReview
                ? `<button class="btn btn-upwork-secondary btn-sm" onclick="openReviewModal(${skill.id})">
                       <i class="fas fa-star me-1"></i> Review
                   </button>`
                : '';

            actionButtons.innerHTML = `
                    <div class="d-flex gap-2">
                        ${reviewButtonHtml}
                        <button onclick="openRequestModal(${skill.id})" class="btn btn-upwork-primary btn-sm">
                            <i class="fas fa-handshake me-1"></i> Request Skill
                        </button>
                    </div>
                `;

            const sidebarReviewButton = canReview
                ? `<button class="btn btn-upwork-secondary mb-2" onclick="openReviewModal(${skill.id})">
                       <i class="fas fa-star me-1"></i> Write a Review
                   </button>`
                : '';

            sidebarActions.innerHTML = `
                ${sidebarReviewButton}
                <button onclick="openRequestModal(${skill.id})" class="btn btn-upwork-primary">
                    <i class="fas fa-handshake me-1"></i> Request This Skill
                </button>
            `;

            // Show review button in reviews section header if eligible
            const reviewButton = document.getElementById('reviewButton');
            if (reviewButton) {
                reviewButton.style.display = canReview ? 'block' : 'none';
            }
        } else {
            // Not logged in
            actionButtons.innerHTML = `
            <a href="login.html" class="btn btn-upwork-primary btn-sm">
                <i class="fas fa-sign-in-alt me-1"></i> Login to Request
            </a>
        `;
            sidebarActions.innerHTML = `
                <a href="login.html" class="btn btn-upwork-primary">
                    <i class="fas fa-sign-in-alt me-1"></i> Login to Request Skill
                </a>
            `;
        }
    }

    // Sidebar scrolls naturally with page - owner card and buttons flow normally

    // Check for completed requests to enable review button
    // IMPORTANT: Always render buttons immediately first, then update asynchronously if needed
    if (!isOwner && currentUser) {
        // Render buttons first with default state (no review button)
        renderActionButtons(false, []);
        // Then check for completed requests asynchronously and update if eligible
        // Use setTimeout to ensure rendering happens first
        setTimeout(() => {
            checkCompletedRequests().catch(err => {
                console.error('Error in checkCompletedRequests:', err);
                // On error, buttons are already rendered, so just leave them
            });
        }, 100);
    } else {
        renderActionButtons(false, []);
    }
}

function showError(message) {
    document.getElementById('loadingState').style.display = 'none';
    document.getElementById('skillContent').style.display = 'none';
    document.getElementById('errorState').style.display = 'block';

    const errorCard = document.querySelector('#errorState .upwork-card-body');
    if (errorCard && message) {
        errorCard.querySelector('p').textContent = message;
    }
}

// Review Modal Functions
function openReviewModal(skillId) {
    if (!skillId) {
        const urlParams = new URLSearchParams(window.location.search);
        skillId = urlParams.get('id');
    }

    // Check if user is logged in
    const currentUser = window.Auth && window.Auth.getCurrentUser ? window.Auth.getCurrentUser() : null;
    if (!currentUser) {
        window.location.href = 'login.html';
        return;
    }

    // Check if user has completed requests for this skill
    if (!canReview || !completedRequests || completedRequests.length === 0) {
        showToast('You can only review skills after completing an exchange. Please complete a request for this skill first.', 'warning');
        return;
    }

    const reviewModal = new bootstrap.Modal(document.getElementById('reviewModal'));
    document.getElementById('reviewSkillId').value = skillId;

    // If multiple completed requests, show dropdown to select one
    // For now, use the first one (most recent)
    if (completedRequests.length > 0) {
        document.getElementById('reviewRequestId').value = completedRequests[0].request_id;
    }

    // Reset form
    document.getElementById('reviewComment').value = '';
    document.getElementById('selectedRating').value = '';
    resetStarRating();

    reviewModal.show();
}

function resetStarRating() {
    const stars = document.querySelectorAll('#starRating .fa-star');
    stars.forEach(star => {
        star.classList.remove('fas');
        star.classList.add('far');
        star.style.color = '#ccc';
    });
}

// Star rating interaction and review submission - initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function () {
    // Wait a bit for modal to be in DOM
    setTimeout(function () {
        const starRating = document.getElementById('starRating');
        const selectedRatingInput = document.getElementById('selectedRating');

        if (starRating && selectedRatingInput) {
            starRating.addEventListener('click', function (e) {
                if (e.target.classList.contains('fa-star')) {
                    const rating = parseInt(e.target.getAttribute('data-rating'));
                    selectedRatingInput.value = rating;

                    // Update star display
                    const stars = starRating.querySelectorAll('.fa-star');
                    stars.forEach((star, index) => {
                        if (index < rating) {
                            star.classList.remove('far');
                            star.classList.add('fas');
                            star.style.color = '#ffc107';
                        } else {
                            star.classList.remove('fas');
                            star.classList.add('far');
                            star.style.color = '#ccc';
                        }
                    });
                }
            });

            // Hover effect
            starRating.addEventListener('mouseover', function (e) {
                if (e.target.classList.contains('fa-star')) {
                    const hoverRating = parseInt(e.target.getAttribute('data-rating'));
                    const stars = starRating.querySelectorAll('.fa-star');
                    stars.forEach((star, index) => {
                        if (index < hoverRating) {
                            star.style.color = '#ffc107';
                        } else {
                            star.style.color = '#ccc';
                        }
                    });
                }
            });

            starRating.addEventListener('mouseout', function () {
                const rating = parseInt(selectedRatingInput.value) || 0;
                const stars = starRating.querySelectorAll('.fa-star');
                stars.forEach((star, index) => {
                    if (index < rating) {
                        star.style.color = '#ffc107';
                    } else {
                        star.style.color = '#ccc';
                    }
                });
            });
        }

        // Submit review button
        const submitReviewBtn = document.getElementById('submitReviewBtn');
        if (submitReviewBtn) {
            submitReviewBtn.addEventListener('click', async function () {
                const requestId = document.getElementById('reviewRequestId').value;
                const rating = document.getElementById('selectedRating').value;
                const comment = document.getElementById('reviewComment').value;

                if (!rating) {
                    showToast('Please select a rating', 'warning');
                    return;
                }

                if (!requestId) {
                    showToast('Please complete a request before reviewing. You need to have an exchange completed for this skill.', 'warning');
                    return;
                }

                const originalText = this.innerHTML;
                this.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Submitting...';
                this.disabled = true;

                try {
                    const res = await fetch('backend/reviews/post_review.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            request_id: requestId,
                            rating: parseInt(rating),
                            comment: comment
                        }),
                        credentials: 'include'
                    });

                    const data = await res.json();

                    if (data.success) {
                        bootstrap.Modal.getInstance(document.getElementById('reviewModal')).hide();
                        showToast('Review submitted successfully!', 'success');
                        // Reload skill details to show new review
                        const urlParams = new URLSearchParams(window.location.search);
                        const skillId = urlParams.get('id');
                        if (skillId) {
                            loadSkillDetail(skillId);
                        }
                    } else {
                        showToast(data.error || 'Failed to submit review', 'error');
                    }
                } catch (e) {
                    console.error('Error submitting review:', e);
                    showToast('An error occurred while submitting the review', 'error');
                } finally {
                    this.innerHTML = originalText;
                    this.disabled = false;
                }
            });
        }
    }, 500);
});

// Open request modal
window.openRequestModal = function (skillId) {
    document.getElementById('requestSkillId').value = skillId;
    const requestModal = new bootstrap.Modal(document.getElementById('requestModal'));
    requestModal.show();
};

// Submit request form handler
document.addEventListener('DOMContentLoaded', function () {
    const submitRequestBtn = document.getElementById('submitRequestBtn');
    if (submitRequestBtn) {
        submitRequestBtn.addEventListener('click', async function () {
            const form = document.getElementById('requestForm');
            const skillId = document.getElementById('requestSkillId').value;
            const hours = parseInt(document.getElementById('requestHours').value) || 1;
            const note = document.getElementById('requestNote').value.trim();

            if (!skillId || hours < 1) {
                showToast('Please enter valid hours (at least 1).', 'warning');
                return;
            }

            const originalText = this.innerHTML;
            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Sending...';

            try {
                const response = await fetch('backend/requests/create_request.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify({
                        skill_id: parseInt(skillId),
                        hours: hours,
                        note: note
                    })
                });

                const result = await response.json();

                if (result.success) {
                    // Close modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('requestModal'));
                    if (modal) modal.hide();

                    // Reset form
                    form.reset();

                    // Show success message
                    showToast('Request sent successfully! The skill owner will be notified.', 'success');

                    // Update button to show request sent - find and update existing buttons
                    const requestBtn = actionButtons?.querySelector('button[onclick*="openRequestModal"]');
                    const sidebarRequestBtn = sidebarActions?.querySelector('button[onclick*="openRequestModal"]');

                    if (requestBtn) {
                        requestBtn.disabled = true;
                        requestBtn.className = 'btn btn-success btn-sm';
                        requestBtn.innerHTML = '<i class="fas fa-check me-1"></i> Request Sent';
                        requestBtn.removeAttribute('onclick');
                    }
                    if (sidebarRequestBtn) {
                        sidebarRequestBtn.disabled = true;
                        sidebarRequestBtn.className = 'btn btn-success';
                        sidebarRequestBtn.innerHTML = '<i class="fas fa-check me-1"></i> Request Sent';
                        sidebarRequestBtn.removeAttribute('onclick');
                    }
                } else {
                    showToast(result.error || 'Failed to send request. Please try again.', 'error');
                }
            } catch (error) {
                console.error('Error sending request:', error);
                showToast('An error occurred while sending the request. Please try again.', 'error');
            } finally {
                this.disabled = false;
                this.innerHTML = originalText;
            }
        });
    }
});

// Delete skill functionality is now in shared/skill-utils.js

