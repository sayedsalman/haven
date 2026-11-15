document.querySelectorAll('.mood-grid .mood-day').forEach(el => {
    el.addEventListener('click', function() {
        // Could expand to show details
        alert('Mood for ' + this.title + ': ' + this.textContent);
    });
});