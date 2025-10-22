// Helper: format 24-hour time to 12-hour AM/PM
const formatTime12hr = (timeStr) => {
    if (!timeStr) return '-';
    const [h, m] = timeStr.split(':');
    let hour = parseInt(h, 10);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    hour = hour % 12 || 12;
    return `${hour}:${m} ${ampm}`;
};

// Function to render rooms for a building
function renderRooms(buildingId) {
    const building = buildingsData.find(b => b.building_id === buildingId);
    const roomsList = document.getElementById('rooms-list');

    if (!building || !building.rooms || !building.rooms.length) {
        roomsList.innerHTML = '<li class="text-roomu-black italic">No rooms found</li>';
        return;
    }

    roomsList.innerHTML = '';

    building.rooms.forEach(room => {
        const li = document.createElement('li');
        li.className = 'p-2 bg-gray-50 rounded-md cursor-pointer hover:bg-hover-roomu-green flex flex-col justify-between items-start space-y-1';
        li.dataset.roomId = room.room_id;

        const statusColor = room.status === 'available' ? 'text-green-600' :
            room.status === 'occupied' ? 'text-red-600' : 'text-yellow-600';

        const roomClasses = classesByRoom[room.room_id] || [];

        let classInfoHtml = '';
        if (roomClasses.length) {
            roomClasses.forEach(c => {
                classInfoHtml += `
                    <div class="text-xs text-roomu-black/80 mt-1 space-y-0.5">
                        <div><strong>Section:</strong> ${c.section_name || 'N/A'}</div>
                        <div><strong>Subject:</strong> ${c.subject_code || 'N/A'}</div>
                        <div><strong>Instructor:</strong> ${c.instructor_name || 'N/A'}</div>
                        <div><strong>Schedule:</strong> ${formatTime12hr(c.schedule_start)} - ${formatTime12hr(c.schedule_end)}</div>
                    </div>
                `;
            });
        } else {
            classInfoHtml = `<div class="text-xs italic text-gray-500 mt-1">No class assigned</div>`;
        }

        li.innerHTML = `
            <div class="flex justify-between w-full">
                <span class="font-medium text-roomu-black">${room.room_name}</span>
                <span class="text-xs ${statusColor} font-medium">${room.status}</span>
            </div>
            ${classInfoHtml}
        `;

        roomsList.appendChild(li);
    });
}

// Event listener for building selection
document.querySelectorAll('[data-action="select-building"]').forEach(item => {
    item.addEventListener('click', (e) => {
        e.preventDefault();
        const buildingId = parseInt(item.dataset.buildingId, 10);
        renderRooms(buildingId);

        // Highlight selected building
        document.querySelectorAll('[data-action="select-building"]').forEach(el => el.classList.remove('bg-hover-roomu-green'));
        item.classList.add('bg-hover-roomu-green');

        // Store currently selected building
        window.currentBuildingId = buildingId;
    });
});

// ------------------------
// Auto-update room statuses
// ------------------------
async function updateRoomStatuses() {
    try {
        const res = await fetch('/instructor/instructor_classes.php');
        const data = await res.json();

        // Update buildingsData array
        data.forEach(building => {
            const b = buildingsData.find(bd => bd.building_id === building.building_id);
            if (b) {
                b.available = building.available;
                b.occupied = building.occupied;
                b.maintenance = building.maintenance;

                if (b.rooms) {
                    b.rooms.forEach(room => {
                        const updatedRoom = building.rooms?.find(r => r.room_id === room.room_id);
                        if (updatedRoom) room.status = updatedRoom.status;
                    });
                }
            }
        });

        // Re-render rooms if a building is selected
        if (window.currentBuildingId) renderRooms(window.currentBuildingId);

        // Update status counts in building list
        data.forEach(building => {
            const li = document.querySelector(`[data-building-id="${building.building_id}"]`);
            if (!li) return;

            const statusDivs = li.querySelectorAll('div.flex.items-center span.ml-1');
            if (statusDivs.length >= 3) {
                statusDivs[0].textContent = building.available;
                statusDivs[1].textContent = building.occupied;
                statusDivs[2].textContent = building.maintenance;
            }
        });

    } catch (err) {
        console.error('Error updating room statuses:', err);
    }
}

// Refresh every 10 seconds
setInterval(updateRoomStatuses, 10000);
updateRoomStatuses();
