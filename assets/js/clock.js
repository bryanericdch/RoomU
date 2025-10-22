document.addEventListener('DOMContentLoaded', function () {
    // 🔹 Update clock: time, day, date
    function updateClock() {
        const now = new Date();

        // Convert to Manila time
        const manilaTime = now.toLocaleString('en-US', { timeZone: 'Asia/Manila' });
        const manilaDate = new Date(manilaTime);

        // Format time HH:MM AM/PM
        let hours = manilaDate.getHours();
        const minutes = manilaDate.getMinutes().toString().padStart(2, '0');
        const ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12 || 12;
        const timeStr = `${hours}:${minutes} ${ampm}`;

        // Format day
        const dayStr = manilaDate.toLocaleDateString('en-US', { weekday: 'long', timeZone: 'Asia/Manila' });

        // Format date
        const dateStr = manilaDate.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric', timeZone: 'Asia/Manila' });

        // Update DOM
        const timeEl = document.getElementById('current-time');
        const dayEl = document.getElementById('current-day');
        const dateEl = document.getElementById('current-date');
        if (timeEl) timeEl.textContent = timeStr;
        if (dayEl) dayEl.textContent = dayStr;
        if (dateEl) dateEl.textContent = dateStr;
    }

    // 🔁 Refresh room/class list every minute
    async function refreshRoomStatus() {
        try {
            const res = await fetch('../instructor/instructor_classes.php?action=fetch_classes');
            const data = await res.json();

            const list = document.getElementById('class-list');
            if (list && data.classes) {
                list.innerHTML = '';
                data.classes.forEach(cls => {
                    const li = document.createElement('li');
                    li.className = 'p-2 border-b flex justify-between items-center';
                    li.innerHTML = `
                        <span>${cls.subject_code} (${cls.schedule_start}–${cls.schedule_end})</span>
                        <span class="${cls.status === 'occupied' ? 'text-red-500' : 'text-green-500'} font-medium">
                            ${cls.status}
                        </span>
                    `;
                    list.appendChild(li);
                });
            }
        } catch (err) {
            console.error('Error refreshing room statuses:', err);
        }
    }

    updateClock();
    setInterval(updateClock, 1000);
    setInterval(refreshRoomStatus, 60000);
});
