document.addEventListener('DOMContentLoaded', () => {
    const checkinBtn = document.getElementById('checkin-btn');
    const modal = document.getElementById('modal-checkin');
    const cancel = document.getElementById('checkin-cancel');
    const form = document.getElementById('checkin-form');

    const instructorInput = document.getElementById('checkin-instructor');
    const courseSelect = document.getElementById('checkin-course');
    const sectionSelect = document.getElementById('checkin-section');
    const classSelect = document.getElementById('checkin-class');

    // track selected room (set by room click handler below)
    let currentRoomId = null;
    // instructor name/id (adjust to use PHP values if available)
    const instructorDisplay = document.getElementById('instructor-display');
    const instructorName = instructorDisplay ? instructorDisplay.textContent.trim() : 'Instructor';
    // optional: instructor id via data attr: <div id="instructor-display" data-id="123">Name</div>
    const instructorId = instructorDisplay ? instructorDisplay.dataset.id || null : null;

    // Fallback sample data (used when backend endpoints not available)
    const sample = {
        courses: [
            { id: 'c1', name: 'BSIT' },
            { id: 'c2', name: 'BSBA' }
        ],
        sections: {
            'c1': [{ id: 's1', name: 'BSIT2-05' }, { id: 's2', name: 'BSIT3-01' }],
            'c2': [{ id: 's3', name: 'BSBA3-A' }]
        },
        classes: {
            's1': [{ id: 'cl1', subject: 'ITE-300' }, { id: 'cl2', subject: 'CS201' }],
            's2': [{ id: 'cl3', subject: 'NET-101' }],
            's3': [{ id: 'cl4', subject: 'MGMT201' }]
        }
    };

    // helper: fetch helper with fallback to sample data
    function fetchJSON(url) {
        return fetch(url, { credentials: 'same-origin' })
            .then(r => r.ok ? r.json() : Promise.reject())
            .catch(() => Promise.reject());
    }

    function openModalFor(roomId) {
        currentRoomId = roomId;
        instructorInput.value = instructorName;
        // load courses from backend or fallback
        fetchJSON('/instructor/api/courses.php')
            .then(list => populateSelect(courseSelect, list, 'id', 'name'))
            .catch(() => populateSelect(courseSelect, sample.courses, 'id', 'name'));

        // clear dependent selects
        populateSelect(sectionSelect, [], 'id', 'name');
        populateSelect(classSelect, [], 'id', 'subject');

        modal.classList.remove('hidden'); modal.classList.add('flex');
    }

    function closeModal() {
        modal.classList.add('hidden'); modal.classList.remove('flex');
        form.reset();
    }

    cancel.addEventListener('click', closeModal);
    modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });

    // populate helper
    function populateSelect(el, items, valKey = 'id', labelKey = 'name') {
        el.innerHTML = '';
        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = '-- select --';
        el.appendChild(placeholder);
        (items || []).forEach(it => {
            const opt = document.createElement('option');
            opt.value = it[valKey];
            opt.textContent = it[labelKey];
            el.appendChild(opt);
        });
    }

    // when course changes -> load sections
    courseSelect.addEventListener('change', () => {
        const courseId = courseSelect.value;
        if (!courseId) { populateSelect(sectionSelect, [], 'id', 'name'); populateSelect(classSelect, [], 'id', 'subject'); return; }
        fetchJSON('/instructor/api/sections.php?course=' + encodeURIComponent(courseId))
            .then(list => populateSelect(sectionSelect, list, 'id', 'name'))
            .catch(() => populateSelect(sectionSelect, sample.sections[courseId] || [], 'id', 'name'));
    });

    // when section changes -> load classes
    sectionSelect.addEventListener('change', () => {
        const sectionId = sectionSelect.value;
        if (!sectionId) { populateSelect(classSelect, [], 'id', 'subject'); return; }
        fetchJSON('/instructor/api/classes.php?section=' + encodeURIComponent(sectionId))
            .then(list => populateSelect(classSelect, list, 'id', 'subject'))
            .catch(() => populateSelect(classSelect, sample.classes[sectionId] || [], 'id', 'subject'));
    });

    // submit check-in
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        if (!currentRoomId) return alert('No room selected.');
        const payload = {
            room_id: currentRoomId,
            instructor_id: instructorId,
            instructor_name: instructorName,
            course_id: courseSelect.value,
            section_id: sectionSelect.value,
            class_id: classSelect.value
        };
        if (!payload.course_id || !payload.section_id || !payload.class_id) return alert('Select course, section and class.');

        // POST to backend (replace URL)
        fetch('/instructor/api/checkin.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(payload)
        }).then(res => {
            if (res.ok) return res.json().catch(() => ({ success: true }));
            return Promise.reject();
        }).then(data => {
            // success message (adjust to your response format)
            alert((data && data.message) ? data.message : 'Checked in.');
            closeModal();
        }).catch(() => {
            // fallback: log and close for local testing
            console.log('Check-in payload', payload);
            alert('Checked in (local).');
            closeModal();
        });
    });

    // keep track of clicked room id — simple delegation
    const roomsList = document.getElementById('rooms-list');
    if (roomsList) {
        roomsList.addEventListener('click', (e) => {
            const li = e.target.closest('li[data-building-id], li[data-room-id]');
            // rooms in your markup use data-room-id; buildings list uses data-building-id.
            // prefer room item with data-room-id; if building clicked, clear currentRoomId.
            const roomLi = e.target.closest('li[data-room-id]');
            if (roomLi) {
                // when a room is clicked, show checkin button
                const rid = roomLi.getAttribute('data-room-id') || roomLi.dataset.roomId;
                currentRoomId = rid;
                // reveal the checkin button
                if (checkinBtn) checkinBtn.classList.remove('hidden');
            } else {
                // if building clicked, hide checkin until a room selected
                currentRoomId = null;
                if (checkinBtn) checkinBtn.classList.add('hidden');
            }
        });
    }

    // open modal when checkin button clicked
    if (checkinBtn) {
        checkinBtn.addEventListener('click', () => {
            if (!currentRoomId) return alert('Select a room first.');
            openModalFor(currentRoomId);
        });
    }

    // initial: hide checkin button until a room is clicked
    if (checkinBtn) checkinBtn.classList.add('hidden');
});