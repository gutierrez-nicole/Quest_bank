

document.addEventListener('DOMContentLoaded', () => {
    
    const alerts = document.querySelectorAll('.animate-fadeIn');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.5s ease';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });
});

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
}

function togglePopover(popoverId) {
    const popover = document.getElementById(popoverId);
    if (popover) {
        popover.classList.toggle('hidden');
    }
}
