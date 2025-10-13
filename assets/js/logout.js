document.addEventListener('DOMContentLoaded', () => {
    const profileBtn = document.getElementById('profile-btn');
    const logoutBar = document.getElementById('logout-bar');
    const closeLogout = document.getElementById('close-logout');
    const overlay = document.getElementById('sidebar-overlay') || (() => {
        const o = document.createElement('div');
        o.id = 'sidebar-overlay';
        o.className = 'fixed inset-0 bg-roomu-black opacity-25 hidden z-40';
        document.body.appendChild(o);
        return o;
    })();

    const openChangeBtn = document.getElementById('open-change-password');
    const changeForm = document.getElementById('change-password-form');
    const cancelChange = document.getElementById('cancel-change');
    const logoutBtn = document.getElementById('logout-btn');
    const logoutMsg = document.getElementById('logout-msg');

    function openSidebar() {
        logoutBar.classList.remove('translate-x-full');
        overlay.classList.remove('hidden');
    }

    function closeSidebar() {
        logoutBar.classList.add('translate-x-full');
        overlay.classList.add('hidden');
        changeForm.classList.add('hidden');
        logoutMsg.classList.add('hidden');
    }

    profileBtn.addEventListener('click', openSidebar);
    closeLogout.addEventListener('click', closeSidebar);
    overlay.addEventListener('click', closeSidebar);

    openChangeBtn.addEventListener('click', () => {
        changeForm.classList.toggle('hidden');
    });
    cancelChange.addEventListener('click', () => changeForm.classList.add('hidden'));

    // change password (frontend -> backend)  // YOU CAN CHANGE THIS PART DEPENDS ON HOW WILL YOU DO THE BACKEND
    changeForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        logoutMsg.classList.add('hidden');
        const fd = new FormData(changeForm);
        const current_password = fd.get('current_password');
        const new_password = fd.get('new_password');
        const confirm_password = fd.get('confirm_password');

        if (new_password !== confirm_password) {
            logoutMsg.textContent = 'New password and confirmation do not match.';
            logoutMsg.classList.remove('hidden');
            return;
        }
        try {
            const res = await fetch('/RoomU/api/auth.php?action=change_password', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ current_password, new_password })
            });
            const json = await res.json();
            if (json.success) {
                logoutMsg.textContent = 'Password changed successfully.';
                logoutMsg.classList.remove('hidden');
                logoutMsg.classList.remove('text-red-600');
                logoutMsg.classList.add('text-green-600');
                changeForm.reset();
                setTimeout(closeSidebar, 1200);
            } else {
                logoutMsg.textContent = json.message || 'Failed to change password.';
                logoutMsg.classList.remove('hidden');
            }
        } catch (err) {
            logoutMsg.textContent = 'Network error.';
            logoutMsg.classList.remove('hidden');
        }
    });

    // Logout handler
    logoutBtn.addEventListener('click', async () => {
        try {
            //const res = await fetch('/RoomU/api/auth.php?action=logout', { method: 'POST' });  // YOU CAN CHANGE THIS
            window.location.href = '/login.html';
        } catch (err) {
            logoutMsg.textContent = 'Logout failed.';
            logoutMsg.classList.remove('hidden');
        }
    });
});