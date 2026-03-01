document.addEventListener('DOMContentLoaded', function () {
    const skillsGrid = document.getElementById('skillsGrid');
    const categorySelect = document.getElementById('categorySelect');
    const searchInput = document.getElementById('searchInput');
    const sortSelect = document.getElementById('sortSelect');

    function createSkillCard(skill) {
        const col = document.createElement('div');
        col.className = 'col-12 col-md-6 col-lg-4';

        const card = document.createElement('div');
        card.className = 'skill-card-upwork';

        // Image
        if (skill.image) {
            const img = document.createElement('img');
            img.src = skill.image;
            img.alt = skill.title;
            img.className = 'skill-card-image';
            card.appendChild(img);
        } else {
            const imgPlaceholder = document.createElement('div');
            imgPlaceholder.className = 'skill-card-image';
            imgPlaceholder.style.background = 'linear-gradient(135deg, #F7C5E5 0%, #BFD6FF 100%)';
            imgPlaceholder.style.display = 'flex';
            imgPlaceholder.style.alignItems = 'center';
            imgPlaceholder.style.justifyContent = 'center';
            imgPlaceholder.innerHTML = '<i class="fas fa-graduation-cap" style="font-size: 3rem; color: #C11C84;"></i>';
            card.appendChild(imgPlaceholder);
        }

        const content = document.createElement('div');
        content.className = 'skill-card-content';

        const badge = document.createElement('span');
        badge.className = 'badge-upwork badge-upwork-primary mb-2';
        badge.textContent = skill.category_name || skill.category || 'Uncategorized';

        const title = document.createElement('h3');
        title.className = 'skill-card-title';
        title.textContent = skill.title;

        const desc = document.createElement('p');
        desc.className = 'skill-card-description';
        desc.textContent = (skill.description || '').substring(0, 100) + (skill.description && skill.description.length > 100 ? '...' : '');

        content.appendChild(badge);
        content.appendChild(title);
        content.appendChild(desc);

        const footer = document.createElement('div');
        footer.className = 'skill-card-footer';

        const owner = document.createElement('div');
        owner.className = 'd-flex align-items-center';

        const avatar = document.createElement('img');
        avatar.className = 'rounded-circle me-2';
        avatar.style.width = '32px';
        avatar.style.height = '32px';
        avatar.style.objectFit = 'cover';
        avatar.alt = 'Avatar';
        avatar.src = skill.owner_avatar || skill.user_profile_pic || 'assets/img/default-avatar.png';

        const ownerInfo = document.createElement('div');
        const ownerName = document.createElement('div');
        ownerName.style.fontSize = '0.875rem';
        ownerName.style.fontWeight = '600';
        ownerName.style.color = '#222222';
        ownerName.textContent = skill.owner_name || skill.user_name || 'User';

        const ownerRating = document.createElement('div');
        ownerRating.style.fontSize = '0.75rem';
        ownerRating.style.color = '#666666';
        if (skill.avg_rating && skill.avg_rating > 0) {
            ownerRating.innerHTML = `<i class="fas fa-star text-warning"></i> ${skill.avg_rating}`;
        } else {
            ownerRating.innerHTML = '<span style="color: #999;">Not rated yet</span>';
        }

        ownerInfo.appendChild(ownerName);
        ownerInfo.appendChild(ownerRating);

        owner.appendChild(avatar);
        owner.appendChild(ownerInfo);

        const price = document.createElement('div');
        price.className = 'skill-card-price';
        if (skill.credits_required && skill.credits_required > 0) {
            price.innerHTML = `<i class="fas fa-coins me-1"></i>${skill.credits_required} Credits`;
        } else {
            price.innerHTML = '<i class="fas fa-coins me-1"></i>Free';
        }

        // Actions container for price display
        const actionsContainer = document.createElement('div');
        actionsContainer.className = 'd-flex align-items-center gap-2';

        actionsContainer.appendChild(price);

        footer.appendChild(owner);
        footer.appendChild(actionsContainer);

        card.appendChild(content);
        card.appendChild(footer);

        // Make card clickable
        card.style.cursor = 'pointer';
        card.addEventListener('click', () => {
            window.location.href = 'skill-detail.html?id=' + skill.id;
        });

        col.appendChild(card);
        return col;
    }

    function renderSkills(skills) {
        skillsGrid.innerHTML = '';
        if (!skills || skills.length === 0) {
            skillsGrid.innerHTML = `
                <div class="col-12">
                    <div class="upwork-card">
                        <div class="upwork-card-body">
                            <div class="empty-state">
                                <div class="empty-state-icon"><i class="fas fa-search"></i></div>
                                <div class="empty-state-title">No skills found</div>
                                <div class="empty-state-text">Try adjusting your search or filters</div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            return;
        }
        const fragment = document.createDocumentFragment();
        skills.forEach(skill => {
            fragment.appendChild(createSkillCard(skill));
        });
        skillsGrid.appendChild(fragment);
    }

    function loadCategories() {
        fetch('backend/api/categories_list.php')
            .then(resp => resp.json())
            .then(data => {
                if (data && data.ok && Array.isArray(data.categories)) {
                    // clear existing options except the first
                    const first = categorySelect.querySelector('option');
                    categorySelect.innerHTML = '';
                    if (first) categorySelect.appendChild(first);

                    data.categories.forEach(cat => {
                        const opt = document.createElement('option');
                        opt.value = cat.id;
                        opt.textContent = cat.name;
                        categorySelect.appendChild(opt);
                    });
                }
            })
            .catch(err => console.error('Categories load error', err));
    }

    function loadSkills() {
        // Show loading state
        if (skillsGrid) {
            skillsGrid.innerHTML = '<div class="col-12 loading-spinner"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
        }

        // Collect filters
        const categoryId = categorySelect ? categorySelect.value : '';
        const sortBy = sortSelect ? sortSelect.value : 'rating';
        const searchQuery = searchInput ? searchInput.value.trim() : '';

        // Build URL
        const params = new URLSearchParams();
        if (categoryId) params.append('category', categoryId);
        if (sortBy) params.append('sort', sortBy);
        if (searchQuery) params.append('search', searchQuery);

        // Use unified API
        const url = 'backend/api/skills.php?' + params.toString();

        fetch(url)
            .then(resp => resp.json())
            .then(data => {
                if (data && data.ok && Array.isArray(data.skills)) {
                    renderSkills(data.skills);
                } else {
                    renderSkills([]); // Show empty state
                }
            })
            .catch(err => {
                console.error('Skills load error', err);
                if (skillsGrid) {
                    skillsGrid.innerHTML = '<div class="col-12"><div class="upwork-card"><div class="upwork-card-body"><div class="empty-state"><div class="empty-state-icon"><i class="fas fa-exclamation-triangle"></i></div><div class="empty-state-title">Error loading skills</div><div class="empty-state-text">Please try again later</div></div></div></div></div>';
                }
            });
    }

    // Initial load - only if elements exist
    if (categorySelect) {
        loadCategories();
    }
    if (skillsGrid) {
        loadSkills();
    }

    // filter by category
    if (categorySelect) {
        categorySelect.addEventListener('change', function () {
            loadSkills();
        });
    }

    // sort handler
    if (sortSelect) {
        sortSelect.addEventListener('change', function () {
            loadSkills();
        });
    }

    // search
    if (searchInput) {
        let timer = null;
        searchInput.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(() => {
                loadSkills();
            }, 500); // 500ms debounce
        });
    }

});
