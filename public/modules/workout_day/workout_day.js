/**
 * xFit Workout Day Module
 * Handles phase transitions and hard rules
 */

document.querySelectorAll('[data-next]').forEach(btn => {
    btn.addEventListener('click', () => {
        const current = btn.closest('.phase');
        const nextId = btn.dataset.next;

        current.classList.remove('active');

        if (nextId !== 'done') {
            document.getElementById(nextId).classList.add('active');
        }

        if (nextId === 'phase-cut') {
            stopAllAudio();
        }
    });
});

function stopAllAudio() {
    document.querySelectorAll('audio').forEach(a => {
        a.pause();
        a.currentTime = 0;
    });
}
