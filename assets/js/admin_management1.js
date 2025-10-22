document.addEventListener('DOMContentLoaded', () => {
    // --- DOM Elements ---
    const modal = document.getElementById('modal');
    const modalForm = document.getElementById('modal-form');
    const modalTitle = document.getElementById('modal-title');
    const addBtn = document.getElementById('add-btn');
    const listContainer = document.getElementById('list-container');
    const detailsContainer = document.getElementById('details-container');
    const btnBuildings = document.getElementById('btn-buildings');
    const btnDepartments = document.getElementById('btn-departments');
    const listTitle = document.getElementById('list-title');
    const detailsTitle = document.getElementById('details-title');
    const addSubBtn = document.getElementById('add-sub-btn');

    // --- Scroll ---- (for buildings/rooms and departments/instructors list)
    listContainer.style.overflowY = 'auto';
    listContainer.style.maxHeight = '350px';
    detailsContainer.style.overflowY = 'auto';
    detailsContainer.style.maxHeight = '350px';

    // --- State ---
    let currentTab = 'buildings';
    let editId = null;
    let selectedBuildingId = null;
    let selectedBuildingName = '';

    // =========================
    // --- Tab Switching ---
    // =========================
    btnBuildings.onclick = () => switchTab('buildings');
    btnDepartments.onclick = () => switchTab('departments');

    function switchTab(tab) {
        currentTab = tab;
        btnBuildings.classList.toggle('active-tab', tab === 'buildings');
        btnDepartments.classList.toggle('active-tab', tab === 'departments');

        // Reset selections and UI
        listContainer.querySelectorAll('li').forEach(li => li.classList.remove('bg-gray-100'));
        detailsContainer.innerHTML = '';
        selectedBuildingId = null;

        updateTitles();
        renderList();
    }

    function updateTitles() {
        listTitle.textContent = currentTab === 'buildings' ? 'Buildings' : 'Departments';
        detailsTitle.textContent = currentTab === 'buildings'
            ? 'Rooms in Selected Building'
            : 'Instructors in Selected Department';
        addSubBtn.textContent = currentTab === 'buildings' ? '+ Add Room' : '+ Add Instructor';
    }

    // =========================
    // --- Modal Handling ---
    // =========================
    addBtn.onclick = () => openModal('add');

    function openModal(mode, id = null, currentName = '') {
        modal.classList.remove('hidden');
        editId = id;
        modalTitle.textContent = mode === 'add'
            ? `Add New ${currentTab.slice(0, -1)}`
            : `Edit ${currentTab.slice(0, -1)}`;

        modalForm.innerHTML = `
            <input type="text" name="name" value="${currentName}" placeholder="${currentTab.slice(0, -1)} Name" class="border p-2 rounded-md w-full" required>
            <div class="flex justify-end gap-2 mt-4">
                <button type="button" id="cancel-btn" class="border px-3 py-1 rounded-md hover:bg-gray-100">Cancel</button>
                <button type="submit" class="bg-roomu-green text-white px-3 py-1 rounded-md hover:bg-hover-roomu-green">Save</button>
            </div>
        `;
        modalForm.querySelector('#cancel-btn').onclick = closeModal;
    }

    function closeModal() {
        modal.classList.add('hidden');
        modalForm.innerHTML = '';
        editId = null;
    }

    // Submit form for add/edit
    modalForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const name = modalForm.querySelector('input[name="name"]').value.trim();
        if (!name) return;

        const formData = new FormData();
        formData.append('type', currentTab);
        formData.append('mode', editId ? 'edit' : 'add');
        formData.append('name', name);
        if (editId) formData.append('id', editId);

        try {
            const res = await fetch('/admin/admin_management.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                closeModal();
                renderList();
            } else {
                alert('Failed to save.');
            }
        } catch (err) {
            console.error(err);
        }
    });

    // =========================
    // --- Render List ---
    // =========================
    async function renderList() {
        try {
            const res = await fetch('/admin/admin_management.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ type: currentTab, mode: 'fetch' })
            });
            const data = await res.json();

            if (!data.success || !Array.isArray(data.items) || data.items.length === 0) {
                listContainer.innerHTML = `<li class="p-3 italic text-gray-500">No ${currentTab} found</li>`;
                detailsContainer.innerHTML = `<li class="p-3 italic text-gray-500">No ${currentTab === 'buildings' ? 'rooms' : 'instructors'} found</li>`;
                return;
            }

            listContainer.innerHTML = '';
            data.items.forEach(item => {
                const li = document.createElement('li');
                li.dataset.id = item.id;
                li.className = 'p-3 border rounded-md flex justify-between items-center mb-2 cursor-pointer hover:bg-gray-50';
                li.innerHTML = `
                    <span>${item.name}</span>
                    <div class="flex gap-2">
                        <button class="edit-btn text-blue-500 hover:underline">Edit</button>
                        <button class="remove-btn text-red-500 hover:underline">Remove</button>
                    </div>
                `;
                listContainer.appendChild(li);
            });
        } catch (err) {
            console.error(err);
            listContainer.innerHTML = `<li class="p-3 italic text-red-500">Error loading ${currentTab}</li>`;
        }
    }

    // =========================
    // --- Details Click Handler (Rooms & Instructors) ---
    // =========================
    detailsContainer.addEventListener('click', async (e) => {
        const button = e.target.closest('button'); // <- define button first
        if (!button) return;

        const li = button.closest('li');
        if (!li) return;

        const id = li.dataset.id;

        // --- Rooms ---
        if (currentTab === 'buildings') {
            if (button.classList.contains('edit-room')) {
                const oldName = li.querySelector('span').textContent.split(' (')[0];
                const newName = prompt('Enter new room name:', oldName);
                if (!newName) return;
                try {
                    const res = await fetch('/admin/admin_management.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({ mode: 'edit_room', id, name: newName })
                    });
                    const data = await res.json();
                    if (data.success) loadRooms(selectedBuildingId);
                    else alert('Failed to update room.');
                } catch (err) { console.error(err); }
            }

            if (button.classList.contains('remove-room')) {
                if (!confirm('Delete this room?')) return;
                try {
                    const res = await fetch('/admin/admin_management.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({ mode: 'delete_room', id })
                    });
                    const data = await res.json();
                    if (data.success) li.remove();
                    else alert('Failed to delete room.');
                } catch (err) { console.error(err); }
            }
        }

        // --- Instructors ---
        if (currentTab === 'departments') {
            if (button.classList.contains('edit-instructor')) {
                const oldName = li.querySelector('span').textContent.split(' (')[0];
                const newName = prompt('Enter new instructor name:', oldName);
                if (!newName) return;
                try {
                    const res = await fetch('/admin/admin_management.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({ mode: 'edit_instructor', id, full_name: newName })
                    });
                    const data = await res.json();
                    if (data.success) {
                        const deptId = document.querySelector('#list-container li.bg-gray-100')?.dataset.id;
                        if (deptId) loadInstructors(deptId);
                    } else alert('Failed to update instructor.');
                } catch (err) { console.error(err); }
            }

            // Remove instructor
            if (e.target.classList.contains('remove-instructor')) {
                if (!confirm('Are you sure you want to delete this instructor?')) return;
                try {
                    const res = await fetch('/admin/admin_management.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            mode: 'delete_instructor',
                            id: li.dataset.id
                        })
                    });
                    const data = await res.json();
                    if (data.success) li.remove();
                    else alert('Failed to delete instructor.');
                } catch (err) { console.error(err); }
            }
        }
    });

    // =========================
    // --- List Click Handler ---
    // =========================
    listContainer.addEventListener('click', async (e) => {
        const li = e.target.closest('li');
        if (!li) return;
        const id = li.dataset.id;
        const name = li.querySelector('span').textContent;

        // Click on name to show sublist
        if (!e.target.classList.contains('edit-btn') && !e.target.classList.contains('remove-btn')) {
            listContainer.querySelectorAll('li').forEach(el => el.classList.remove('bg-gray-100'));
            li.classList.add('bg-gray-100');

            if (currentTab === 'buildings') {
                selectedBuildingId = id;
                selectedBuildingName = name;
                detailsTitle.textContent = `Rooms in ${name}`;
                loadRooms(id);
            } else {
                selectedBuildingId = null;
                detailsTitle.textContent = `Instructors in ${name}`;
                loadInstructors(id);
            }
            return;
        }

        // Edit building/department
        if (e.target.classList.contains('edit-btn')) {
            openModal('edit', id, name);
        }

        // Delete building/department
        if (e.target.classList.contains('remove-btn')) {
            if (!confirm('Are you sure you want to delete this item?')) return;
            try {
                const res = await fetch('/admin/admin_management.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ type: currentTab, mode: 'delete', id })
                });
                const data = await res.json();
                if (data.success) li.remove();
                else alert('Failed to delete.');
            } catch (err) { console.error(err); alert('Error deleting item.'); }
        }
    });

    // =========================
    // --- Add Sub-item ---
    // =========================
    addSubBtn.addEventListener('click', async () => {
        if (currentTab === 'buildings') {
            if (!selectedBuildingId) return alert('Select a building first.');
            const roomName = prompt('Enter room name:');
            if (!roomName) return;
            try {
                const res = await fetch('/admin/admin_management.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ mode: 'add_room', building_id: selectedBuildingId, name: roomName })
                });
                const data = await res.json();
                if (data.success) loadRooms(selectedBuildingId);
                else alert('Failed to add room.');
            } catch (err) { console.error(err); }
        }

        if (currentTab === 'departments') {
            const selectedLi = document.querySelector('#list-container li.bg-gray-100');
            if (!selectedLi) return alert('Select a department first.');
            const departmentId = selectedLi.dataset.id;
            window.location.href = `/admin/registration.php?department_id=${departmentId}`;
        }
    });

    // =========================
    // --- Load Rooms / Instructors ---
    // =========================
    async function loadRooms(buildingId) {
        try {
            const res = await fetch('/admin/admin_management.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ mode: 'fetch_rooms', building_id: buildingId })
            });
            const data = await res.json();
            if (!data.success || !Array.isArray(data.items)) {
                detailsContainer.innerHTML = `<li class="p-3 italic text-gray-500">No rooms found</li>`;
                return;
            }

            detailsContainer.innerHTML = '';
            data.items.forEach(room => {
                const li = document.createElement('li');
                li.dataset.id = room.id;
                li.className = 'p-3 border rounded-md flex justify-between items-center hover:bg-gray-50 mb-2';
                li.innerHTML = `
                    <span>${room.name} <small class="text-gray-500">(${room.status})</small></span>
                    <div class="flex gap-2">
                        <button class="edit-room text-blue-500 hover:underline">Edit</button>
                        <button class="remove-room text-red-500 hover:underline">Remove</button>
                    </div>
                `;
                detailsContainer.appendChild(li);
            });
        } catch (err) {
            console.error('Fetch rooms error:', err);
            detailsContainer.innerHTML = `<li class="p-3 text-red-500">Error loading rooms</li>`;
        }
    }

    async function loadInstructors(departmentId) {
        try {
            const res = await fetch('/admin/admin_management.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ mode: 'fetch_instructors', department_id: departmentId })
            });
            const data = await res.json();
            detailsContainer.innerHTML = '';
            if (!data.success || !Array.isArray(data.items) || data.items.length === 0) {
                detailsContainer.innerHTML = `<li class="text-gray-500 text-center">No instructors found</li>`;
                return;
            }

            data.items.forEach(user => {
                const li = document.createElement('li');
                li.dataset.id = user.user_id;
                li.className = 'p-3 border rounded-md hover:bg-gray-50 cursor-pointer flex justify-between';
                li.innerHTML = `
                    <span>${user.full_name} (${user.email})</span>
                    <div class="flex gap-2">
                        <button class="edit-instructor text-blue-500 hover:underline">Edit</button>
                        <button class="remove-instructor text-red-500 hover:underline">Remove</button>
                    </div>
                `;
                detailsContainer.appendChild(li);
            });
        } catch (err) {
            console.error(err);
        }
    }

    // =========================
    // --- Initial Load ---
    // =========================
    renderList();
});
