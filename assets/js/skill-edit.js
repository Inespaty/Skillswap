document.addEventListener('DOMContentLoaded', function () {
    const skillForm = document.getElementById('skillForm');
    const categorySelect = document.getElementById('category_id');
    const imageUpload = document.getElementById('imageUpload'); // May not exist
    const coverImageInput = document.getElementById('coverImage');
    const imagePreview = document.getElementById('imagePreview');
    const skillTitle = document.getElementById('skillTitle');
    const skillLevel = document.getElementById('skillLevel'); // May not exist
    const skillDescription = document.getElementById('skillDescription');
    const previewTitle = document.getElementById('previewTitle'); // May not exist
    const previewLevel = document.getElementById('previewLevel'); // May not exist
    const previewDescription = document.getElementById('previewDescription'); // May not exist
    const previewCategory = document.getElementById('previewCategory'); // May not exist
    const creditsInput = document.getElementById('creditsAmount');
    const previewPrice = document.getElementById('previewPrice'); // May not exist

    // Load categories from backend
    function loadCategories() {
        fetch('backend/api/categories_list.php', { credentials: 'include' })
            .then(r => r.json())
            .then(data => {
                if (data && data.ok && Array.isArray(data.categories)) {
                    // Clear existing options except the first placeholder
                    const firstOpt = categorySelect.querySelector('option:first-child');
                    categorySelect.innerHTML = '';
                    if (firstOpt) {
                        categorySelect.appendChild(firstOpt);
                    }

                    // Add categories from API
                    data.categories.forEach(cat => {
                        const opt = document.createElement('option');
                        opt.value = cat.id;  // API returns 'id', not 'category_id'
                        opt.textContent = cat.name;  // API returns 'name', not 'category_name'
                        categorySelect.appendChild(opt);
                    });

                    // Ensure first option text is correct
                    const firstOption = categorySelect.querySelector('option:first-child');
                    if (firstOption) {
                        firstOption.textContent = 'Select a category';
                    }
                } else {
                    console.error('Categories data format error:', data);
                }
            })
            .catch(err => {
                console.error('Load categories error', err);
                // Show user-friendly error
                const errorOpt = document.createElement('option');
                errorOpt.value = '';
                errorOpt.textContent = 'Error loading categories';
                errorOpt.disabled = true;
                categorySelect.appendChild(errorOpt);
            });
    }

    // Image upload handler (only if imageUpload element exists)
    if (imageUpload && coverImageInput) {
        imageUpload.addEventListener('click', () => coverImageInput.click());

        imageUpload.addEventListener('dragover', (e) => {
            e.preventDefault();
            imageUpload.style.borderColor = '#3B82F6';
            imageUpload.style.backgroundColor = '#DBEAFE';
        });

        imageUpload.addEventListener('dragleave', () => {
            imageUpload.style.borderColor = '#DEE2E6';
            imageUpload.style.backgroundColor = '#F8F9FA';
        });

        imageUpload.addEventListener('drop', (e) => {
            e.preventDefault();
            imageUpload.style.borderColor = '#DEE2E6';
            imageUpload.style.backgroundColor = '#F8F9FA';
            const files = e.dataTransfer.files;
            if (files.length > 0) handleImageSelect(files[0]);
        });
    }

    coverImageInput.addEventListener('change', (e) => {
        if (e.target.files.length > 0) handleImageSelect(e.target.files[0]);
    });

    function handleImageSelect(file) {
        if (!file.type.startsWith('image/')) {
            showToast('Please select a valid image file', 'warning');
            return;
        }
        if (!imagePreview) return;
        const reader = new FileReader();
        reader.onload = (e) => {
            imagePreview.src = e.target.result;
            imagePreview.style.display = 'block';
        };
        reader.readAsDataURL(file);
    }

    // Image upload handler for simple file input
    if (coverImageInput) {
        coverImageInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) handleImageSelect(e.target.files[0]);
        });
    }

    // Real-time preview updates (only if preview elements exist)
    if (skillTitle && previewTitle) {
        skillTitle.addEventListener('input', () => previewTitle.textContent = skillTitle.value || 'Your Skill Title');
    }
    if (skillLevel && previewLevel) {
        skillLevel.addEventListener('change', () => previewLevel.textContent = skillLevel.value || 'All Levels');
    }
    if (skillDescription && previewDescription) {
        skillDescription.addEventListener('input', () => previewDescription.textContent = skillDescription.value || 'Your skill description will appear here.');
    }
    if (categorySelect && previewCategory) {
        categorySelect.addEventListener('change', () => {
            const selected = categorySelect.options[categorySelect.selectedIndex];
            previewCategory.textContent = selected.textContent;
        });
    }

    // Credits preview (only if preview element exists)
    if (creditsInput && previewPrice) {
        creditsInput.addEventListener('input', () => {
            const v = parseInt(creditsInput.value || '0', 10) || 0;
            previewPrice.textContent = v > 0 ? `${v} credits` : 'Free';
        });
        // initialize preview
        const v = parseInt(creditsInput.value || '0', 10) || 0;
        previewPrice.textContent = v > 0 ? `${v} credits` : 'Free';
    }

    // Form submission
    skillForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        // Protect route check
        if (!window.Auth || !window.Auth.isAuthenticated()) {
            showToast('Please log in to create a skill', 'warning');
            window.location.href = 'login.html';
            return;
        }

        // Check if we're editing an existing skill
        const urlParams = new URLSearchParams(window.location.search);
        const skillId = urlParams.get('id');
        const isEdit = !!skillId;

        const formData = new FormData();
        formData.append('title', skillTitle.value);
        formData.append('description', skillDescription.value);
        formData.append('category_id', categorySelect.value);

        // Credits (site credits, default 0)
        let credits = 0;
        if (creditsInput) {
            credits = parseInt(creditsInput.value || '0', 10) || 0;
        }
        formData.append('credits', credits);

        if (coverImageInput.files.length > 0) {
            formData.append('image', coverImageInput.files[0]);
        }

        // If editing, add skill_id
        if (isEdit) {
            formData.append('skill_id', skillId);
        }

        // publish button handling (fallback if id missing)
        let publishBtn = document.getElementById('publishBtn');
        if (!publishBtn) publishBtn = skillForm.querySelector('button[type="submit"]');
        const originalText = publishBtn ? publishBtn.textContent : '';
        if (publishBtn) {
            publishBtn.disabled = true;
            publishBtn.textContent = isEdit ? 'Updating...' : 'Publishing...';
        }

        try {
            const endpoint = isEdit ? 'backend/api/skill_edit.php' : 'backend/api/skill_create.php';
            const response = await fetch(endpoint, {
                method: 'POST',
                body: formData,
                credentials: 'include'
            });

            const data = await response.json();

            if (data && data.ok) {
                const message = data.message || (isEdit
                    ? 'Skill updated successfully!'
                    : 'Skill created successfully and is now live!');
                showToast(message, 'success');
                // Redirect to skill detail page if it's a new skill, otherwise back to dashboard
                if (!isEdit && data.skill_id) {
                    window.location.href = `skill-detail.html?id=${data.skill_id}`;
                } else {
                    window.location.href = 'dashboard.html';
                }
            } else {
                showToast('Error: ' + (data.error || `Failed to ${isEdit ? 'update' : 'create'} skill`), 'error');
            }
        } catch (error) {
            showToast('Network error: ' + error.message, 'error');
        } finally {
            if (publishBtn) {
                publishBtn.disabled = false;
                publishBtn.textContent = originalText;
            }
        }
    });

    // Load existing skill if ID is in URL
    function loadExistingSkill() {
        const urlParams = new URLSearchParams(window.location.search);
        const skillId = urlParams.get('id');

        if (skillId) {
            fetch(`backend/api/skill_edit.php?id=${skillId}`, { credentials: 'include' })
                .then(r => r.json())
                .then(data => {
                    if (data && data.ok && data.skill) {
                        const skill = data.skill;

                        // Populate form fields
                        if (skillTitle) skillTitle.value = skill.title || '';
                        if (skillDescription) skillDescription.value = skill.description || '';
                        if (creditsInput) {
                            creditsInput.value = skill.credits_required || skill.credits || 0;
                        }

                        // Set category after categories are loaded
                        if (categorySelect && skill.category_id) {
                            // Wait for categories to load, then set the value
                            const setCategory = () => {
                                if (categorySelect.querySelector(`option[value="${skill.category_id}"]`)) {
                                    categorySelect.value = skill.category_id;
                                } else {
                                    // If categories not loaded yet, wait a bit and try again
                                    setTimeout(setCategory, 100);
                                }
                            };
                            setCategory();
                        }

                        // Load image if exists
                        if (imagePreview && skill.image) {
                            imagePreview.src = skill.image.startsWith('http') || skill.image.startsWith('/')
                                ? skill.image
                                : './' + skill.image;
                            imagePreview.style.display = 'block';
                        }

                        // Update page title
                        document.querySelector('.page-title-upwork').textContent = 'Edit Skill';
                        document.querySelector('.page-subtitle-upwork').textContent = 'Update your skill information';

                        // Change button text
                        const publishBtn = document.getElementById('publishBtn');
                        if (publishBtn) {
                            publishBtn.textContent = 'Update Skill';
                        }
                    } else {
                        console.error('Failed to load skill:', data.error);
                        showToast('Failed to load skill: ' + (data.error || 'Unknown error'), 'error');
                    }
                })
                .catch(err => {
                    console.error('Error loading skill:', err);
                    showToast('Error loading skill details', 'error');
                });
        }
    }

    // Initial loads
    loadCategories();

    // Load existing skill after categories are loaded (so category dropdown is populated)
    setTimeout(() => {
        loadExistingSkill();
    }, 300);
});
