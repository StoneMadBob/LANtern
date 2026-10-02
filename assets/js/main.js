// -----------------------------
// Collapsible Panels
// -----------------------------
document.querySelectorAll('.dw-panel-toggle').forEach(btn => {
    btn.addEventListener('click', () => {
        btn.classList.toggle('open');
        btn.nextElementSibling.classList.toggle('open');
    });
});

// -----------------------------
// Search / Filter
// -----------------------------
const search = document.getElementById('dw-search');

if (search) {
    search.addEventListener('input', () => {
        const term = search.value.toLowerCase();

        document.querySelectorAll('.dw-link').forEach(link => {
            const text = link.textContent.toLowerCase();
            link.style.display = text.includes(term) ? 'block' : 'none';
        });
    });
}
