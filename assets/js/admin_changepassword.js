document.addEventListener('DOMContentLoaded', () => {
    const openChangeBtn = document.getElementById('open-change-password');
    const changeForm = document.getElementById('change-password-form');
    const cancelChangeBtn = document.getElementById('cancel-change');

    // Create or reuse the message container
    let changeMsg = document.getElementById('change-password-msg');
    if (!changeMsg) {
        changeMsg = document.createElement('div');
        changeMsg.id = 'change-password-msg';
        changeMsg.className = 'text-sm mt-2';
        changeForm.parentNode.insertBefore(changeMsg, changeForm.nextSibling);
    }

    // Open form
    openChangeBtn.addEventListener('click', () => {
        changeForm.classList.remove('hidden');
        changeMsg.textContent = '';
    });

    // Cancel and reset form
    cancelChangeBtn.addEventListener('click', () => {
        changeForm.classList.add('hidden');
        changeForm.reset();
        changeMsg.textContent = '';
    });

    // Handle submit
    changeForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        const current = document.getElementById('current_password').value.trim();
        const newPass = document.getElementById('new_password').value.trim();
        const confirm = document.getElementById('confirm_password').value.trim();

        if (!current || !newPass || !confirm) {
            changeMsg.style.color = 'red';
            changeMsg.textContent = 'All fields are required.';
            return;
        }

        if (newPass !== confirm) {
            changeMsg.style.color = 'red';
            changeMsg.textContent = 'Passwords do not match.';
            return;
        }

        const formData = new FormData();
        formData.append('mode', 'change_password');
        formData.append('current_password', current);
        formData.append('new_password', newPass);
        formData.append('confirm_password', confirm);

        try {
            const response = await fetch('/admin/admin_management.php', {
                method: 'POST',
                body: formData
            });

            if (!response.ok) throw new Error(`HTTP error ${response.status}`);

            const result = await response.json();

            if (result.success) {
                changeMsg.style.color = 'green';
                changeMsg.textContent = result.message || 'Password changed successfully!';
                changeForm.reset();

                // Auto-hide after success
                setTimeout(() => {
                    changeForm.classList.add('hidden');
                    changeMsg.textContent = '';
                }, 2000);
            } else {
                changeMsg.style.color = 'red';
                changeMsg.textContent = result.message || 'Failed to change password.';
            }

        } catch (err) {
            console.error('Password change error:', err);
            changeMsg.style.color = 'red';
            changeMsg.textContent = 'An error occurred. Please try again.';
        }
    });
});
