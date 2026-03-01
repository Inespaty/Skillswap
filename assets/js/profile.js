/**
 * profile.js
 * Handles profile data loading, rendering, and editing.
 */

document.addEventListener('DOMContentLoaded', async function () {
  // Auth Check - simple check since profile.html is excluded from protectRoute()
  // This page is accessed from dashboard.html, so user should already be authenticated
  // Wait briefly for auth.js to finish syncing with server
  await new Promise(resolve => setTimeout(resolve, 200));

  if (!window.Auth || !window.Auth.isAuthenticated || !window.Auth.isAuthenticated()) {
    window.location.href = 'login.html';
    return;
  }

  const currentUser = window.Auth.getCurrentUser();
  // Check if viewing own profile or another user's profile
  const urlParams = new URLSearchParams(window.location.search);
  const profileId = urlParams.get('id') ? parseInt(urlParams.get('id')) : currentUser.id;
  const isOwnProfile = profileId === currentUser.id;

  // Elements
  const elements = {
    header: document.getElementById('profileHeader'),
    about: document.getElementById('aboutContent'),
    email: document.getElementById('userEmail'),
    memberSince: document.getElementById('memberSince'),
    teachingSkillsList: document.getElementById('teachingSkillsList'),
    sidebarTeaching: document.getElementById('teachingSkills'),
    sidebarLearning: document.getElementById('learningSkills'),
    editModal: new bootstrap.Modal(document.getElementById('editProfileModal')),
    editForm: document.getElementById('editProfileForm'),
    saveBtn: document.getElementById('saveProfileBtn'),
    avatarInput: document.getElementById('editAvatarInput'),
    avatarPreview: document.getElementById('editAvatarPreview')
  };

  // Load Profile Data
  async function loadProfile() {
    try {
      const res = await fetch(`backend/api/user_profile.php?id=${profileId}`);
      const data = await res.json();

      if (data.ok) {
        renderHeader(data.user);
        renderAbout(data.user);
        renderSkills(data.skills || [], data.received_skills || []);
        renderReviews(data.reviews);

        // Privacy: Only show activity if own profile
        if (isOwnProfile) {
          renderActivity(data.transactions);
          const activityTab = document.getElementById('activity-tab');
          if (activityTab) activityTab.style.display = 'block';
        } else {
          const activityTab = document.getElementById('activity-tab');
          if (activityTab) activityTab.style.display = 'none';
        }

        // Populate Edit Form
        populateEditForm(data.user);
      } else {
        console.error('Failed to load profile:', data.error);
      }
    } catch (e) {
      console.error('Error fetching profile:', e);
    }
  }

  function renderHeader(user) {
    // Fix profile picture path - handle different path formats
    let profilePicUrl = user.profile_pic || '';

    if (profilePicUrl) {
      // If it starts with uploads/, make it relative to root
      if (profilePicUrl.startsWith('uploads/')) {
        profilePicUrl = './' + profilePicUrl;
      }
      // If it doesn't start with http, /, or assets/, assume it's relative to root
      else if (!profilePicUrl.startsWith('http') && !profilePicUrl.startsWith('/') && !profilePicUrl.startsWith('assets/')) {
        profilePicUrl = './' + profilePicUrl;
      }
    }

    // Create a simple fallback with user initials
    const getInitials = (name) => {
      if (!name) return '?';
      const parts = name.trim().split(/\s+/);
      if (parts.length >= 2) {
        return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
      }
      return name[0].toUpperCase();
    };

    const initials = getInitials(user.name);
    const fallbackSvg = `data:image/svg+xml,${encodeURIComponent(`<svg width="140" height="140" xmlns="http://www.w3.org/2000/svg"><rect width="140" height="140" fill="#6366f1"/><text x="50%" y="50%" font-family="Arial, sans-serif" font-size="48" font-weight="bold" fill="white" text-anchor="middle" dominant-baseline="central">${initials}</text></svg>`)}`;

    elements.header.innerHTML = `
            <div class="row align-items-center">
                <div class="col-md-auto text-center mb-3 mb-md-0">
                    ${profilePicUrl ?
        `<img src="${profilePicUrl}" 
                            class="profile-avatar rounded-circle" 
                            alt="${user.name}"
                            onerror="this.onerror=null; this.src='${fallbackSvg}'"
                            style="width: 140px; height: 140px; object-fit: cover; border: 4px solid #fff; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15); background-color: #e5e7eb; display: block;">` :
        `<div class="profile-avatar rounded-circle d-flex align-items-center justify-content-center" 
                            style="width: 140px; height: 140px; border: 4px solid #fff; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15); background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%); color: white; font-size: 3rem; font-weight: bold;">
                            ${initials}
                        </div>`
      }
                </div>
                <div class="col-md text-center text-md-start">
                    <div class="d-flex flex-column flex-md-row align-items-center mb-2">
                        <h2 class="mb-0 fw-bold me-md-3" style="color: #1e293b !important; font-size: 2rem !important; font-weight: 700 !important; text-shadow: 0 1px 3px rgba(0,0,0,0.1); letter-spacing: -0.02em;">${user.name}</h2>
                        ${user.title ? `<span class="badge bg-light text-secondary border">${user.title}</span>` : ''}
                    </div>
                    <p class="text-muted mb-3"><i class="fas fa-map-marker-alt me-1"></i> ${user.location || 'Location not set'}</p>
                    
                    <div class="d-flex justify-content-center justify-content-md-start gap-4 mb-4">
                        <div class="text-center">
                            <div class="h4 fw-bold mb-0 text-primary">${user.credits || 0}</div>
                            <small class="text-uppercase text-muted" style="font-size: 0.7rem;">Credits</small>
                        </div>
                        <div class="text-center">
                            <div class="h4 fw-bold mb-0 text-warning">${parseFloat(user.reputation_score).toFixed(1)}</div>
                            <small class="text-uppercase text-muted" style="font-size: 0.7rem;">Reputation</small>
                        </div>
                        <div class="text-center">
                            <div class="h4 fw-bold mb-0 text-secondary">${user.skill_count || 0}</div>
                            <small class="text-uppercase text-muted" style="font-size: 0.7rem;">Skills</small>
                        </div>
                    </div>

                    ${isOwnProfile ? `
                    <div class="d-flex gap-2 justify-content-center justify-content-md-start">
                        <button class="btn btn-outline-primary rounded-pill px-4" onclick="document.getElementById('openEditModalBtn').click()">
                            <i class="fas fa-edit me-1"></i> Edit Profile
                        </button>
                    </div>
                    <!-- Hidden trigger for modal to keep JS clean -->
                    <button id="openEditModalBtn" class="d-none" data-bs-toggle="modal" data-bs-target="#editProfileModal"></button>
                    ` : ''}
                </div>
            </div>
        `;

    // Logout button removed - use navbar dropdown instead
  }

  function renderAbout(user) {
    elements.about.innerHTML = user.bio
      ? `<p style="color: #1e293b; font-size: 1rem; line-height: 1.6; margin-bottom: 0;">${user.bio}</p>`
      : '<p class="text-muted fst-italic" style="color: #64748b;">No bio provided.</p>';
    elements.email.innerText = user.email;
    elements.memberSince.innerText = new Date(user.created_at).toLocaleDateString();

    // Add extra fields to contact info if they exist
    const contactList = document.getElementById('contactInfo');
    // Reset list to base
    contactList.innerHTML = `
            <li><i class="fas fa-envelope text-primary" style="width:20px"></i> <span class="ms-2">${user.email}</span></li>
            <li><i class="fas fa-calendar-alt text-primary" style="width:20px"></i> <span class="ms-2">Joined ${new Date(user.created_at).toLocaleDateString()}</span></li>
        `;

    if (user.phone) {
      contactList.innerHTML += `<li><i class="fas fa-phone text-primary" style="width:20px"></i> <span class="ms-2">${user.phone}</span></li>`;
    }
    if (user.website) {
      contactList.innerHTML += `<li><i class="fas fa-globe text-primary" style="width:20px"></i> <a href="${user.website}" target="_blank" class="ms-2 text-decoration-none text-dark">${user.website}</a></li>`;
    }
  }

  function renderSkills(skills, received) {
    // Update page elements based on ownership
    const skillsTabTitle = document.getElementById('skillsTabTitle');
    const addNewSkillBtn = document.getElementById('addNewSkillBtn');
    if (skillsTabTitle) {
      skillsTabTitle.textContent = isOwnProfile ? 'Skills I Offer' : 'Skills Offered';
    }
    if (addNewSkillBtn) {
      addNewSkillBtn.style.display = isOwnProfile ? 'block' : 'none';
    }

    // Main Skills List
    if (skills.length > 0) {
      elements.teachingSkillsList.innerHTML = `<div class="row g-4">${skills.map(skill => {
        // Determine status badge - using only brand colors
        let statusBadge = '';
        if (skill.approval_status === 'pending') {
          statusBadge = '<span class="badge ms-2" style="background-color: rgba(193, 28, 132, 0.1); color: #C11C84; border: 1px solid rgba(193, 28, 132, 0.3);"><i class="fas fa-clock me-1"></i>Pending</span>';
        } else if (skill.approval_status === 'rejected') {
          statusBadge = '<span class="badge ms-2" style="background-color: rgba(30, 41, 59, 0.1); color: #1e293b; border: 1px solid rgba(30, 41, 59, 0.3);"><i class="fas fa-times me-1"></i>Rejected</span>';
        } else if (skill.active_status === 1 && skill.approval_status === 'approved') {
          statusBadge = '<span class="badge ms-2" style="background-color: #C11C84; color: #FFFFFF;"><i class="fas fa-check me-1"></i>Active</span>';
        } else {
          statusBadge = '<span class="badge ms-2" style="background-color: rgba(30, 41, 59, 0.1); color: #1e293b; border: 1px solid rgba(30, 41, 59, 0.3);">Inactive</span>';
        }

        // Format date - handle cases where created_at might not be available
        let dateStr = 'Recently';
        if (skill.created_at) {
          try {
            const createdDate = new Date(skill.created_at);
            dateStr = createdDate.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
          } catch (e) {
            dateStr = 'Recently';
          }
        }

        // Image handling - using white background with brand color icon
        let imageHtml = '';
        if (skill.image) {
          let imageUrl = skill.image;
          if (!imageUrl.startsWith('http') && !imageUrl.startsWith('/') && !imageUrl.startsWith('assets/')) {
            imageUrl = './' + imageUrl;
          }
          imageHtml = `<img src="${imageUrl}" alt="${skill.title}" class="card-img-top" style="height: 200px; object-fit: cover; border-radius: 0.75rem 0.75rem 0 0;">`;
        } else {
          imageHtml = `<div class="card-img-top d-flex align-items-center justify-content-center" style="height: 200px; background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 0.75rem 0.75rem 0 0;">
            <i class="fas fa-graduation-cap" style="font-size: 3rem; color: #C11C84;"></i>
          </div>`;
        }

        // Credits display
        const credits = skill.credits_required || 0;
        const creditsDisplay = credits > 0
          ? `<div class="mb-2">
               <span class="badge" style="background-color: #C11C84; color: #FFFFFF; font-weight: 600; padding: 0.4rem 0.8rem;">
                 <i class="fas fa-coins me-1"></i>${credits} Credits
               </span>
             </div>`
          : `<div class="mb-2">
               <span class="badge" style="background-color: rgba(193, 28, 132, 0.1); color: #C11C84; border: 1px solid rgba(193, 28, 132, 0.3); font-weight: 500;">
                 <i class="fas fa-gift me-1"></i>Free
               </span>
             </div>`;

        return `
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="card h-100 border-0" style="background: #FFFFFF; border: 1px solid #E2E8F0 !important; border-radius: 0.75rem; cursor: pointer; transition: all 0.2s; box-shadow: 0 1px 3px rgba(0,0,0,0.1);" 
                         onclick="window.location.href='skill-detail.html?id=${skill.id || skill.skill_id}'"
                         onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 4px 12px rgba(193, 28, 132, 0.15)'; this.style.borderColor='#C11C84'"
                         onmouseout="this.style.transform=''; this.style.boxShadow='0 1px 3px rgba(0,0,0,0.1)'; this.style.borderColor='#E2E8F0'">
                        ${imageHtml}
                        <div class="card-body p-3">
                            <div class="mb-2">
                                <h5 class="card-title mb-2 fw-bold" style="color: #1e293b; font-size: 1.1rem;">${skill.title}</h5>
                                ${creditsDisplay}
                                <div class="d-flex flex-wrap gap-2 mb-2">
                                    <span class="badge" style="background-color: rgba(193, 28, 132, 0.1); color: #C11C84; border: 1px solid rgba(193, 28, 132, 0.3); font-weight: 500;">${skill.category_name || 'Uncategorized'}</span>
                                    ${statusBadge}
                                </div>
                            </div>
                            <p class="card-text mb-3" style="color: #6B7280; font-size: 0.9rem; line-height: 1.6; min-height: 48px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                ${skill.description || 'No description provided.'}
                            </p>
                            <div class="d-flex justify-content-between align-items-center pt-2" style="border-top: 1px solid #E2E8F0;">
                                <small style="color: #6B7280;">
                                    <i class="fas fa-calendar-alt me-1" style="color: #C11C84;"></i>${dateStr}
                                </small>
                                ${isOwnProfile ? `
                                <div class="d-flex gap-2">
                                    <button class="btn btn-sm" style="background-color: #C11C84; color: #FFFFFF; border: none; padding: 0.25rem 0.75rem; border-radius: 0.375rem;" 
                                            onclick="event.stopPropagation(); window.location.href='skill-edit.html?id=${skill.id || skill.skill_id}'">
                                        <i class="fas fa-edit me-1"></i>Edit
                                    </button>
                                    <button class="btn btn-sm" style="background-color: #1e293b; color: #FFFFFF; border: none; padding: 0.25rem 0.75rem; border-radius: 0.375rem;" 
                                            onclick="event.stopPropagation(); SkillUtils.deleteSkill(${skill.id || skill.skill_id}, event)">
                                        <i class="fas fa-trash me-1"></i>Delete
                                    </button>
                                </div>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                </div>
            `;
      }).join('')}</div>`;

      // Sidebar List
      elements.sidebarTeaching.innerHTML = skills.map(s =>
        `<span class="skill-tag">${s.title}</span>`
      ).join('');

      // Show/hide Manage button in sidebar based on ownership
      const manageSkillsBtn = document.getElementById('manageSkillsBtn');
      if (manageSkillsBtn) {
        manageSkillsBtn.style.display = isOwnProfile ? 'block' : 'none';
      }

    } else {
      const emptyMessage = isOwnProfile ? 'You haven\'t added any skills yet.' : 'This user hasn\'t added any skills yet.';
      elements.teachingSkillsList.innerHTML = `<div class="alert alert-light text-center">${emptyMessage}</div>`;
      elements.sidebarTeaching.innerHTML = '<small class="text-muted">No skills</small>';

      // Hide Manage button if no skills
      const manageSkillsBtn = document.getElementById('manageSkillsBtn');
      if (manageSkillsBtn) {
        manageSkillsBtn.style.display = 'none';
      }
    }

    // Learning List (Sidebar only)
    if (received && received.length > 0) {
      elements.sidebarLearning.innerHTML = received.map(s =>
        `<span class="skill-tag" style="background:#fdf2f8; color:#db2777;">${s.title}</span>`
      ).join('');
    }
  }

  function renderReviews(reviews) {
    const container = document.querySelector('.reviews-container');
    if (!container) return;

    if (!reviews || reviews.length === 0) {
      container.innerHTML = '<div class="text-center text-muted py-4"><i class="far fa-comment-dots fa-2x mb-2"></i><p>No reviews yet.</p></div>';
      return;
    }

    container.innerHTML = reviews.map(review => `
        <div class="card mb-3 border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div class="d-flex align-items-center">
                        <img src="${review.reviewer_avatar || 'assets/img/default-avatar.png'}" class="rounded-circle me-3" width="40" height="40">
                        <div>
                            <h6 class="mb-0 fw-bold">${review.reviewer_name}</h6>
                            <small class="text-muted">for ${review.skill_title}</small>
                        </div>
                    </div>
                    <div class="text-warning">
                        ${'★'.repeat(review.rating)}${'☆'.repeat(5 - review.rating)}
                    </div>
                </div>
                <p class="mt-3 mb-0" style="color: var(--color-text);">${review.comment}</p>
                <small class="text-muted mt-2 d-block">${new Date(review.created_at).toLocaleDateString()}</small>
            </div>
        </div>
    `).join('');
  }

  function renderActivity(transactions) {
    const container = document.querySelector('.timeline');
    if (!container) return;

    if (!transactions || transactions.length === 0) {
      container.innerHTML = '<p class="text-center text-muted py-4">No recent activity.</p>';
      return;
    }

    container.innerHTML = transactions.map(t => {
      const iscredit = t.direction === 'received';
      const icon = iscredit ? 'fa-arrow-down text-success' : 'fa-arrow-up text-danger';
      const color = iscredit ? 'text-success' : 'text-danger';
      const sign = iscredit ? '+' : '-';

      return `
        <div class="timeline-item">
            <div class="timeline-badge bg-${iscredit ? 'success' : 'danger'}">
                <i class="fas ${iscredit ? 'fa-plus' : 'fa-minus'}"></i>
            </div>
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-1">${t.type === 'exchange' ? 'Session Exchange' : 'Credit Transfer'}</h6>
                    <p class="mb-0 text-muted small">
                        ${iscredit ? 'Received from' : 'Sent to'} <strong>${t.other_user_name}</strong>
                    </p>
                    <small class="text-muted">${new Date(t.created_at).toLocaleDateString()}</small>
                </div>
                <div class="fw-bold ${color}">
                    ${sign}${t.credits} Credits
                </div>
            </div>
        </div>`;
    }).join('');
  }

  function populateEditForm(user) {
    const form = elements.editForm;
    form.elements['name'].value = user.name;
    form.elements['title'].value = user.title || '';
    form.elements['bio'].value = user.bio || '';
    form.elements['location'].value = user.location || '';
    form.elements['phone'].value = user.phone || '';
    form.elements['website'].value = user.website || '';

    // Fix profile picture path for edit modal
    let profilePicUrl = user.profile_pic || '';
    if (profilePicUrl) {
      if (profilePicUrl.startsWith('uploads/')) {
        profilePicUrl = './' + profilePicUrl;
      } else if (!profilePicUrl.startsWith('http') && !profilePicUrl.startsWith('/') && !profilePicUrl.startsWith('assets/')) {
        profilePicUrl = './' + profilePicUrl;
      }
    }

    if (profilePicUrl) {
      elements.avatarPreview.src = profilePicUrl;
      elements.avatarPreview.onerror = function () {
        // Fallback to initials if image fails to load
        const getInitials = (name) => {
          if (!name) return '?';
          const parts = name.trim().split(/\s+/);
          if (parts.length >= 2) {
            return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
          }
          return name[0].toUpperCase();
        };
        const initials = getInitials(user.name);
        this.src = `data:image/svg+xml,${encodeURIComponent(`<svg width="100" height="100" xmlns="http://www.w3.org/2000/svg"><rect width="100" height="100" fill="#6366f1"/><text x="50%" y="50%" font-family="Arial, sans-serif" font-size="36" font-weight="bold" fill="white" text-anchor="middle" dominant-baseline="central">${initials}</text></svg>`)}`;
      };
    } else {
      // Use initials fallback
      const getInitials = (name) => {
        if (!name) return '?';
        const parts = name.trim().split(/\s+/);
        if (parts.length >= 2) {
          return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
        }
        return name[0].toUpperCase();
      };
      const initials = getInitials(user.name);
      elements.avatarPreview.src = `data:image/svg+xml,${encodeURIComponent(`<svg width="100" height="100" xmlns="http://www.w3.org/2000/svg"><rect width="100" height="100" fill="#6366f1"/><text x="50%" y="50%" font-family="Arial, sans-serif" font-size="36" font-weight="bold" fill="white" text-anchor="middle" dominant-baseline="central">${initials}</text></svg>`)}`;
    }
  }

  // Avatar Preview
  elements.avatarInput.addEventListener('change', function (e) {
    if (this.files && this.files[0]) {
      const reader = new FileReader();
      reader.onload = (e) => elements.avatarPreview.src = e.target.result;
      reader.readAsDataURL(this.files[0]);
    }
  });

  // Save Profile
  elements.saveBtn.addEventListener('click', async function () {
    const formData = new FormData(elements.editForm);

    // Show loading state
    const originalText = this.innerHTML;
    this.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving...';
    this.disabled = true;

    try {
      const res = await fetch('backend/api/profile_update.php', {
        method: 'POST',
        credentials: 'include',
        body: formData
      });

      // Check if response is OK
      if (!res.ok) {
        throw new Error(`HTTP error! status: ${res.status}`);
      }

      // Try to parse JSON response
      let result;
      const text = await res.text();
      try {
        result = JSON.parse(text);
      } catch (parseError) {
        console.error('JSON parse error:', parseError, 'Response text:', text);
        throw new Error('Invalid response from server. Please try again.');
      }

      if (result.ok) {
        // Update local auth user in localStorage if needed so navbar reflects changes immediately
        if (window.Auth && window.Auth.setCurrentUser && result.user) {
          window.Auth.setCurrentUser(result.user);
        }

        // Force navbar to update immediately to reflect profile picture changes
        if (window.Navbar && typeof window.Navbar.update === 'function') {
          window.Navbar.update();
        }

        elements.editModal.hide();
        loadProfile(); // Reload UI
        showToast('Profile updated successfully!', 'success');
      } else {
        showToast(result.error || 'Failed to update profile', 'error');
      }
    } catch (e) {
      console.error('Save error:', e);
      showToast('An error occurred while saving: ' + (e.message || 'Unknown error'), 'error');
    } finally {
      this.innerHTML = originalText;
      this.disabled = false;
    }
  });

  // Initial Load
  loadProfile();
});
