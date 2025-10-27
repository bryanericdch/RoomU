document.addEventListener('DOMContentLoaded', () => {
    // DOM refs
    const coursesEl = document.getElementById('courses');
    const sectionsEl = document.getElementById('sections');
    const classesEl = document.getElementById('classes');

    const addCourseBtn = document.getElementById('add-course');
    const addSectionBtn = document.getElementById('add-section');
    const addClassBtn = document.getElementById('add-class');

    // class modal
    const modal = document.getElementById('modal');
    const modalForm = document.getElementById('modal-form');
    const modalTitle = document.getElementById('modal-title');
    const subjectInput = document.getElementById('subject_code');
    const startInput = document.getElementById('start_time');
    const endInput = document.getElementById('end_time');
    const modalCancel = document.getElementById('modal-cancel');
    const roomSelect = document.getElementById('room_id');
    const classesList = document.getElementById('classes');
    const delForm = document.createElement('form');

    // course modal
    const modalCourse = document.getElementById('modal-course');
    const modalCourseForm = document.getElementById('modal-course-form');
    const modalCourseTitle = document.getElementById('modal-course-title');
    const courseNameInput = document.getElementById('course-name');
    const modalCourseCancel = document.getElementById('modal-course-cancel');

    // section modal
    const modalSection = document.getElementById('modal-section');
    const modalSectionForm = document.getElementById('modal-section-form');
    const modalSectionTitle = document.getElementById('modal-section-title');
    const sectionNameInput = document.getElementById('section-name');
    const modalSectionCancel = document.getElementById('modal-section-cancel');

    // Data storage
    const data = { courses: [] };

    let selectedCourse = null;
    let selectedSection = null;

    // modal contexts
    let courseMode = null;     // 'add' | 'edit'
    let editingCourseId = null;

    let sectionMode = null;    // 'add' | 'edit'
    let editingSectionId = null;

    // class modal contexts
    let modalMode = null; // 'add' | 'edit'
    let editingClassId = null;

    // helpers
    let nextId = 200;
    const genId = () => nextId++;
    const esc = s => (s == null) ? '' : String(s);

    // -------------------------
    // RENDER FUNCTIONS
    // -------------------------
    function renderCourses() {
        coursesEl.innerHTML = '';
        if (!data.courses.length) {
            coursesEl.innerHTML = '<li class="p-2 text-gray-500 italic">No courses</li>';
            return;
        }

        data.courses.forEach(c => {
            const li = document.createElement('li');
            li.className = 'p-2 border rounded flex justify-between items-center';
            if (selectedCourse === c.id) li.classList.add('bg-roomu-green/10');
            li.innerHTML = `
            <span class="cursor-pointer">${esc(c.name)}</span>
            <div class="flex gap-2">
                <button data-id="${c.id}" data-act="edit" class="text-sm text-blue-500 cursor-pointer">Edit</button>
                <button data-id="${c.id}" data-act="del" class="text-sm text-red-500 cursor-pointer">Delete</button>
            </div>
        `;

            li.addEventListener('click', async (e) => {
                if (e.target.closest('button')) return;
                selectedCourse = c.id;
                selectedSection = null;
                addSectionBtn.disabled = false;
                addClassBtn.disabled = true;
                await loadSections(c.id);
                renderAll();
            });

            // edit course
            li.querySelector('[data-act="edit"]').addEventListener('click', (e) => {
                e.stopPropagation();
                openCourseModal('edit', c.id);
                const course = data.courses.find(x => x.id === c.id);
                if (course) courseNameInput.value = course.name;
            });

            // delete course
            li.querySelector('[data-act="del"]').addEventListener('click', async (e) => {
                e.stopPropagation();
                if (!confirm(`Delete course "${c.name}" and its sections/classes?`)) return;

                try {
                    const res = await fetch('/instructor/instructor_classes.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'delete_course',
                            course_id: c.id
                        })
                    });

                    const text = await res.text();
                    let dataRes;
                    try { dataRes = JSON.parse(text); }
                    catch (err) { console.error('Expected JSON, got:', text); return alert('Error parsing server response.'); }

                    if (dataRes.success) {
                        alert('Course deleted successfully.');
                        await loadCourses();
                    } else {
                        alert(dataRes.message || 'Failed to delete course.');
                    }
                } catch (err) {
                    console.error('Error deleting course:', err);
                    alert('Error connecting to server.');
                }
            });

            coursesEl.appendChild(li);
        });
        // Add scrolling to courses list
        coursesEl.style.maxHeight = '550px';
        coursesEl.style.overflowY = 'auto';
    }

    function renderSections() {
        sectionsEl.innerHTML = '';
        const course = data.courses.find(c => c.id === selectedCourse);

        if (!course) {
            sectionsEl.innerHTML = '<li class="p-2 text-gray-500 italic">Select a course</li>';
            return;
        }

        if (!course.sections.length) {
            sectionsEl.innerHTML = '<li class="p-2 text-gray-500 italic">No sections</li>';
            return;
        }

        course.sections.forEach(s => {
            const li = document.createElement('li');
            li.className = 'p-2 border rounded flex justify-between items-center cursor-pointer';
            if (selectedSection === s.id) li.classList.add('bg-roomu-green/10');

            li.innerHTML = `<span class="flex-1 cursor-pointer">${esc(s.name)}</span>
        <div class="flex gap-2">
            <button data-id="${s.id}" data-act="edit" class="text-sm text-blue-500 cursor-pointer">Edit</button>
            <button data-id="${s.id}" data-act="del" class="text-sm text-red-500 cursor-pointer">Delete</button>
        </div>`;

            li.addEventListener('click', async (e) => {
                if (e.target.closest('button')) return;
                selectedSection = s.id;
                addClassBtn.disabled = false;
                renderClasses();
                renderSections();
            });

            // Edit section
            li.querySelector('[data-act="edit"]').addEventListener('click', (e) => {
                e.stopPropagation();
                openSectionModal('edit', s.id);
                sectionNameInput.value = s.name;
            });

            // Delete section
            li.querySelector('[data-act="del"]').addEventListener('click', async (e) => {
                e.stopPropagation();
                if (!confirm(`Delete section "${s.name}" and its classes?`)) return;

                try {
                    const res = await fetch('/instructor/instructor_classes.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'delete_section',
                            section_id: s.id
                        })
                    });
                    const dataRes = await res.json();
                    if (dataRes.success) {
                        alert('Section deleted successfully.');
                        if (selectedSection === s.id) selectedSection = null;
                        await loadSections(selectedCourse);
                    } else {
                        alert(dataRes.message || 'Failed to delete section.');
                    }
                } catch (err) {
                    console.error('Error deleting section:', err);
                    alert('Error connecting to server.');
                }
            });

            sectionsEl.appendChild(li);
        });
        // Add scrolling
        sectionsEl.style.maxHeight = '550px';
        sectionsEl.style.overflowY = 'auto';
    }

    // Convert HH:MM:SS to 12-hour AM/PM format
    function formatTime(t) {
        if (!t) return '';
        const [h, m] = t.split(':');
        const hour = parseInt(h, 10);
        const ampm = hour >= 12 ? 'PM' : 'AM';
        const hour12 = hour % 12 || 12;
        return `${hour12}:${m} ${ampm}`;
    }

    function renderClasses() {
        classesEl.innerHTML = '';

        if (!selectedSection) {
            classesEl.innerHTML = '<li class="p-2 text-gray-500 italic">Select a section</li>';
            return;
        }

        const course = data.courses.find(c => c.id === selectedCourse);
        if (!course) return;

        const section = course.sections.find(s => s.id === selectedSection);
        if (!section) return;

        if (!section.classes || !section.classes.length) {
            classesEl.innerHTML = '<li class="p-2 text-gray-500 italic">No classes</li>';
            return;
        }

        section.classes.forEach(cls => {
            const li = document.createElement('li');
            li.className = 'p-2 border rounded flex justify-between items-center bg-white shadow-sm';
            li.innerHTML = `
            <div>
                <span>
                    ${esc(cls.name)}
                    ${cls.room_name ? ` - ${esc(cls.room_name)}` : ''} (${cls.status})
                </span>
                <div class="text-gray-500 text-sm mt-1">
                    ${formatTime(cls.schedule_start)} - ${formatTime(cls.schedule_end)}
                </div>
            </div>
            <div class="flex gap-2 items-center">
                
                <button data-act="del" class="text-sm text-red-500 cursor-pointer">Del</button>
            </div>
        `;




            // Delete class
            li.querySelector('[data-act="del"]').addEventListener('click', async (e) => {
                e.stopPropagation();
                if (!confirm(`Delete class "${cls.name}"?`)) return;

                try {
                    const res = await fetch('/instructor/instructor_classes.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'delete_class',
                            class_id: cls.class_id,
                            room_id: cls.room_id // send the room id to mark it available
                        })
                    });

                    const dataRes = await res.json();
                    if (dataRes.success) {
                        alert('Class deleted successfully.');
                        section.classes = section.classes.filter(c => c.class_id !== cls.class_id);
                        renderClasses();
                    } else {
                        alert(dataRes.message || 'Failed to delete class.');
                    }
                } catch (err) {
                    console.error(err);
                    alert('Error connecting to server.');
                }
            });

            classesEl.appendChild(li);
        });
        // Add scrolling
        classesEl.style.maxHeight = '550px';
        classesEl.style.overflowY = 'auto';
    }

    function renderAll() {
        renderCourses();
        renderSections();
        renderClasses();
    }

    // Course modal logic
    addCourseBtn.addEventListener('click', () => openCourseModal('add'));

    function openCourseModal(mode, courseId = null) {
        courseMode = mode;
        editingCourseId = courseId;
        modalCourseTitle.textContent = mode === 'add' ? 'Add Course' : 'Edit Course';
        courseNameInput.value = '';
        modalCourse.classList.remove('hidden');
        modalCourse.classList.add('flex');
        courseNameInput.focus();
    }

    modalCourseCancel.addEventListener('click', () => {
        modalCourse.classList.add('hidden');
        modalCourse.classList.remove('flex');
        modalCourseForm.reset();
    });

    modalCourseForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const name = courseNameInput.value.trim();
        if (!name) return alert('Course name required');

        try {
            let res;
            if (courseMode === 'add') {
                res = await fetch('/instructor/instructor_classes.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'add_course',
                        name
                    })
                });
            } else if (courseMode === 'edit' && editingCourseId) {
                res = await fetch('/instructor/instructor_classes.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'edit_course',
                        course_id: editingCourseId,
                        name
                    })
                });
            }

            const dataRes = await res.json();
            if (dataRes.success) {
                alert(courseMode === 'add' ? 'Course added successfully!' : 'Course updated successfully!');
                await loadCourses();
            } else {
                alert(dataRes.message || 'Failed to save course.');
            }
        } catch (err) {
            alert('Error saving course.');
            console.error(err);
        }

        modalCourse.classList.add('hidden');
        modalCourse.classList.remove('flex');
        modalCourseForm.reset();
    });

    // SECTION MODAL logic
    addSectionBtn.addEventListener('click', () => openSectionModal('add'));

    function openSectionModal(mode, sectionId = null) {
        sectionMode = mode;
        editingSectionId = sectionId;
        modalSectionTitle.textContent = mode === 'add' ? 'Add Section' : 'Edit Section';
        sectionNameInput.value = '';
        modalSection.classList.remove('hidden');
        modalSection.classList.add('flex');
        sectionNameInput.focus();
    }

    modalSectionCancel.addEventListener('click', () => {
        modalSection.classList.add('hidden');
        modalSection.classList.remove('flex');
        modalSectionForm.reset();
    });

    modalSectionForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const name = sectionNameInput.value.trim();
        if (!name) return alert('Section name required');
        if (!selectedCourse) return alert('Select a course first.');

        try {
            let res;
            if (sectionMode === 'add') {
                res = await fetch('/instructor/instructor_classes.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'add_section',
                        course_id: selectedCourse,
                        section_name: name
                    })
                });
            } else if (sectionMode === 'edit' && editingSectionId) {
                res = await fetch('/instructor/instructor_classes.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'edit_section',
                        section_id: editingSectionId,
                        section_name: name
                    })
                });
            }


            const dataRes = await res.json();
            if (dataRes.success) {
                alert(sectionMode === 'add' ? 'Section added successfully!' : 'Section updated successfully!');
                await loadSections(selectedCourse);
            } else {
                alert(dataRes.message || 'Failed to save section.');
            }
        } catch (err) {
            console.error('Error saving section:', err);
            alert('Error connecting to server.');
        }

        modalSection.classList.add('hidden');
        modalSection.classList.remove('flex');
        modalSectionForm.reset();
    });

    // load courses + sections
    async function loadCourses() {
        try {
            const res = await fetch('/instructor/instructor_classes.php?fetch=courses');
            const courses = await res.json();
            data.courses = courses.map(c => ({
                id: c.course_id,
                name: c.name,
                sections: []
            }));
            renderAll();
        } catch (err) {
            console.error('Failed to load courses:', err);
        }
    }

    async function loadSections(courseId) {
        try {
            const res = await fetch(`/instructor/instructor_classes.php?fetch=sections&course_id=${courseId}`);
            const sections = await res.json();
            const course = data.courses.find(c => c.id === courseId);
            if (course) {
                course.sections = sections.map(s => ({
                    id: s.section_id,
                    name: s.section_name,
                    classes: [] // initialize empty classes array
                }));
            }

            // Fetch classes for each section
            for (const section of course.sections) {
                const resClasses = await fetch(`/instructor/instructor_classes.php?fetch=classes&section_id=${section.id}`);
                const classes = await resClasses.json();
                section.classes = classes.map(cls => ({
                    class_id: cls.class_id,
                    subject_id: cls.subject_id,
                    name: cls.subject_code,
                    schedule_start: cls.schedule_start,
                    schedule_end: cls.schedule_end,
                    checkin_grace_minutes: cls.checkin_grace_minutes,
                    status: cls.status,
                    room_id: cls.room_id,
                    room_name: cls.room_name,
                    room_status: cls.status

                }));

            }

            renderAll();
        } catch (err) {
            console.error('Failed to load sections or classes:', err);
        }
    }

    // --- CLASSES MODAL LOGIC ---

    // Open class modal
    function openClassModal(mode, classId = null) {
        modalMode = mode;
        editingClassId = classId;

        modalTitle.textContent = mode === 'add' ? 'Add Class' : 'Edit Class';
        subjectInput.value = '';
        startInput.value = '';
        endInput.value = '';
        roomSelect.innerHTML = '<option value="">Loading rooms...</option>';

        //Load available rooms each time the modal opens
        loadAvailableRooms();

        modal.classList.remove('hidden');
        modal.classList.add('flex');
        subjectInput.focus();
    }


    // Close class modal
    function closeClassModal() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        modalForm.reset();
        modalMode = null;
        editingClassId = null;
    }

    // +Add Class button
    addClassBtn.addEventListener('click', () => {
        if (!selectedSection) return alert('Select a section first.');
        openClassModal('add');
    });

    // Cancel button in modal
    modalCancel.addEventListener('click', () => {
        closeClassModal();
    });
    async function loadAvailableRooms() {
        try {
            const res = await fetch('/instructor/instructor_classes.php?fetch=available_rooms');
            const rooms = await res.json();

            roomSelect.innerHTML = '';

            if (!rooms.length) {
                roomSelect.innerHTML = '<option value="">No rooms available</option>';
                return;
            }

            roomSelect.innerHTML = rooms.map(r => {
                const disabled = r.status === 'checkedin' ? 'disabled' : '';
                const displayStatus = r.status === 'checkedin' ? 'Checked In' : 'Available';
                return `<option value="${r.room_id}" ${disabled}>${r.room_name} (${displayStatus})</option>`;
            }).join('');
        } catch (err) {
            console.error('Error loading rooms:', err);
            roomSelect.innerHTML = '<option value="">Error loading rooms</option>';
        }
    }




    // Submit class form
    modalForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        const subject_code = subjectInput.value.trim();
        const schedule_start = startInput.value;
        const schedule_end = endInput.value;
        const checkin_grace_minutes = 5; // default value
        const room_id = roomSelect.value;

        if (!room_id) return alert('Please select a room.');


        if (!subject_code || !schedule_start || !schedule_end)
            return alert('All fields are required.');

        const course = data.courses.find(c => c.id === selectedCourse);
        const section = course && course.sections.find(s => s.id === selectedSection);
        if (!section) return alert('Section not found.');

        try {
            const body = new URLSearchParams();

            if (modalMode === 'add') {
                body.append('action', 'add_class');
                body.append('section_id', selectedSection);
                body.append('subject_code', subject_code);
                body.append('schedule_start', schedule_start);
                body.append('schedule_end', schedule_end);
                body.append('room_id', room_id);
                body.append('checkin_grace_minutes', checkin_grace_minutes);
            } else if (modalMode === 'edit' && editingClassId != null) {
                body.append('action', 'edit_class');
                body.append('class_id', editingClassId);
                body.append('subject_code', subject_code);
                body.append('schedule_start', schedule_start);
                body.append('schedule_end', schedule_end);
                body.append('checkin_grace_minutes', checkin_grace_minutes);
            } else {
                return alert('Invalid modal state.');
            }

            const res = await fetch('/instructor/instructor_classes.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body
            });

            const dataRes = await res.json();
            if (!dataRes.success)
                return alert(dataRes.message || 'Failed to save class.');

            closeClassModal();
            await loadSections(selectedCourse); // refresh section & classes
        } catch (err) {
            console.error('Error saving class:', err);
            alert('Error connecting to server.');
        }
    });

    // initial state
    addSectionBtn.disabled = true;
    addClassBtn.disabled = true;
    loadCourses();
    renderAll();
});
