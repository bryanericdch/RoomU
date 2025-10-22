
document.addEventListener('DOMContentLoaded', () => {
    // DOM refs
    const coursesEl = document.getElementById('courses');
    const sectionsEl = document.getElementById('sections');
    const classesEl = document.getElementById('classes');

    const addCourseBtn = document.getElementById('add-course');
    const addSectionBtn = document.getElementById('add-section');
    const addClassBtn = document.getElementById('add-class');

    // class modal (already present)
    const modal = document.getElementById('modal');
    const modalForm = document.getElementById('modal-form');
    const modalTitle = document.getElementById('modal-title');
    const subjectInput = document.getElementById('subject');
    const startInput = document.getElementById('start_time');
    const endInput = document.getElementById('end_time');
    const modalCancel = document.getElementById('modal-cancel');

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

    // Simple sample data (replace with PHP-backed calls later)
    const data = {
        courses: [
            {
                id: 1, name: 'BSIT', sections: [
                    {
                        id: 11, name: 'BSIT2-05', classes: [
                            { id: 111, subject: 'ITE-300', start_time: '10:30', end_time: '12:00' }
                        ]
                    }
                ]
            }
        ]
    };

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

    // Renders
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
            li.innerHTML = `<span class="cursor-pointer">${esc(c.name)}</span>
                <div class="flex gap-2">
                    <button data-id="${c.id}" data-act="edit" class="text-sm cursor-pointer">Edit</button>
                    <button data-id="${c.id}" data-act="del" class="text-sm text-red-500 cursor-pointer">Del</button>
                </div>`;
            // select course
            li.querySelector('span').addEventListener('click', () => {
                selectedCourse = c.id;
                selectedSection = null;
                addSectionBtn.disabled = false;
                addClassBtn.disabled = true;
                renderAll();
            });
            // edit course -> open course modal
            li.querySelector('[data-act="edit"]').addEventListener('click', (e) => {
                e.stopPropagation();
                openCourseModal('edit', c.id);
            });
            // delete
            li.querySelector('[data-act="del"]').addEventListener('click', (e) => {
                e.stopPropagation();
                if (!confirm(`Delete course "${c.name}" and all its sections/classes?`)) return;
                const idx = data.courses.findIndex(x => x.id === c.id);
                if (idx !== -1) data.courses.splice(idx, 1);
                if (selectedCourse === c.id) { selectedCourse = null; selectedSection = null; addSectionBtn.disabled = true; addClassBtn.disabled = true; }
                renderAll();
            });
            coursesEl.appendChild(li);
        });
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
            li.className = 'p-2 border rounded flex justify-between items-center';
            if (selectedSection === s.id) li.classList.add('bg-roomu-green/10');
            li.innerHTML = `<span class="cursor-pointer">${esc(s.name)}</span>
                <div class="flex gap-2">
                    <button data-id="${s.id}" data-act="edit" class="text-sm cursor-pointer">Edit</button>
                    <button data-id="${s.id}" data-act="del" class="text-sm text-red-500 cursor-pointer">Del</button>
                </div>`;
            li.querySelector('span').addEventListener('click', () => {
                selectedSection = s.id;
                addClassBtn.disabled = false;
                renderAll();
            });
            // edit section -> open section modal
            li.querySelector('[data-act="edit"]').addEventListener('click', (e) => {
                e.stopPropagation();
                openSectionModal('edit', s.id);
            });
            // delete
            li.querySelector('[data-act="del"]').addEventListener('click', (e) => {
                e.stopPropagation();
                if (!confirm(`Delete section "${s.name}" and its classes?`)) return;
                course.sections = course.sections.filter(x => x.id !== s.id);
                if (selectedSection === s.id) { selectedSection = null; addClassBtn.disabled = true; }
                renderAll();
            });
            sectionsEl.appendChild(li);
        });
    }

    function formatTime12(t) {
        if (!t) return '';
        const parts = String(t).split(':');
        if (parts.length < 2) return t;
        let hh = parseInt(parts[0], 10);
        const mm = parts[1];
        const ampm = hh >= 12 ? 'PM' : 'AM';
        hh = hh % 12;
        if (hh === 0) hh = 12;
        return `${hh}:${mm} ${ampm}`;
    }

    function renderClasses() {
        classesEl.innerHTML = '';
        const course = data.courses.find(c => c.id === selectedCourse);
        const section = course && course.sections.find(s => s.id === selectedSection);
        if (!section) {
            classesEl.innerHTML = '<li class="p-2 text-gray-500 italic">Select a section</li>';
            return;
        }
        if (!section.classes.length) {
            classesEl.innerHTML = '<li class="p-2 text-gray-500 italic">No classes</li>';
            return;
        }
        section.classes.forEach(cl => {
            const li = document.createElement('li');
            li.className = 'p-2 border rounded flex justify-between items-center';
            const times = (cl.start_time && cl.end_time) ? `${formatTime12(cl.start_time)} - ${formatTime12(cl.end_time)}` : '';
            li.innerHTML = `<div>
                    <div class="font-medium">${esc(cl.subject)}</div>
                    <div class="text-sm text-gray-600">${esc(esc(times))}</div>
                </div>
                <div class="flex gap-2">
                    <button data-id="${cl.id}" data-act="edit" class="text-sm cursor-pointer">Edit</button>
                    <button data-id="${cl.id}" data-act="del" class="text-sm text-red-500 cursor-pointer">Del</button>
                </div>`;
            li.querySelector('[data-act="edit"]').addEventListener('click', () => openClassModal('edit', cl.id));
            li.querySelector('[data-act="del"]').addEventListener('click', () => {
                if (!confirm('Delete this class?')) return;
                section.classes = section.classes.filter(x => x.id !== cl.id);
                renderAll();
            });
            classesEl.appendChild(li);
        });
    }

    function renderAll() {
        renderCourses();
        renderSections();
        renderClasses();
    }

    // Actions - Course
    addCourseBtn.addEventListener('click', () => openCourseModal('add'));

    function openCourseModal(mode, courseId = null) {
        courseMode = mode;
        editingCourseId = courseId;
        modalCourseTitle.textContent = mode === 'add' ? 'Add Course' : 'Edit Course';
        if (mode === 'edit' && courseId != null) {
            const course = data.courses.find(c => c.id === courseId);
            courseNameInput.value = course ? course.name : '';
        } else {
            courseNameInput.value = '';
        }
        modalCourse.classList.remove('hidden');
        modalCourse.classList.add('flex');
        courseNameInput.focus();
    }

    modalCourseCancel.addEventListener('click', () => {
        modalCourse.classList.add('hidden');
        modalCourse.classList.remove('flex');
        courseMode = null;
        editingCourseId = null;
        modalCourseForm.reset();
    });

    modalCourseForm.addEventListener('submit', (e) => {
        e.preventDefault();
        const name = courseNameInput.value.trim();
        if (!name) return alert('Course name required');
        if (courseMode === 'add') {
            data.courses.push({ id: genId(), name, sections: [] });
        } else if (courseMode === 'edit' && editingCourseId != null) {
            const course = data.courses.find(c => c.id === editingCourseId);
            if (course) course.name = name;
        }
        modalCourse.classList.add('hidden');
        modalCourse.classList.remove('flex');
        renderAll();
    });

    // Actions - Section
    addSectionBtn.addEventListener('click', () => {
        if (!selectedCourse) return alert('Select a course first.');
        openSectionModal('add');
    });

    function openSectionModal(mode, sectionId = null) {
        sectionMode = mode;
        editingSectionId = sectionId;
        modalSectionTitle.textContent = mode === 'add' ? 'Add Section' : 'Edit Section';
        if (mode === 'edit' && sectionId != null) {
            const course = data.courses.find(c => c.id === selectedCourse);
            const sec = course && course.sections.find(s => s.id === sectionId);
            sectionNameInput.value = sec ? sec.name : '';
        } else {
            sectionNameInput.value = '';
        }
        modalSection.classList.remove('hidden');
        modalSection.classList.add('flex');
        sectionNameInput.focus();
    }

    modalSectionCancel.addEventListener('click', () => {
        modalSection.classList.add('hidden');
        modalSection.classList.remove('flex');
        sectionMode = null;
        editingSectionId = null;
        modalSectionForm.reset();
    });

    modalSectionForm.addEventListener('submit', (e) => {
        e.preventDefault();
        const name = sectionNameInput.value.trim();
        if (!name) return alert('Section name required');
        const course = data.courses.find(c => c.id === selectedCourse);
        if (!course) return alert('No course selected');
        if (sectionMode === 'add') {
            course.sections.push({ id: genId(), name, classes: [] });
        } else if (sectionMode === 'edit' && editingSectionId != null) {
            const sec = course.sections.find(s => s.id === editingSectionId);
            if (sec) sec.name = name;
        }
        modalSection.classList.add('hidden');
        modalSection.classList.remove('flex');
        renderAll();
    });

    // Classes modal logic (existing)
    addClassBtn.addEventListener('click', () => {
        if (!selectedSection) return alert('Select a section first.');
        openClassModal('add');
    });

    function openClassModal(mode, classId = null) {
        modalMode = mode;
        editingClassId = classId;
        modalTitle.textContent = mode === 'add' ? 'Add Class' : 'Edit Class';

        if (mode === 'edit' && classId != null) {
            const course = data.courses.find(c => c.id === selectedCourse);
            const section = course && course.sections.find(s => s.id === selectedSection);
            const cls = section && section.classes.find(x => x.id === classId);
            subjectInput.value = cls ? cls.subject : '';
            startInput.value = cls ? (cls.start_time || '') : '';
            endInput.value = cls ? (cls.end_time || '') : '';
        } else {
            subjectInput.value = '';
            startInput.value = '';
            endInput.value = '';
        }

        modal.classList.remove('hidden');
        modal.classList.add('flex');
        subjectInput.focus();
    }

    function closeClassModal() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        modalMode = null;
        editingClassId = null;
        modalForm.reset();
    }

    modalCancel.addEventListener('click', closeClassModal);
    modal.addEventListener('click', (e) => { if (e.target === modal) closeClassModal(); });

    modalForm.addEventListener('submit', (e) => {
        e.preventDefault();
        const subject = subjectInput.value.trim();
        const start_time = startInput.value;
        const end_time = endInput.value;
        if (!subject || !start_time || !end_time) return alert('Both fields are required.');
        const course = data.courses.find(c => c.id === selectedCourse);
        const section = course && course.sections.find(s => s.id === selectedSection);
        if (!section) return alert('Section not found.');

        if (modalMode === 'add') {
            section.classes.push({ id: genId(), subject, start_time, end_time });
        } else if (modalMode === 'edit' && editingClassId != null) {
            const cls = section.classes.find(x => x.id === editingClassId);
            if (cls) { cls.subject = subject; cls.start_time = start_time; cls.end_time = end_time; }
        }
        closeClassModal();
        renderAll();
    });

    // initial state
    addSectionBtn.disabled = true;
    addClassBtn.disabled = true;
    renderAll();
});
