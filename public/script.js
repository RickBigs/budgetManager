// Variabile globale per il grafico
let categoryChartInstance = null;

// Colori per le categorie
const categoryColors = {
    'Alimentari': '#3498db',
    'Bollette': '#e74c3c',
    'Svago': '#f39c12',
    'Trasporti': '#2ecc71',
    'Altro': '#9b59b6'
};

// Funzione per mostrare/nascondere il loader
function showLoader(show) {
    document.getElementById('loader').style.display = show ? 'block' : 'none';
}

// Funzione per mostrare messaggi di errore
function showError(msg) {
    const errorEl = document.getElementById('errorMsg');
    errorEl.textContent = msg || '';
    if (msg) {
        setTimeout(() => {
            errorEl.textContent = '';
        }, 5000);
    }
}

// Funzione per formattare la data in italiano
function formatDate(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleDateString('it-IT', { 
        day: '2-digit', 
        month: '2-digit', 
        year: 'numeric' 
    });
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
        .then(res => {
            if (!res.ok) throw new Error('Errore nel server');
            return res.json();
        })
        .then(data => {
            let total = 0;
            const tbody = document.querySelector('#expensesTable tbody');
            tbody.innerHTML = '';
            
            if (data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="empty-state">Nessuna spesa registrata</td></tr>';
            } else {
                data.forEach(exp => {
                    total += parseFloat(exp.amount);
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${formatDate(exp.expense_date)}</td>
                        <td><span class="category-badge" style="background-color: ${categoryColors[exp.category]}20; color: ${categoryColors[exp.category]}">${exp.category}</span></td>
                        <td>${exp.description}</td>
                        <td style="text-align: right; font-weight: bold;">€ ${parseFloat(exp.amount).toFixed(2)}</td>
                        <td style="text-align: center;">
                            <button class="btn-edit" onclick='editExpense(${JSON.stringify(exp).replace(/'/g, "&apos;")})'>✏️</button>
                            <button class="btn-danger" onclick="deleteExpense(${exp.id})">🗑️</button>
                        </td>
                    `;
                    tbody.appendChild(row);
                });
            }
            
            document.getElementById('total').textContent = total.toFixed(2);
        })
        .catch(err => {
            console.error('Errore:', err);
            showError('Errore nel caricamento delle spese.');
        })
        .finally(() => showLoader(false));
}

// Funzione per caricare il riepilogo e creare il grafico
function loadSummary(month) {
    let url = 'api/summary.php';
    if (month) {
        url += '?month=' + month;
    }
    
    fetch(url)
        .then(res => {
            if (!res.ok) throw new Error('Errore nel server');
            return res.json();
        })
        .then(data => {
            const canvas = document.getElementById('categoryChart');
            const ctx = canvas.getContext('2d');
            const noDataMsg = document.getElementById('noDataMessage');
            const legendContainer = document.getElementById('categoryLegend');
            
            // Distruggi il grafico esistente se presente
            if (categoryChartInstance) {
                categoryChartInstance.destroy();
                categoryChartInstance = null;
            }
            
            // Pulisci la legenda
            legendContainer.innerHTML = '';
            
            if (!data || data.length === 0) {
                canvas.style.display = 'none';
                noDataMsg.style.display = 'block';
                return;
            }
            
            canvas.style.display = 'block';
            noDataMsg.style.display = 'none';
            
            // Prepara i dati per il grafico
            const labels = data.map(d => d.category);
            const values = data.map(d => parseFloat(d.total));
            const colors = labels.map(cat => categoryColors[cat] || '#95a5a6');
            
            // Crea il grafico
            categoryChartInstance = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: colors,
                        borderColor: '#fff',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false // Nascondo la legenda di Chart.js per crearne una custom
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((value / total) * 100).toFixed(1);
                                    return `${label}: € ${value.toFixed(2)} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
            
            // Crea legenda personalizzata
            data.forEach((item, index) => {
                const total = values.reduce((a, b) => a + b, 0);
                const percentage = ((parseFloat(item.total) / total) * 100).toFixed(1);
                
                const legendItem = document.createElement('div');
                legendItem.className = 'legend-item';
                legendItem.innerHTML = `
                    <div class="legend-color" style="background-color: ${colors[index]}"></div>
                    <span>${item.category}: € ${parseFloat(item.total).toFixed(2)} (${percentage}%)</span>
                `;
                legendContainer.appendChild(legendItem);
            });
        })
        .catch(err => {
            console.error('Errore nel caricamento del riepilogo:', err);
            document.getElementById('categoryChart').style.display = 'none';
            document.getElementById('noDataMessage').style.display = 'block';
        });
}

// Funzione per eliminare una spesa
function deleteExpense(id) {
    if (!confirm('Sei sicuro di voler eliminare questa spesa?')) return;
    
    showLoader(true);
    showError('');
    
    fetch('api/expenses.php', {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${id}`
    })
    .then(res => {
        if (!res.ok) throw new Error('Errore nel server');
        return res.json();
    })
    .then(response => {
        if (response.status === 'deleted') {
            const currentMonth = document.getElementById('monthFilter').value;
            loadExpenses(currentMonth);
            loadSummary(currentMonth);
        } else {
            showError('Errore durante l\'eliminazione.');
        }
    })
    .catch(err => {
        console.error('Errore:', err);
        showError('Errore di rete durante l\'eliminazione.');
    })
    .finally(() => showLoader(false));
}

// Funzione per modificare una spesa
function editExpense(exp) {
    // Rimuovi modal esistente se presente
    const existingModal = document.querySelector('.modal-overlay');
    if (existingModal) existingModal.remove();
    
    // Crea il modal
    const modal = document.createElement('div');
    modal.className = 'modal-overlay';
    modal.innerHTML = `
        <div class="modal-content">
            <form id="editForm">
                <h3>✏️ Modifica Spesa</h3>
                
                <label>Categoria:</label>
                <select name="category" required>
                    <option value="Alimentari" ${exp.category === 'Alimentari' ? 'selected' : ''}>Alimentari</option>
                    <option value="Bollette" ${exp.category === 'Bollette' ? 'selected' : ''}>Bollette</option>
                    <option value="Svago" ${exp.category === 'Svago' ? 'selected' : ''}>Svago</option>
                    <option value="Trasporti" ${exp.category === 'Trasporti' ? 'selected' : ''}>Trasporti</option>
                    <option value="Altro" ${exp.category === 'Altro' ? 'selected' : ''}>Altro</option>
                </select>
                
                <label>Importo (€):</label>
                <input type="number" name="amount" step="0.01" value="${exp.amount}" required>
                
                <label>Descrizione:</label>
                <input type="text" name="description" value="${exp.description}" required>
                
                <label>Data spesa:</label>
                <input type="date" name="expense_date" value="${exp.expense_date}" required>
                
                <input type="hidden" name="id" value="${exp.id}">
                
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="this.closest('.modal-overlay').remove()">Annulla</button>
                    <button type="submit" class="btn-primary">Salva modifiche</button>
                </div>
            </form>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Gestisci il submit del form
    const form = document.getElementById('editForm');
    form.onsubmit = function(e) {
        e.preventDefault();
        
        showLoader(true);
        showError('');
        
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());
        
        fetch('api/expenses.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(res => {
            if (!res.ok) throw new Error('Errore nel server');
            return res.json();
        })
        .then(response => {
            if (response.status === 'updated') {
                const currentMonth = document.getElementById('monthFilter').value;
                loadExpenses(currentMonth);
                loadSummary(currentMonth);
                modal.remove();
            } else {
                showError(response.message || 'Errore durante la modifica.');
            }
        })
        .catch(err => {
            console.error('Errore:', err);
            showError('Errore di rete durante la modifica.');
        })
        .finally(() => showLoader(false));
    };
    
    // Chiudi il modal cliccando fuori
    modal.onclick = function(e) {
        if (e.target === modal) {
            modal.remove();
        }
    };
}

// Funzione per resettare il filtro
function resetFilter() {
    document.getElementById('monthFilter').value = '';
    loadExpenses();
    loadSummary();
}

// Gestione filtro mese
document.addEventListener('DOMContentLoaded', function() {
    const monthInput = document.getElementById('monthFilter');
    
    // Imposta il mese corrente come default
    const now = new Date();
    const currentMonth = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0');
    monthInput.value = currentMonth;
    
    // Gestisci il cambio del filtro
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
    
    // Carica i dati iniziali per il mese corrente
    loadExpenses(currentMonth);
    loadSummary(currentMonth);
});

// Aggiungi stili per i badge delle categorie
const style = document.createElement('style');
style.textContent = `
    .category-badge {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.85em;
        font-weight: 600;
        display: inline-block;
    }
`;
document.head.appendChild(style);