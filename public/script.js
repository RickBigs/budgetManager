// Funzione per mostrare/nascondere il loader
function showLoader(show) {
    document.getElementById('loader').style.display = show ? 'block' : 'none';
}
// Funzione per mostrare messaggi di errore
function showError(msg) {
    document.getElementById('errorMsg').innerText = msg || '';
}

// Funzione per caricare le spese
function loadExpenses(month) {
    showLoader(true);
    showError('');
    let url = 'api/expenses.php';
    if (month) {
        url += '?month=' + month;
    }
    fetch(url)
        .then(res => res.json())
        .then(data => {
            let total = 0;
            const tbody = document.querySelector('#expensesTable tbody');
            tbody.innerHTML = '';
            data.forEach(exp => {
                total += parseFloat(exp.amount);
                tbody.innerHTML += `<tr>
                    <td>${exp.expense_date}</td>
                    <td>${exp.category}</td>
                    <td>${exp.description}</td>
                    <td>${exp.amount} €</td>
                    <td><button onclick="deleteExpense(${exp.id})">Elimina</button> <button onclick='editExpense(${JSON.stringify(exp)})'>Modifica</button></td>
                </tr>`;
            });
            document.getElementById('total').innerText = total.toFixed(2);
        })
        .catch(() => showError('Errore nel caricamento delle spese.'))
        .finally(() => showLoader(false));
}

// Gestione filtro mese
const monthInput = document.getElementById('monthFilter');
monthInput.addEventListener('change', function() {
    const val = this.value;
    if (val) {
        loadExpenses(val);
        loadSummary(val);
    } else {
        loadExpenses();
        loadSummary();
    }
});

// Carica spese all'avvio
loadExpenses();

function loadSummary(month) {
    let url = 'api/summary.php';
    if (month) url += '?month=' + month;
    fetch(url)
        .then(res => res.json())
        .then(data => {
            const ctx = document.getElementById('categoryChart').getContext('2d');
            if (window.categoryChartInstance) window.categoryChartInstance.destroy();
            window.categoryChartInstance = new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: data.map(d => d.category),
                    datasets: [{
                        data: data.map(d => d.total)
                    }]
                }
            });
        });
}

function deleteExpense(id) {
    if (!confirm('Sei sicuro di voler eliminare questa spesa?')) return;
    showLoader(true);
    showError('');
    fetch('api/expenses.php', {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${id}`
    })
    .then(res => res.json())
    .then(response => {
        if (response.status === 'deleted') {
            loadExpenses();
            loadSummary(monthInput.value);
        } else {
            showError('Errore durante l\'eliminazione.');
        }
    })
    .catch(() => showError('Errore di rete durante l\'eliminazione.'))
    .finally(() => showLoader(false));
}

let editModal = null;
function editExpense(exp) {
    if (editModal) editModal.remove();
    editModal = document.createElement('div');
    editModal.style.position = 'fixed';
    editModal.style.top = '0';
    editModal.style.left = '0';
    editModal.style.width = '100vw';
    editModal.style.height = '100vh';
    editModal.style.background = 'rgba(0,0,0,0.3)';
    editModal.style.display = 'flex';
    editModal.style.alignItems = 'center';
    editModal.style.justifyContent = 'center';
    editModal.innerHTML = `
        <form id="editForm" style="background:#fff;padding:20px;border-radius:10px;min-width:300px;">
            <h3>Modifica Spesa</h3>
            <label>Categoria:</label>
            <select name="category" required>
                <option value="Alimentari">Alimentari</option>
                <option value="Bollette">Bollette</option>
                <option value="Svago">Svago</option>
                <option value="Trasporti">Trasporti</option>
                <option value="Altro">Altro</option>
            </select>
            <label>Importo (€):</label>
            <input type="number" name="amount" step="0.01" required>
            <label>Descrizione:</label>
            <input type="text" name="description" required>
            <label>Data spesa:</label>
            <input type="date" name="expense_date" required>
            <input type="hidden" name="id">
            <div style="margin-top:15px;">
                <button type="submit">Salva</button>
                <button type="button" id="closeEditModal">Annulla</button>
            </div>
        </form>
    `;
    document.body.appendChild(editModal);
    const form = document.getElementById('editForm');
    form.category.value = exp.category;
    form.amount.value = exp.amount;
    form.description.value = exp.description;
    form.expense_date.value = exp.expense_date;
    form.id.value = exp.id;
    document.getElementById('closeEditModal').onclick = () => editModal.remove();
    form.onsubmit = function(e) {
        e.preventDefault();
        showLoader(true);
        showError('');
        const data = Object.fromEntries(new FormData(form).entries());
        fetch('api/expenses.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(res => res.json())
        .then(response => {
            if (response.status === 'updated') {
                loadExpenses(monthInput.value);
                loadSummary(monthInput.value);
                editModal.remove();
            } else {
                showError('Errore durante la modifica.');
            }
        })
        .catch(() => showError('Errore di rete durante la modifica.'))
        .finally(() => showLoader(false));
    };
}
