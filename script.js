document.addEventListener('DOMContentLoaded', function () {
    const voteForm = document.getElementById('voteForm');
    if (!voteForm) return; // nothing to do if form not present

    const voteBtn = document.getElementById('voteSubmit');
    if (voteBtn) {
        voteBtn.addEventListener('click', function() {
            const form = voteForm;
            const formData = new FormData(form);

            fetch('index.php', {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                const resEl = document.getElementById('result');
                const voteBtn = document.getElementById('voteSubmit');
                if (resEl) {
                    resEl.textContent = data;
                    const lower = (data || '').toLowerCase();
                    const isSuccess = /undian untuk|telah direkodkan|jumlah undian/.test(lower);
                    resEl.style.color = isSuccess ? '#27ae60' : '#c0392b';
                    // Make message more visible
                    resEl.style.fontWeight = '700';
                    resEl.style.marginTop = '12px';
                }
                // Disable button briefly to avoid accidental duplicate votes
                if (voteBtn) {
                    voteBtn.disabled = true;
                    voteBtn.textContent = 'Terima kasih';
                    setTimeout(() => {
                        voteBtn.disabled = false;
                        voteBtn.textContent = 'Undi';
                    }, 5000);
                }
                form.reset();
            })
            .catch(() => {
                const resEl = document.getElementById('result');
                if (resEl) resEl.textContent = 'Ralat semasa menghantar undian.';
            });
        });
    }
});