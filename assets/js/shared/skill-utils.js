/**
 * skill-utils.js
 * Shared utilities for skill-related functionality across all pages
 */

// Global skill utilities namespace
window.SkillUtils = {
  /**
   * Delete a skill - shared function used across all pages
   */
  deleteSkill: function (skillId, event) {
    console.log('deleteSkill called with skillId:', skillId, 'event:', event);

    // Get the button that was clicked
    const deleteBtn = event ? event.target.closest('button') : document.querySelector(`[data-skill-id="${skillId}"]`);

    if (!skillId || isNaN(skillId)) {
      showToast('Error: Invalid skill ID', 'error');
      console.error('Invalid skill ID:', skillId);
      return;
    }

    if (!confirm('Are you sure you want to delete this skill? This action cannot be undone.')) {
      return;
    }

    console.log('Delete confirmed, proceeding with deletion...');

    // Show loading state
    const originalText = deleteBtn ? deleteBtn.innerHTML : '';
    if (deleteBtn) {
      deleteBtn.disabled = true;
      deleteBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Deleting...';
    }

    fetch('backend/api/skill_delete.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ skill_id: skillId }),
      credentials: 'include'
    })
      .then(r => {
        // Check if response is JSON
        const contentType = r.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
          return r.text().then(text => {
            console.error('Non-JSON response:', text);
            throw new Error('Invalid response from server');
          });
        }
        return r.json();
      })
      .then(data => {
        if (data && data.ok) {
          showToast(data.message || 'Skill deleted successfully', 'success');
          // Redirect based on current page
          const currentPath = window.location.pathname;
          if (currentPath.includes('profile.html')) {
            // Reload profile page
            window.location.reload(true);
          } else if (currentPath.includes('skill-detail.html')) {
            // Redirect to profile page
            const currentUser = window.Auth && window.Auth.getCurrentUser ? window.Auth.getCurrentUser() : null;
            if (currentUser && currentUser.id) {
              window.location.href = `profile.html?id=${currentUser.id}`;
            } else {
              window.location.href = 'dashboard.html';
            }
          } else {
            // Default: redirect to dashboard
            window.location.href = 'dashboard.html';
          }
        } else {
          showToast(data.error || 'Failed to delete skill', 'error');
          if (deleteBtn) {
            deleteBtn.disabled = false;
            deleteBtn.innerHTML = originalText;
          }
        }
      })
      .catch(err => {
        console.error('Delete error:', err);
        showToast('Network error. Please try again. ' + (err.message || ''), 'error');
        if (deleteBtn) {
          deleteBtn.disabled = false;
          deleteBtn.innerHTML = originalText;
        }
      });
  },

  /**
   * Generate edit button HTML
   */
  getEditButton: function (skillId, size = 'sm', classes = '') {
    const sizeClass = size === 'sm' ? 'btn-sm' : '';
    return `<a href="skill-edit.html?id=${skillId}" class="btn btn-upwork-primary ${sizeClass} ${classes}">
      <i class="fas fa-edit me-1"></i> Edit Skill
    </a>`;
  },

  /**
   * Generate delete button HTML
   */
  getDeleteButton: function (skillId, size = 'sm', classes = '') {
    const sizeClass = size === 'sm' ? 'btn-sm' : '';
    return `<button onclick="SkillUtils.deleteSkill(${skillId}, event)" class="btn ${sizeClass} ${classes}" 
            style="background-color: #1e293b; color: #FFFFFF; border: none;" data-skill-id="${skillId}">
      <i class="fas fa-trash me-1"></i> Delete
    </button>`;
  },

  /**
   * Generate action buttons for skill owner (Edit + Delete)
   */
  getOwnerActions: function (skillId, size = 'sm', showLabels = true) {
    const labelText = showLabels ? 'Skill' : '';
    return `<div class="d-flex gap-2">
      ${this.getEditButton(skillId, size)}
      ${this.getDeleteButton(skillId, size)}
    </div>`;
  },

  /**
   * Generate skill status badge HTML
   */
  getStatusBadge: function (skill) {
    if (skill.approval_status === 'pending') {
      return '<span class="badge bg-warning text-dark">Pending Approval</span>';
    } else if (skill.approval_status === 'rejected') {
      return '<span class="badge bg-danger">Rejected</span>';
    } else if (skill.active_status === 1 && skill.approval_status === 'approved') {
      return '<span class="badge bg-success">Active</span>';
    } else {
      return '<span class="badge bg-secondary">Inactive</span>';
    }
  },

  /**
   * Format date for display
   */
  formatDate: function (dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
  },

  /**
   * Escape HTML to prevent XSS
   */
  escapeHtml: function (text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }
};

// Make deleteSkill available globally for backward compatibility
window.deleteSkill = window.SkillUtils.deleteSkill;

