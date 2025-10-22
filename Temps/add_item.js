const modal = document.getElementById('modal');
const addBtn = document.getElementById('add-btn');
const cancelBtn = document.getElementById('cancel-btn');
const closeModal = document.getElementById('close-modal');
const modalForm = document.getElementById('modal-form');
const listTitle = document.getElementById('list-title');
const listContainer = document.getElementById('list-container');

let currentTab = 'buildings'; // default

// Determine current tab dynamically when modal opens
addBtn.addEventListener('click', () => {
    modal.classList.remove('hidden');
    currentTab = listTitle.textContent.toLowerCase(); // either 'buildings' or 'departments'
    document.getElementById('modal-title').textContent = `Add New ${currentTab === 'buildings' ? 'Building' : 'Department'}`;
});

// Open modal when clicking Edit button
document.querySelectorAll('.edit-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
        const li = e.target.closest('li');
        currentEditId = li.dataset.id;
        const currentName = li.querySelector('span').textContent;

        modalTitle.textContent = 'Edit Building';
        modal.querySelector('input[name="name"]').value = currentName;
        modal.classList.remove('hidden');
    });
});

// Close modal
cancelBtn.addEventListener('click', () => modal.classList.add('hidden'));
closeModal.addEventListener('click', () => modal.classList.add('hidden'));

// Submit modal form
modalForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const nameInput = modalForm.querySelector('input[name="name"]').value.trim();
    if (!nameInput) return;

    try {
        const res = await fetch('/admin/add_item.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name: nameInput, type: currentTab })
        });

        const data = await res.json();

        if (data.success) {
            const li = document.createElement('li');
            li.className = 'p-3 border rounded-md hover:bg-gray-50 cursor-pointer flex justify-between items-center';
            li.innerHTML = `<span>${nameInput}</span>
                            <div class="flex gap-2">
                                <button class="text-blue-500 hover:underline">Edit</button>
                                <button class="text-red-500 hover:underline">Remove</button>
                            </div>`;
            listContainer.appendChild(li);

            modalForm.reset();
            modal.classList.add('hidden');
        } else {
            alert(data.message);
        }

    } catch (err) {
        console.error(err);
        alert('Error saving data.');
    }
});
// Open modal for editing an item
function openEditModal(li) {
    editItem = li;
    currentTab = listTitle.textContent.toLowerCase();
    modal.classList.remove('hidden');
    modalTitle.textContent = `Edit ${currentTab === 'buildings' ? 'Building' : 'Department'}`;
    modalForm.querySelector('input[name="name"]').value = li.querySelector('span').textContent;
}
listContainer.querySelectorAll('li').forEach(li => {
    li.querySelector('.edit-btn').addEventListener('click', () => openEditModal(li));
});
// Attach edit/remove listeners to all existing items
document.querySelectorAll('#list-container li').forEach(li => {
    const editBtn = li.querySelector('.edit-btn');
    const removeBtn = li.querySelector('.remove-btn');

    if (editBtn) editBtn.addEventListener('click', () => openEditModal(li));
    if (removeBtn) removeBtn.addEventListener('click', () => removeItem(li));
});
