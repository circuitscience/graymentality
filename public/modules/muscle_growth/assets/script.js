// assets/script.js

document.addEventListener('DOMContentLoaded', () => {
    // Accordion logic
    document.querySelectorAll('.accordion-header').forEach(header => {
        header.addEventListener('click', () => {
            const item = header.closest('.accordion-item');
            const open = item.classList.contains('open');

            // Close others
            document.querySelectorAll('.accordion-item.open').forEach(it => {
                if (it !== item) it.classList.remove('open');
            });

            // Toggle current
            if (!open) {
                item.classList.add('open');
            } else {
                item.classList.remove('open');
            }
        });
    });

    // Scroll to check-in
    const scrollBtn = document.getElementById('scrollToCheckin');
    if (scrollBtn) {
        scrollBtn.addEventListener('click', () => {
            const section = document.getElementById('checkin');
            if (section) {
                section.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    }

    // Form handling
    const form = document.getElementById('checkinForm');
    const msgEl = document.getElementById('formMessage');

    if (form && msgEl) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            msgEl.textContent = '';
            msgEl.className = 'form-message';

            const formData = new FormData(form);

            try {
                const res = await fetch('save_log.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await res.json().catch(() => null);

                if (!res.ok || !data || !data.ok) {
                    throw new Error(data && data.message ? data.message : 'Error saving check-in');
                }

                msgEl.textContent = data.message || 'Saved!';
                msgEl.classList.add('success');

                // Optional: simple reset
                form.reset();

                // Optional: reload to show new history
                setTimeout(() => {
                    window.location.reload();
                }, 800);

            } catch (err) {
                console.error(err);
                msgEl.textContent = err.message || 'Something went wrong';
                msgEl.classList.add('error');
            }
        });
    }
});
