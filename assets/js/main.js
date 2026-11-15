// GSAP Hero animation
gsap.from(".hero-title", { y: 80, opacity: 0, duration: 1, ease: "power3.out" });
gsap.from(".lead", { y: 40, opacity: 0, duration: 1, delay: 0.3 });
gsap.from(".btn", { scale: 0.8, opacity: 0, duration: 0.6, delay: 0.6, ease: "back.out(1.7)" });

// Dark mode toggle (add button in header)
document.addEventListener('DOMContentLoaded', function() {
    const darkToggle = document.getElementById('dark-toggle');
    if (darkToggle) {
        darkToggle.addEventListener('click', function() {
            document.body.classList.toggle('dark-mode');
            localStorage.setItem('darkMode', document.body.classList.contains('dark-mode'));
        });
        if (localStorage.getItem('darkMode') === 'true') document.body.classList.add('dark-mode');
    }
});

// GSAP animations for cards on scroll
document.addEventListener('DOMContentLoaded', function() {
    gsap.utils.toArray('.glass-card').forEach(card => {
        gsap.from(card, {
            scrollTrigger: { trigger: card, toggleActions: 'play none none none' },
            y: 30,
            opacity: 0,
            duration: 0.8,
            stagger: 0.1
        });
    });

    // Dark mode toggle
    const toggle = document.createElement('button');
    toggle.className = 'btn btn-outline-secondary btn-sm position-fixed bottom-0 end-0 m-3';
    toggle.innerHTML = '<i class="bi bi-moon"></i>';
    toggle.id = 'dark-toggle';
    document.body.appendChild(toggle);
    toggle.addEventListener('click', function() {
        document.body.classList.toggle('dark-mode');
        localStorage.setItem('darkMode', document.body.classList.contains('dark-mode'));
    });
    if (localStorage.getItem('darkMode') === 'true') document.body.classList.add('dark-mode');
});