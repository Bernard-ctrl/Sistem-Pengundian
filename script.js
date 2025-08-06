// script.js
document.getElementById('voteForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);

    fetch('index.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        document.getElementById('result').textContent = data;
        form.reset();
    })
    .catch(() => {
        document.getElementById('result').textContent = 'Ralat semasa menghantar undian.';
    });
});