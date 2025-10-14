let selectedBuilding = null;
let selectedRoom = null;

// Initialize on load
function init() {
    const hash = window.location.hash.substring(1);
    if (hash) {
        const params = new URLSearchParams(hash);
        const buildingId = params.get('building');
        const roomId = params.get('room');
        if (buildingId) selectBuilding(parseInt(buildingId));
        if (roomId) selectRoom(parseInt(roomId));
    }
}

// ✅ Select Building
function selectBuilding(buildingId) {
    // Remove highlights
    document.querySelectorAll('[data-building-id]').forEach(item => item.classList.remove('selected'));

    const buildingItem = document.querySelector(`[data-building-id="${buildingId}"]`);
    if (buildingItem) {
        buildingItem.classList.add('selected');
        buildingItem.setAttribute('aria-selected', 'true');

        selectedBuilding = sampleData.buildings.find(b => b.id === buildingId);
        populateRooms(buildingId);

        window.location.hash = `building=${buildingId}`;
    }
}

// ✅ Populate Rooms
function populateRooms(buildingId) {
    const building = sampleData.buildings.find(b => b.id === buildingId);
    const roomsList = document.getElementById('rooms-list');
    roomsList.innerHTML = '';

    if (building && building.rooms) {
        building.rooms.forEach(room => {
            const statusClass =
                room.status === 'available'
                    ? 'text-green-600'
                    : room.status === 'occupied'
                        ? 'text-red-600'
                        : 'text-yellow-600';

            const li = document.createElement('li');
            li.className = 'flex items-center justify-between p-3 rounded-md hover:bg-hover-roomu-green cursor-pointer transition duration-200 ease-in-out';
            li.setAttribute('data-room-id', room.id);
            li.setAttribute('data-action', 'select-room');

            li.innerHTML = `
                <span class="text-base font-medium text-roomu-black flex-1">${room.name}</span>
                <span class="px-2 py-1 text-xs font-semibold rounded-full ${statusClass} bg-opacity-10">
                    ${room.status.charAt(0).toUpperCase() + room.status.slice(1)}
                </span>
            `;
            roomsList.appendChild(li);
        });
    } else {
        roomsList.innerHTML = `<li class="italic text-gray-500">No rooms available</li>`;
    }
}

// ✅ Select Room
function selectRoom(roomId) {
    document.querySelectorAll('[data-room-id]').forEach(item => item.classList.remove('selected'));

    const roomItem = document.querySelector(`[data-room-id="${roomId}"]`);
    if (roomItem) {
        roomItem.classList.add('selected');
        roomItem.setAttribute('aria-selected', 'true');
        selectedRoom = roomId;

        populateDetails(roomId);

        window.location.hash = `building=${selectedBuilding.id}&room=${roomId}`;
    }
}

// ✅ Populate Details in Sidebar
function populateDetails(roomId) {
    const sidebar = document.getElementById('details-sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    const sidebarContent = document.getElementById('sidebar-content');
    const checkinBtn = document.getElementById('checkin-btn');

    const room = selectedBuilding.rooms.find(r => r.id === roomId);
    const details = sampleData.roomDetails[roomId];

    sidebarContent.innerHTML = `
        <div class="p-4 bg-gray-50 rounded-md">
            <p class="text-sm mb-2"><strong>Status:</strong>
                <span class="font-semibold ${room.status === 'available'
            ? 'text-green-600'
            : room.status === 'occupied'
                ? 'text-red-600'
                : 'text-yellow-600'
        }">
                    ${room.status.charAt(0).toUpperCase() + room.status.slice(1)}
                </span>
            </p>
        </div>

        ${room.status === 'occupied' && details
            ? `
            <div class="p-4 bg-blue-50 rounded-md">
                <h3 class="font-semibold mb-3">Current Class</h3>
                <ul class="space-y-2 text-sm">
                    <li><strong>Instructor:</strong> ${details.instructor}</li>
                    <li><strong>Course & Section:</strong> ${details.courseSection}</li>
                    <li><strong>Subject Code:</strong> ${details.subjectCode}</li>
                    <li><strong>Schedule:</strong> ${details.schedule}</li>
                </ul>
            </div>
        `
            : room.status === 'available'
                ? `
            <div class="p-4 bg-green-50 rounded-md">
                <p class="text-sm text-green-700">Room is available - no class details.</p>
            </div>
        `
                : `
            <div class="p-4 bg-yellow-50 rounded-md">
                <p class="text-sm text-yellow-700">Room under maintenance - no class details.</p>
            </div>
        `
        }
    `;

    // Show/hide check-in button
    if (room.status === 'available') {
        checkinBtn.classList.remove('hidden');

    } else {
        checkinBtn.classList.add('hidden');
        checkinBtn.onclick = null;
    }

    // Slide sidebar in
    sidebar.classList.remove('translate-x-full');
    sidebar.classList.add('translate-x-0');
    overlay.classList.remove('hidden');
}

// ✅ Sidebar close controls
document.getElementById('close-sidebar').addEventListener('click', closeSidebar);
document.getElementById('sidebar-overlay').addEventListener('click', closeSidebar);

function closeSidebar() {
    const sidebar = document.getElementById('details-sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    sidebar.classList.add('translate-x-full');
    sidebar.classList.remove('translate-x-0');
    overlay.classList.add('hidden');
}

// ✅ Global Clicks
document.addEventListener('click', function (e) {
    const buildingEl = e.target.closest('[data-action="select-building"]');
    const roomEl = e.target.closest('[data-action="select-room"]');

    if (buildingEl) {
        e.preventDefault();
        selectBuilding(parseInt(buildingEl.dataset.buildingId));
    } else if (roomEl) {
        e.preventDefault();
        selectRoom(parseInt(roomEl.dataset.roomId));
    }
});

// ✅ Start
init();
