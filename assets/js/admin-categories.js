document.addEventListener('DOMContentLoaded', function() {
  const categoriesTableBody = document.querySelector('#categoriesTable tbody');
  const createForm = document.getElementById('createCategoryForm');
  const newCategoryName = document.getElementById('newCategoryName');
  const adminMessage = document.getElementById('adminMessage');

  function showMessage(msg, isError) {
    adminMessage.textContent = msg;
    adminMessage.classList.toggle('text-danger', !!isError);
    adminMessage.classList.toggle('text-success', !isError);
    setTimeout(() => adminMessage.textContent = '', 4000);
  }

  function loadCategories() {
    fetch('backend/api/categories_list.php', { credentials: 'include' })
      .then(r => r.json())
      .then(data => {
        categoriesTableBody.innerHTML = '';
        if (!data || !data.ok) return;
        data.categories.forEach(cat => {
          const tr = document.createElement('tr');
          tr.innerHTML = `
            <td>${cat.category_id}</td>
            <td>${escapeHtml(cat.category_name)}</td>
            <td>${cat.created_at ? cat.created_at : ''}</td>
            <td class="text-end">
              <button class="btn btn-sm btn-danger btn-delete" data-id="${cat.category_id}">Delete</button>
            </td>
          `;
          categoriesTableBody.appendChild(tr);
        });
        // attach delete handlers
        document.querySelectorAll('.btn-delete').forEach(btn => {
          btn.addEventListener('click', function() {
            const id = this.dataset.id;
            if (!confirm('Delete this category? This will NOT delete associated skills but will set their category to NULL.')) return;
            deleteCategory(id);
          });
        });
      })
      .catch(err => console.error('Categories load error', err));
  }

  function createCategory(name) {
    fetch('backend/api/categories_create.php', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ category_name: name })
    })
    .then(r => r.json())
    .then(data => {
      if (data && data.ok) {
        showMessage('Category created');
        newCategoryName.value = '';
        loadCategories();
      } else {
        showMessage(data.error || 'Failed to create', true);
      }
    })
    .catch(err => showMessage('Network error', true));
  }

  function deleteCategory(id) {
    fetch('backend/api/categories_delete.php', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ category_id: id })
    })
    .then(r => r.json())
    .then(data => {
      if (data && data.ok) {
        showMessage('Category deleted');
        loadCategories();
      } else {
        showMessage(data.error || 'Failed to delete', true);
      }
    })
    .catch(err => showMessage('Network error', true));
  }

  createForm.addEventListener('submit', function(e) {
    e.preventDefault();
    const name = newCategoryName.value.trim();
    if (!name) {
      showMessage('Enter a category name', true);
      return;
    }
    createCategory(name);
  });

  // Basic HTML escaper
  function escapeHtml(s) {
    return (s + '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  loadCategories();
});