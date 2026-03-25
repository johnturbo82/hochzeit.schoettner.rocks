(function () {
    const API = '/getraenke/api.php';
    const list = document.getElementById('drink-list');
    const input = document.getElementById('drink-input');
    const submit = document.getElementById('drink-submit');
    const feedback = document.getElementById('drink-feedback');
    let currentItems = [];

    const VOTED_KEY = 'drinks_voted';

    function getVoted() {
        try { return JSON.parse(localStorage.getItem(VOTED_KEY)) || []; }
        catch { return []; }
    }

    function markVoted(id) {
        const voted = getVoted();
        if (!voted.includes(id)) {
            voted.push(id);
            localStorage.setItem(VOTED_KEY, JSON.stringify(voted));
        }
    }

    function hasVoted(id) {
        return getVoted().includes(id);
    }

    function normalizeDrinkName(name) {
        return name.trim().toLowerCase().replace(/\s+/g, ' ');
    }

    function escapeHtml(str) {
        return str
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function renderList(items) {
        currentItems = items || [];
        if (!items || items.length === 0) {
            list.innerHTML = '<li style="list-style:none; color: var(--shadow-color); font-style: italic;">Noch keine Wünsche – sei der Erste!</li>';
            return;
        }
        list.innerHTML = items.map(item => {
            const already = hasVoted(item.id);
            return `
            <li data-id="${escapeHtml(item.id)}">
                <span class="drink-name">${escapeHtml(item.name)}</span>
                <button class="btn-vote${already ? ' btn-voted' : ''}"
                    aria-label="+1 für ${escapeHtml(item.name)}"
                    ${already ? 'disabled title="Du hast hier bereits abgestimmt"' : ''}>
                    ${already ? '✓' : '+1'}
                </button>
                <span class="drink-votes">${item.votes} Stimme${item.votes !== 1 ? 'n' : ''}</span>
            </li>`;
        }).join('');

        list.querySelectorAll('.btn-vote:not([disabled])').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = btn.closest('li').dataset.id;
                vote(id, btn);
            });
        });
    }

    function showFeedback(msg, isError) {
        feedback.textContent = msg;
        feedback.style.color = isError ? '#c0392b' : 'var(--highlight-color)';
        feedback.style.display = 'block';
        setTimeout(() => { feedback.style.display = 'none'; }, 3500);
    }

    async function loadList() {
        try {
            const res = await fetch(API);
            const data = await res.json();
            renderList(data);
        } catch {
            list.innerHTML = '<li style="list-style:none; color:#c0392b;">Fehler beim Laden der Wünsche.</li>';
        }
    }

    async function vote(id, btn) {
        btn.disabled = true;
        try {
            const res = await fetch(API, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'vote', id })
            });
            if (res.ok) {
                markVoted(id);
                await loadList();
            } else {
                showFeedback('Abstimmung fehlgeschlagen.', true);
                btn.disabled = false;
            }
        } catch {
            showFeedback('Netzwerkfehler.', true);
            btn.disabled = false;
        }
    }

    submit.addEventListener('click', async () => {
        const name = input.value.trim();
        if (!name) { showFeedback('Bitte einen Getränkenamen eingeben.', true); return; }

        const normalizedName = normalizeDrinkName(name);
        const existingItem = currentItems.find(item => normalizeDrinkName(item.name) === normalizedName);
        if (existingItem && hasVoted(existingItem.id)) {
            showFeedback('Für dieses Getränk hast du bereits abgestimmt.', true);
            return;
        }

        submit.disabled = true;
        try {
            const res = await fetch(API, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'add', name })
            });
            const data = await res.json();
            if (res.ok) {
                input.value = '';
                markVoted(data.id);
                if (data.status === 'exists') {
                    showFeedback('Getränk bereits vorhanden – deine Stimme wurde gezählt!', false);
                } else {
                    showFeedback('Wunsch eingetragen!', false);
                }
                await loadList();
            } else {
                showFeedback(data.error || 'Fehler beim Einreichen.', true);
            }
        } catch {
            showFeedback('Netzwerkfehler.', true);
        } finally {
            submit.disabled = false;
        }
    });

    input.addEventListener('keydown', e => {
        if (e.key === 'Enter') submit.click();
    });

    loadList();
})();
