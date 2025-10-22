// Helper: format 24-hour time to 12-hour AM/PM
const formatTime12hr = (timeStr) => {
    const [h, m] = timeStr.split(':');
    let hour = parseInt(h);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    hour = hour % 12 || 12;
    return `${hour}:${m} ${ampm}`;
};

// Load classes from server and map them by room_id
async function loadClasses() {
    const res = await fetch('/instructor/instructor_classes.php?action=fetch_classes');
    const data = await res.json();
    const classes = data.classes; // array of classes

    const classMap = {};
    classes.forEach(c => {
        if (c.room_id) {
            classMap[c.room_id] = {
                subject: c.subject_code, // changed from subject_name
                start: c.schedule_start,
                end: c.schedule_end,
                instructor: c.instructor_name || 'Instructor N/A', // make sure this comes from PHP
                section: c.section_name || 'Section N/A'
            };
        }
    });

    return classMap;
}

// Event listener for selecting a building
document.querySelectorAll('[data-action="select-building"]').forEach(item => {
    item.addEventListener('click', async (e) => {
        e.preventDefault();
        const buildingId = parseInt(item.dataset.buildingId);
        const roomsList = document.getElementById('rooms-list');

        // Find the selected building
        const building = buildingsData.find(b => b.building_id === buildingId);
        if (!building || !building.rooms.length) {
            roomsList.innerHTML = '<li class="text-roomu-black italic">No rooms found</li>';
            return;
        }

        // Fetch the latest classes
        const classMap = await loadClasses();

        roomsList.innerHTML = '';
        building.rooms.forEach(room => {
            const li = document.createElement('li');
            li.className = 'p-2 bg-gray-50 rounded-md cursor-pointer hover:bg-hover-roomu-green flex flex-col justify-between items-start space-y-1';
            li.dataset.roomId = room.room_id;

            // Set status color
            const statusColor = room.status === 'available' ? 'text-green-600' :
                room.status === 'occupied' ? 'text-red-600' : 'text-yellow-600';

            const currentClass = classMap[room.room_id];

            li.innerHTML = `
                <div class="flex justify-between w-full">
                    <span class="font-medium text-roomu-black">${room.room_name}</span>
                    <span class="text-xs ${statusColor} font-medium">${room.status}</span>
                </div>
                ${currentClass ? `
                    <div class="text-xs text-roomu-black/80 mt-1 space-y-0.5">
                        <div><strong>Section:</strong> ${currentClass.section}</div>
                        <div><strong>Subject:</strong> ${currentClass.subject}</div>
                        <div><strong>Instructor:</strong> ${currentClass.instructor}</div>
                        <div><strong>Schedule:</strong> ${formatTime12hr(currentClass.start)} - ${formatTime12hr(currentClass.end)}</div>
                    </div>
                ` : `<div class="text-xs italic text-gray-500 mt-1">No class assigned</div>`}
            `;

            roomsList.appendChild(li);
        });
    });
});
