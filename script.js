document.addEventListener('DOMContentLoaded', function () {
    const voteForm = document.getElementById('voteForm');
    if (!voteForm) return;

    // Fetch positions and candidates from server
    let jawatanList = [];
    let calonList = [];
    
    // Load jawatan (positions)
    fetch('index.php?action=get_jawatan', { credentials: 'same-origin' })
        .then(r => r.json())
        .then(data => {
            jawatanList = data;
            populatePositions();
        })
        .catch(() => console.error('Failed to load jawatan'));
    
    // Load calon (candidates)
    fetch('index.php?action=get_calon', { credentials: 'same-origin' })
        .then(r => r.json())
        .then(data => {
            calonList = data;
        })
        .catch(() => console.error('Failed to load calon'));
    
    const positionEl = document.getElementById('position');
    const studentEl = document.getElementById('student');
    
    function populatePositions() {
        if (!positionEl) return;
        positionEl.innerHTML = '<option value="">-- Pilih Jawatan --</option>';
        jawatanList.forEach(j => {
            const opt = document.createElement('option');
            opt.value = j.id_Jawatan;
            opt.textContent = j.nama_Jawatan;
            positionEl.appendChild(opt);
        });
    }
    
    function populateCandidates() {
        if (!studentEl) return;
        studentEl.innerHTML = '<option value="">-- Pilih Calon --</option>';
        calonList.forEach(c => {
            const opt = document.createElement('option');
            opt.value = c.id_Calon;
            opt.textContent = c.nama_Calon;
            studentEl.appendChild(opt);
        });
    }
    
    if (positionEl) {
        positionEl.addEventListener('change', function() {
            populateCandidates();
        });
    }

    const voteBtn = document.getElementById('voteSubmit');
    if (voteBtn) {
        voteBtn.addEventListener('click', function() {
            const form = voteForm;
            const formData = new FormData();
            
            // Get selected values
            const id_jawatan = positionEl ? positionEl.value : '';
            const id_calon = studentEl ? studentEl.value : '';
            
            if (!id_jawatan || !id_calon) {
                const resEl = document.getElementById('result');
                if (resEl) {
                    resEl.textContent = 'Sila pilih jawatan dan calon.';
                    resEl.style.color = '#c0392b';
                    resEl.style.fontWeight = '700';
                }
                return;
            }
            
            formData.append('id_jawatan', id_jawatan);
            formData.append('id_calon', id_calon);

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