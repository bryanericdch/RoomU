// edit_item.js
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('modal');
    const modalForm = document.getElementById('modal-form');
    const modalTitle = document.getElementById('modal-title');
    const cancelBtn = document.getElementById('cancel-btn');
    const closeModal = document.getElementById('close-modal');
    const listTitle = document.getElementById('list-title');
    const listContainer = document.getElementById('list-container');

    let currentEditId = null;

    // Open edit modal
    function openEditModal(li) {
        currentEditId = li.dataset.id;
        const currentName = li.querySelector('span').textContent;

        modalTitle.textContent = `Edit ${listTitle.textContent.slice(0, -1)}`; // "Building" or "Department"
        modal.querySelector('input[name="name"]').value = currentName;
        modal.classList.remove('hidden');
    }

    // Attach edit buttons to all existing items
    listContainer.querySelectorAll('li').forEach(li => {
        const editBtn = li.querySelector('.edit-btn');
        if (editBtn) editBtn.addEventListener('click', () => openEditModal(li));
    });

    // Close modal
    cancelBtn.addEventListener('click', () => {
        modal.classList.add('hidden');
        currentEditId = null;
    });
    closeModal.addEventListener('click', () => {
        modal.classList.add('hidden');
        currentEditId = null;
    });

    // Submit modal form
    modalForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const name = modalForm.querySelector('input[name="name"]').value.trim();
        if (!name) return alert('Name is required');
        if (!currentEditId) return alert('No item selected');

        try {
            const res = await fetch('/admin/edit_building.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: currentEditId, name })
            });

            const data = await res.json();

            if (data.success) {
                // Update the name in the UI
                const li = listContainer.querySelector(`li[data-id='${currentEditId}']`);
                if (li) li.querySelector('span').textContent = name;

                modal.classList.add('hidden');
                currentEditId = null;
            } else {
                alert(data.error || 'Failed to update.');
            }
        } catch (err) {
            console.error(err);
            alert('Error updating item.');
        }
    });
});
