document.addEventListener('DOMContentLoaded', () => {
    const buttons = document.querySelectorAll('.dw-filter-btn');
    const cards = document.querySelectorAll('.dw-device-card');

    if (!buttons.length || !cards.length) {
        return;
    }

    const applyFilter = (filter) => {
        cards.forEach((card) => {
            const visible = filter === 'all' || card.dataset.status === filter;
            card.style.display = visible ? '' : 'none';
        });
    };

    buttons.forEach((button) => {
        button.addEventListener('click', () => {
            buttons.forEach((btn) => btn.classList.toggle('active', btn === button));
            applyFilter(button.dataset.filter);
        });
    });
});
