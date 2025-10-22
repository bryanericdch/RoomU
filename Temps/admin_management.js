/**
const modal = document.getElementById('modal');
const addBtn = document.getElementById('add-btn');
const cancelBtn = document.getElementById('cancel-btn');
const closeModal = document.getElementById('close-modal');

addBtn.onclick = () => modal.classList.remove('hidden');
cancelBtn.onclick = closeModal.onclick = () => modal.classList.add('hidden');


const btnBuildings = document.getElementById('btn-buildings');
const btnDepartments = document.getElementById('btn-departments');
const listTitle = document.getElementById('list-title');
const detailsTitle = document.getElementById('details-title');
const addSubBtn = document.getElementById('add-sub-btn');

btnBuildings.onclick = () => {
    btnBuildings.classList.add('active-tab');
    btnDepartments.classList.remove('active-tab');
    listTitle.textContent = "Buildings";
    detailsTitle.textContent = "Rooms in Selected Building";
    addSubBtn.textContent = "+ Add Room";
};

btnDepartments.onclick = () => {
    btnDepartments.classList.add('active-tab');
    btnBuildings.classList.remove('active-tab');
    listTitle.textContent = "Departments";
    detailsTitle.textContent = "Instructors in Selected Department";
    addSubBtn.textContent = "+ Add Instructor";
};  */


document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('modal');
    const addBtn = document.getElementById('add-btn');
    const cancelBtn = document.getElementById('cancel-btn');
    const closeModal = document.getElementById('close-modal');

    const btnBuildings = document.getElementById('btn-buildings');
    const btnDepartments = document.getElementById('btn-departments');
    const listTitle = document.getElementById('list-title');
    const detailsTitle = document.getElementById('details-title');
    const addSubBtn = document.getElementById('add-sub-btn');

    const listContainer = document.getElementById('list-container');
    const detailsContainer = document.getElementById('details-container');
    const modalForm = document.getElementById('modal-form');
    const modalTitle = document.getElementById('modal-title');

    // In-memory sample data (replace with AJAX / server calls later)
    const sampleData = {
        buildings: [
            { id: 1, name: 'PTC', rooms: [{ id: 1, name: 'Room 101' }, { id: 2, name: 'Room 102' }] },
            { id: 2, name: 'RS', rooms: [{ id: 3, name: 'Room 201' }] }
        ],
        departments: [
            { id: 1, name: 'CITE', instructors: [{ id: 1, name: 'Dale Doe', email: 'dale@x.com' }] },
            { id: 2, name: 'CAHS', instructors: [] }
        ]
    };

    let currentTab = 'buildings'; // 'buildings' or 'departments'
    let selectedId = null; // selected building_id or department_id
    let modalMode = null; // 'add', 'edit', 'add-sub', 'edit-sub'
    let editContext = null; // {itemId, subId}

    // Helpers to generate unique ids (simple)
    let nextId = 100;
    function genId() { return nextId++; }

    // Tab management
    function updateTitles() {
        listTitle.textContent = currentTab === 'buildings' ? 'Buildings' : 'Departments';
        detailsTitle.textContent = currentTab === 'buildings' ? 'Rooms in Selected Building' : 'Instructors in Selected Department';
        addSubBtn.textContent = currentTab === 'buildings' ? '+ Add Room' : '+ Add Instructor';
    }

    btnBuildings.onclick = () => {
        btnBuildings.classList.add('active-tab');
        btnDepartments.classList.remove('active-tab');
        currentTab = 'buildings';
        selectedId = null;
        updateTitles();
        renderList();
        renderDetails();
    };

    btnDepartments.onclick = () => {
        btnDepartments.classList.add('active-tab');
        btnBuildings.classList.remove('active-tab');
        currentTab = 'departments';
        selectedId = null;
        updateTitles();
        renderList();
        renderDetails();
    };

    // Render the left list (buildings or departments)
    function renderList() {
        listContainer.innerHTML = '';
        const list = sampleData[currentTab];
        if (!Array.isArray(list) || list.length === 0) {
            listContainer.innerHTML = `<li class="p-3 text-gray-500 italic">No ${currentTab} yet</li>`;
            return;
        }
        list.forEach(item => {
            const li = document.createElement('li');
            li.className = 'p-3 border rounded-md hover:bg-gray-50 cursor-pointer transition flex justify-between items-center';
            if (item.id === selectedId) li.classList.add('selected');
            li.dataset.id = item.id;
            li.innerHTML = `
                <span class="list-name">${escapeHtml(item.name)}</span>
                <div class="flex gap-2">
                    <button class="text-blue-500 edit-btn">Edit</button>
                    <button class="text-red-500 remove-btn">Remove</button>
                </div>
            `;
            // Click to select
            li.addEventListener('click', (e) => {
                // avoid selecting when clicking edit/remove
                if (e.target.classList.contains('edit-btn') || e.target.classList.contains('remove-btn')) return;
                selectedId = item.id;
                renderList();
                renderDetails();
            });

            // Edit
            li.querySelector('.edit-btn').addEventListener('click', (e) => {
                e.stopPropagation();
                openModal('edit', { itemId: item.id });
            });

            // Remove
            li.querySelector('.remove-btn').addEventListener('click', (e) => {
                e.stopPropagation();
                if (!confirm(`Remove "${item.name}"?`)) return;
                const idx = sampleData[currentTab].findIndex(x => x.id === item.id);
                if (idx !== -1) sampleData[currentTab].splice(idx, 1);
                if (selectedId === item.id) selectedId = null;
                renderList();
                renderDetails();
            });

            listContainer.appendChild(li);
        });
    }

    // Render the right/details list (rooms or instructors for the selected item)
    function renderDetails() {
        detailsContainer.innerHTML = '';
        if (!selectedId) {
            detailsContainer.innerHTML = `<li class="italic text-gray-500">Select a ${currentTab === 'buildings' ? 'building' : 'department'} to view details</li>`;
            return;
        }

        const item = sampleData[currentTab].find(x => x.id === selectedId);
        if (!item) { detailsContainer.innerHTML = `<li class="italic text-gray-500">Item not found</li>`; return; }

        if (currentTab === 'buildings') {
            const rooms = item.rooms || [];
            if (!rooms.length) {
                detailsContainer.innerHTML = `<li class="italic text-gray-500">No rooms yet</li>`;
                return;
            }
            rooms.forEach(room => {
                const li = document.createElement('li');
                li.className = 'p-3 border rounded-md hover:bg-gray-50 cursor-pointer flex justify-between items-center';
                li.innerHTML = `
                    <span>${escapeHtml(room.name)}</span>
                    <div class="flex gap-2">
                        <button class="text-blue-500 edit-room-btn">Edit</button>
                        <button class="text-red-500 remove-room-btn">Remove</button>
                    </div>
                `;
                li.querySelector('.edit-room-btn').addEventListener('click', (e) => {
                    e.stopPropagation();
                    openModal('edit-sub', { itemId: item.id, subId: room.id });
                });
                li.querySelector('.remove-room-btn').addEventListener('click', (e) => {
                    e.stopPropagation();
                    if (!confirm(`Remove room "${room.name}"?`)) return;
                    item.rooms = item.rooms.filter(r => r.id !== room.id);
                    renderDetails();
                });
                detailsContainer.appendChild(li);
            });
        } else {
            const instructors = item.instructors || [];
            if (!instructors.length) {
                detailsContainer.innerHTML = `<li class="italic text-gray-500">No instructors yet</li>`;
                return;
            }
            instructors.forEach(inst => {
                const li = document.createElement('li');
                li.className = 'p-3 border rounded-md hover:bg-gray-50 cursor-pointer flex justify-between items-center';
                li.innerHTML = `
                    <div>
                        <div class="font-medium">${escapeHtml(inst.name)}</div>
                        <div class="text-xs text-gray-600">${escapeHtml(inst.email || '')}</div>
                    </div>
                    <div class="flex gap-2 items-center">
                        <button class="text-blue-500 edit-inst-btn">Edit</button>
                        <button class="text-red-500 remove-inst-btn">Remove</button>
                    </div>
                `;
                li.querySelector('.edit-inst-btn').addEventListener('click', (e) => {
                    e.stopPropagation();
                    openModal('edit-sub', { itemId: item.id, subId: inst.id });
                });
                li.querySelector('.remove-inst-btn').addEventListener('click', (e) => {
                    e.stopPropagation();
                    if (!confirm(`Remove instructor "${inst.name}"?`)) return;
                    item.instructors = item.instructors.filter(i => i.id !== inst.id);
                    renderDetails();
                });
                detailsContainer.appendChild(li);
            });
        }
    }

    // Modal open/close and form generation
    addBtn.onclick = () => openModal('add');
    addSubBtn.onclick = () => {
        if (!selectedId) { alert('Select a building/department first'); return; }
        openModal('add-sub', { itemId: selectedId });
    };
    cancelBtn.onclick = closeModal.onclick = () => { closeModalFn(); };

    function openModal(mode, ctx = {}) {
        modalMode = mode;
        editContext = ctx || null;
        modal.classList.remove('hidden');

        // Build form depending on mode
        if (mode === 'add') {
            modalTitle.textContent = currentTab === 'buildings' ? 'Add New Building' : 'Add New Department';
            modalForm.innerHTML = `
                <input type="text" name="name" placeholder="${currentTab === 'buildings' ? 'Building Name' : 'Department Name'}" class="border p-2 rounded-md" required>
                <div class="flex justify-end gap-2 mt-4">
                    <button type="button" id="cancel-btn" class="border px-3 py-1 rounded-md hover:bg-gray-100 transition cursor-pointer">Cancel</button>
                    <button type="submit" class="bg-roomu-green text-white px-3 py-1 rounded-md hover:bg-hover-roomu-green transition cursor-pointer">Save</button>
                </div>
            `;
        } else if (mode === 'edit') {
            const list = sampleData[currentTab];
            const it = list.find(i => i.id === ctx.itemId);
            modalTitle.textContent = `Edit ${currentTab === 'buildings' ? 'Building' : 'Department'}`;
            modalForm.innerHTML = `
                <input type="text" name="name" value="${escapeAttr(it ? it.name : '')}" placeholder="Name" class="border p-2 rounded-md" required>
                <div class="flex justify-end gap-2 mt-4">
                    <button type="button" id="cancel-btn" class="border px-3 py-1 rounded-md hover:bg-gray-100 transition cursor-pointer">Cancel</button>
                    <button type="submit" class="bg-roomu-green text-white px-3 py-1 rounded-md hover:bg-hover-roomu-green transition cursor-pointer">Save</button>
                </div>
            `;
        } else if (mode === 'add-sub') {
            modalTitle.textContent = currentTab === 'buildings' ? 'Add New Room' : 'Add New Instructor';
            if (currentTab === 'buildings') {
                modalForm.innerHTML = `
                    <input type="text" name="name" placeholder="Room Name" class="border p-2 rounded-md" required>
                    <div class="flex justify-end gap-2 mt-4">
                        <button type="button" id="cancel-btn" class="border px-3 py-1 rounded-md hover:bg-gray-100 transition cursor-pointer">Cancel</button>
                        <button type="submit" class="bg-roomu-green text-white px-3 py-1 rounded-md hover:bg-hover-roomu-green transition cursor-pointer">Save</button>
                    </div>
                `;
            } else {
                modalForm.innerHTML = `
                    <input type="text" name="name" placeholder="Instructor Name" class="border p-2 rounded-md" required>
                    <input type="email" name="email" placeholder="Email" class="border p-2 rounded-md mt-2" required>
                    <input type="password" name="password" placeholder="Password" class="border p-2 rounded-md mt-2" required>
                    <div class="flex justify-end gap-2 mt-4">
                        <button type="button" id="cancel-btn" class="border px-3 py-1 rounded-md hover:bg-gray-100 transition cursor-pointer">Cancel</button>
                        <button type="submit" class="bg-roomu-green text-white px-3 py-1 rounded-md hover:bg-hover-roomu-green transition cursor-pointer">Save</button>
                    </div>
                `;
            }
        } else if (mode === 'edit-sub') {
            const parent = sampleData[currentTab].find(i => i.id === ctx.itemId) || {};
            let sub = null;
            if (currentTab === 'buildings') sub = (parent.rooms || []).find(r => r.id === ctx.subId) || {};
            else sub = (parent.instructors || []).find(i => i.id === ctx.subId) || {};
            modalTitle.textContent = currentTab === 'buildings' ? 'Edit Room' : 'Edit Instructor';
            if (currentTab === 'buildings') {
                modalForm.innerHTML = `
                    <input type="text" name="name" value="${escapeAttr(sub.name || '')}" placeholder="Room Name" class="border p-2 rounded-md" required>
                    <div class="flex justify-end gap-2 mt-4">
                        <button type="button" id="cancel-btn" class="border px-3 py-1 rounded-md hover:bg-gray-100 transition cursor-pointer">Cancel</button>
                        <button type="submit" class="bg-roomu-green text-white px-3 py-1 rounded-md hover:bg-hover-roomu-green transition cursor-pointer">Save</button>
                    </div>
                `;
            } else {
                modalForm.innerHTML = `
                    <input type="text" name="name" value="${escapeAttr(sub.name || '')}" placeholder="Instructor Name" class="border p-2 rounded-md" required>
                    <input type="email" name="email" value="${escapeAttr(sub.email || '')}" placeholder="Email" class="border p-2 rounded-md mt-2" required>
                    <div class="flex justify-end gap-2 mt-4">
                        <button type="button" id="cancel-btn" class="border px-3 py-1 rounded-md hover:bg-gray-100 transition cursor-pointer">Cancel</button>
                        <button type="submit" class="bg-roomu-green text-white px-3 py-1 rounded-md hover:bg-hover-roomu-green transition cursor-pointer">Save</button>
                    </div>
                `;
            }
        }

        // Re-hook cancel button inside dynamic form (exists after innerHTML set)
        const innerCancel = modalForm.querySelector('#cancel-btn');
        if (innerCancel) innerCancel.onclick = () => closeModalFn();
    }

    function closeModalFn() {
        modal.classList.add('hidden');
        modalForm.innerHTML = '';
        modalMode = null;
        editContext = null;
    }

    // Handle form submit for all modal modes
    modalForm.addEventListener('submit', (e) => {
        e.preventDefault();
        const form = new FormData(modalForm);
        const name = form.get('name') && form.get('name').trim();
        if (!name) return alert('Name is required');

        if (modalMode === 'add') {
            const newItem = { id: genId(), name };
            if (currentTab === 'buildings') newItem.rooms = [];
            else newItem.instructors = [];
            sampleData[currentTab].push(newItem);
            selectedId = newItem.id;
            renderList();
            renderDetails();
            closeModalFn();
        } else if (modalMode === 'edit' && editContext) {
            const list = sampleData[currentTab];
            const it = list.find(x => x.id === editContext.itemId);
            if (it) it.name = name;
            renderList();
            renderDetails();
            closeModalFn();
        } else if (modalMode === 'add-sub' && editContext) {
            const parent = sampleData[currentTab].find(i => i.id === editContext.itemId);
            if (!parent) return;
            if (currentTab === 'buildings') {
                parent.rooms = parent.rooms || [];
                parent.rooms.push({ id: genId(), name });
            } else {
                parent.instructors = parent.instructors || [];
                parent.instructors.push({ id: genId(), name, email: form.get('email') });
            }
            renderDetails();
            closeModalFn();
        } else if (modalMode === 'edit-sub' && editContext) {
            const parent = sampleData[currentTab].find(i => i.id === editContext.itemId);
            if (!parent) return;
            if (currentTab === 'buildings') {
                const room = (parent.rooms || []).find(r => r.id === editContext.subId);
                if (room) room.name = name;
            } else {
                const inst = (parent.instructors || []).find(x => x.id === editContext.subId);
                if (inst) { inst.name = name; inst.email = form.get('email'); }
            }
            renderDetails();
            closeModalFn();
        }
    });

    // small helpers
    function escapeHtml(s) { if (!s) return ''; return String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c])); }
    function escapeAttr(s) { return escapeHtml(s); }

    // Initial render
    updateTitles();
    renderList();
    renderDetails();
});
// ...existing code...

// ...existing code...