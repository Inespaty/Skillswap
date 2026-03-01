/**
 * index-featured-skills.js
 * Loads featured skills from backend and renders them dynamically on the homepage
 */
document.addEventListener('DOMContentLoaded', function () {
  const featuredSkillsContainer = document.getElementById('featuredSkillsContainer');

  if (!featuredSkillsContainer) return;

  function createSkillCard(skill) {
    return `
      <div class="col-12 col-md-6 col-lg-4">
        <div class="card h-100 border-0 shadow-sm" style="transition: transform 0.2s;">
          <img src="${skill.image || 'assets/img/default-skill.png'}" class="card-img-top" alt="${skill.title}"
               style="height: 200px; object-fit: cover;">
          <div class="card-body">
            <span class="badge bg-primary mb-2">${skill.category_name || 'Uncategorized'}</span>
            <h5 class="card-title fw-bold">${skill.title}</h5>
            <p class="card-text text-muted small">${(skill.description || '').substring(0, 100)}...</p>
            <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
              <div class="d-flex align-items-center">
                <img src="${skill.user_profile_pic || 'assets/img/default-avatar.png'}" alt="User"
                     class="rounded-circle me-2" style="width: 32px; height: 32px; object-fit: cover;">
                <small class="fw-bold text-dark">${skill.user_name || 'User'}</small>
              </div>
              <a href="skill-detail.html?id=${skill.skill_id || skill.id}" class="btn btn-sm btn-outline-primary rounded-pill px-3">View</a>
            </div>
          </div>
        </div>
      </div>
    `;
  }

  function loadFeaturedSkills() {
    fetch('backend/api/skills.php?sort=popular&limit=3', { credentials: 'include' })
      .then(r => r.json())
      .then(data => {
        if (data && data.ok && Array.isArray(data.skills) && data.skills.length > 0) {
          featuredSkillsContainer.innerHTML = '';
          // Show first 3 skills as featured
          data.skills.slice(0, 3).forEach(skill => {
            featuredSkillsContainer.innerHTML += createSkillCard(skill);
          });
        } else {
          featuredSkillsContainer.innerHTML = '<div style="grid-column: 1 / -1; text-align: center; padding: 2rem;"><p class="text-muted">No skills available yet.</p></div>';
        }
      })
      .catch(err => {
        console.error('Load featured skills error', err);
        featuredSkillsContainer.innerHTML = '<div style="grid-column: 1 / -1; text-align: center; padding: 2rem;"><p class="text-muted">Failed to load skills.</p></div>';
      });
  }

  // Add small delay to ensure everything is loaded
  setTimeout(loadFeaturedSkills, 50);
});
