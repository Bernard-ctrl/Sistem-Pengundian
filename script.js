document.addEventListener('DOMContentLoaded', function () {
    const voteForm = document.getElementById('voteForm');
    if (!voteForm) return; // nothing to do if form not present

    // Candidate mapping per position (example names)
    const candidates = {
        'Pengerusi': ['Ali', 'Aminah', 'Farid'],
        'Naib Pengerusi': ['Siti', 'Hassan'],
        'Setiausaha': ['Lina', 'Zulkifli'],
        'Bendahari': ['Hadi', 'Nurul']
    };
    const positionEl = document.getElementById('position');
    const studentEl = document.getElementById('student');
    function populateCandidates(pos) {
        if (!studentEl) return;
        studentEl.innerHTML = '<option value="">-- Pilih Calon --</option>';
        if (!pos || !candidates[pos]) return;
        candidates[pos].forEach(name => {
            const opt = document.createElement('option');
            opt.value = name;
            opt.textContent = name;
            studentEl.appendChild(opt);
        });
    }
    if (positionEl) {
        positionEl.addEventListener('change', function() {
            populateCandidates(positionEl.value);
        });
    }

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
                // Refresh server-side voted positions so UI can disable appropriately
                fetch('index.php?action=my_votes', { credentials: 'same-origin' })
                    .then(r => r.json())
                    .then(json => {
                        try {
                            // update global variable used by index.html
                            window.serverVotedPositions = Array.isArray(json) ? json : [];
                        } catch (e) {}
                        try { if (typeof updateVoteControls === 'function') updateVoteControls(); } catch (e) {}
                    })
                    .catch(() => {});
            })
            .catch(() => {
                const resEl = document.getElementById('result');
                if (resEl) resEl.textContent = 'Ralat semasa menghantar undian.';
            });
        });
    }
});