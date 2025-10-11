document.addEventListener("DOMContentLoaded", () => {
    const departments = document.querySelectorAll("[data-action='select-department']");
    const instructorList = document.getElementById("instructors-list");

    const renderInstructors = (departmentId) => {
        instructorList.innerHTML = "";

        const instructors = sampleData[departmentId] || [];
        if (instructors.length === 0) {
            instructorList.innerHTML = `<li class="text-gray-500 text-center py-3">No instructors found</li>`;
            return;
        }

        instructors.forEach(inst => {
            const li = document.createElement("li");
            li.className = "flex justify-between items-center bg-gray-50 rounded-md p-3 hover:bg-hover-roomu-green transition duration-200 ease-in-out";
            li.innerHTML = `
                <div class="w-1/4 font-medium">${inst.name || "-"}</div>
                <div class="w-1/5">${inst.room || "-"}</div>
                <div class="w-1/4">${inst.class || "-"}</div>
                <div class="w-1/4 text-center">${inst.schedule || "-"}</div>
            `;
            instructorList.appendChild(li);
        });
    };

    departments.forEach(dep => {
        dep.addEventListener("click", () => {
            departments.forEach(d => d.classList.remove("bg-roomu-green", "text-roomu-white"));
            dep.classList.add("bg-roomu-green", "text-roomu-white");

            const depId = dep.dataset.departmentId;
            renderInstructors(depId);
        });
    });

    renderInstructors(1);
    departments[0].classList.add("bg-roomu-green", "text-roomu-white");

});