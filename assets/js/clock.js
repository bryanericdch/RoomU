document.addEventListener('DOMContentLoaded', function () {
    function updateClock() {
        const now = new Date().toLocaleString('en-US', {
            timeZone: 'Asia/Manila',
            hour: 'numeric',
            minute: '2-digit',
            hour12: true
        });
        document.getElementById('current-time').textContent = now;
    }
    updateClock();
    setInterval(updateClock, 1000);
});