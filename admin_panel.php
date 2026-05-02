<?php
if (!isset($action) || !is_admin()) {
    header("Location: index.php");
    exit();
}
?>
<?php
// Compter les messages et suggestions
$messages_count = $conn->query("SELECT COUNT(*) FROM messages")->fetch_row()[0];
$suggestions_count = $conn->query("SELECT COUNT(*) FROM suggestions")->fetch_row()[0];
?>
<!-- Tableau de bord Admin -->
<?php if ($action === 'admin_dashboard'): ?>
<div class="card">
    <h2><i class="fas fa-chart-pie"></i> Tableau de bord Admin</h2>
    <div class="flex" style="gap:20px; margin-top:30px;">
        <div class="card grow">
            <h3>Utilisateurs</h3>
            <div style="font-size:2em; color:var(--green);"><?= $stats['users'] ?></div>
        </div>
        <div class="card grow">
            <h3>Épreuves</h3>
            <div style="font-size:2em; color:var(--green);"><?= $stats['exams'] ?></div>
        </div>
        <div class="card grow">
            <h3>Écoles</h3>
            <div style="font-size:2em; color:var(--green);"><?= $stats['schools'] ?></div>
        </div>
    </div>
</div>

<!-- Gestion des utilisateurs -->
<?php elseif ($action === 'admin_users' && isset($users_query)): ?>
<div class="card">
    <h2><i class="fas fa-user-cog"></i> Gestion des utilisateurs</h2>
    
    <?php if (isset($_SESSION['flash_error'])): ?>
        <div class="alert error"><?= $_SESSION['flash_error'] ?></div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['flash_success'])): ?>
        <div class="alert success"><?= $_SESSION['flash_success'] ?></div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <!-- Version Desktop (corrigée) -->
    <div class="desktop-view">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nom</th>
                    <th>Email</th>
                    <th>Rôle</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                // Réinitialiser le pointeur pour la version desktop
                mysqli_data_seek($users_query, 0);
                while($user = mysqli_fetch_assoc($users_query)): 
                ?>
                <tr>
                    <td><?= $user['id'] ?></td>
                    <td><?= h($user['username']) ?></td>
                    <td><?= h($user['email']) ?></td>
                    <td>
                        <form method="post" action="index.php?action=admin_update_role" class="role-form">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                            <select name="role" class="role-select" <?= ($user['id'] === $_SESSION['user_id']) ? 'disabled' : '' ?>>
                                <option value="etudiant" <?= $user['role'] === 'etudiant' ? 'selected' : '' ?>>Étudiant</option>
                                <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                            </select>
                            <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                                <button type="submit" class="btn-icon"><i class="fas fa-check"></i></button>
                            <?php endif; ?>
                        </form>
                    </td>
                    <td>
                        <?php if ($user['id'] !== $_SESSION['user_id'] && 
                                 !($user['role'] === 'admin' && 
                                   mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE role = 'admin'"))['count'] <= 1)): ?>
                            <a href="index.php?action=delete_user&id=<?= $user['id'] ?>" 
                               class="btn-delete"
                               onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ?');">
                                <i class="fas fa-trash"></i>
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <!-- Version Mobile -->
    <div class="mobile-view">
        <?php 
        // Réinitialiser le pointeur pour la version mobile
        mysqli_data_seek($users_query, 0);
        while($user = mysqli_fetch_assoc($users_query)): 
        ?>
        <div class="user-card">
            <div class="user-header">
                <span class="user-id">#<?= $user['id'] ?></span>
                <h3 class="user-name"><?= h($user['username']) ?></h3>
            </div>
            
            <div class="user-details">
                <div class="detail-row">
                    <span class="detail-label">Email:</span>
                    <span class="detail-value"><?= h($user['email']) ?></span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Rôle:</span>
                    <div class="detail-value">
                        <form method="post" action="index.php?action=admin_update_role" class="role-form">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                            <select name="role" class="role-select" <?= ($user['id'] === $_SESSION['user_id']) ? 'disabled' : '' ?>>
                                <option value="etudiant" <?= $user['role'] === 'etudiant' ? 'selected' : '' ?>>Étudiant</option>
                                <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                            </select>
                            <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                                <button type="submit" class="btn-icon"><i class="fas fa-check"></i></button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="user-actions">
                <?php if ($user['id'] !== $_SESSION['user_id'] && 
                         !($user['role'] === 'admin' && 
                           mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE role = 'admin'"))['count'] <= 1)): ?>
                    <a href="index.php?action=delete_user&id=<?= $user['id'] ?>" 
                       class="btn-action btn-delete"
                       onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ?');">
                        <i class="fas fa-trash"></i> Supprimer
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
</div>

<style>
/* Styles de base */
.card {
    background: white;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}

.alert {
    padding: 10px 15px;
    margin-bottom: 15px;
    border-radius: 4px;
}

.alert.error {
    background: #f8d7da;
    color: #721c24;
}

.alert.success {
    background: #d4edda;
    color: #155724;
}

/* Version Desktop */
.desktop-view {
    width: 100%;
    overflow-x: auto;
}

.admin-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}

.admin-table th {
    background: var(--green);
    color: white;
    padding: 12px 8px;
    text-align: left;
}

.admin-table td {
    padding: 12px 8px;
    border-bottom: 1px solid #eee;
    vertical-align: middle;
}

.role-form {
    display: flex;
    align-items: center;
    gap: 8px;
}

.role-select {
    padding: 6px 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.btn-icon {
    background: var(--green);
    color: white;
    border: none;
    border-radius: 4px;
    padding: 6px 10px;
    cursor: pointer;
}

.btn-delete {
    color: #dc3545;
    background: none;
    border: none;
    cursor: pointer;
}

/* Version Mobile */
.mobile-view {
    display: none;
}

.user-card {
    background: white;
    border: 1px solid #eee;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 15px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.user-header {
    display: flex;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #f5f5f5;
}

.user-id {
    background: var(--green);
    color: white;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 0.8rem;
    margin-right: 10px;
}

.user-name {
    margin: 0;
    font-size: 1.1rem;
    color: #333;
}

.detail-row {
    display: flex;
    margin-bottom: 12px;
    align-items: center;
}

.detail-label {
    font-weight: 600;
    color: var(--green-dark);
    min-width: 80px;
    font-size: 0.9rem;
}

.detail-value {
    flex: 1;
}

.user-actions {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #f5f5f5;
    text-align: right;
}

.btn-action {
    padding: 8px 15px;
    border-radius: 20px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 0.9rem;
}

.btn-delete {
    background: #f8d7da;
    color: #721c24;
}

.btn-delete:hover {
    background: #e74c3c;
    color: white;
}

/* Responsive Switch */
@media (max-width: 768px) {
    .desktop-view {
        display: none;
    }
    
    .mobile-view {
        display: block;
    }
}

@media (min-width: 769px) {
    .mobile-view {
        display: none;
    }
    
    .desktop-view {
        display: block;
    }
}
</style>
<?php elseif ($action === 'admin_exams' && isset($exams_query)): ?>
<div class="card">
    <h2><i class="fas fa-file-alt"></i> Gestion des épreuves</h2>
    
    <!-- Messages flash -->
    <?php if (isset($_SESSION['flash_error'])): ?>
        <div class="alert error"><?= $_SESSION['flash_error'] ?></div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['flash_success'])): ?>
        <div class="alert success"><?= $_SESSION['flash_success'] ?></div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>
<form method="post" action="index.php?action=bulk_delete_exams">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    
    <div class="bulk-actions">
        <button type="submit" name="bulk_delete" class="btn btn-danger" 
                onclick="return confirm('Voulez-vous vraiment supprimer les épreuves sélectionnées ?')">
            <i class="fas fa-trash"></i> Supprimer la sélection
        </button>
    </div>

    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th width="40">
                        <input type="checkbox" id="selectAll" onclick="toggleCheckboxes(this)">
                    </th>
                    <th>ID</th>
                    <th>Titre</th>
                    <th>Matière</th>
                    <th>Auteur</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
               <?php while($exam = mysqli_fetch_assoc($exams_query)): ?>
<tr>
    <td data-label="Sélection">
        <input type="checkbox" name="ids[]" value="<?= $exam['Id'] ?>">
    </td>
    <td data-label="ID"><?= $exam['Id'] ?></td>
    <td data-label="Titre"><?= h($exam['Title']) ?></td>
    <td data-label="Matière">
        <?= h($exam['subject_name'] ?? 'Non spécifié') ?>
        <?php if ($exam['department_name'] || $exam['school_name']): ?>
            <div class="small-text">
                <?= h($exam['department_name'] ?? '') ?> 
                <?= ($exam['department_name'] && $exam['school_name']) ? ' - ' : '' ?>
                <?= h($exam['school_name'] ?? '') ?>
            </div>
        <?php endif; ?>
    </td>
    <td data-label="Auteur"><?= h($exam['username']) ?></td>
    <td data-label="Date"><?= date('d/m/Y', strtotime($exam['Uploaded_at'])) ?></td>
    <td class="actions">
        <?php if (!empty($exam['Filename'])): ?>
            <a href="uploads/<?= urlencode($exam['Filename']) ?>" 
               target="_blank" 
               class="btn-action" 
               title="Voir">
                <i class="fas fa-eye"></i>
            </a>
        <?php endif; ?>
        <a href="index.php?action=delete_exam&id=<?= $exam['Id'] ?>" 
           class="btn-action btn-delete"
           onclick="return confirm('Supprimer cette épreuve définitivement ?');"
           title="Supprimer">
            <i class="fas fa-trash"></i>
        </a>
    </td>
</tr>
<?php endwhile; ?>
            </tbody>
        </table>
    </div>
</form>

<script>
// Simple fonction pour sélectionner/désélectionner tout (optionnel)
function toggleCheckboxes(source) {
    const checkboxes = document.querySelectorAll('input[name="ids[]"]');
    checkboxes.forEach(checkbox => {
        checkbox.checked = source.checked;
    });
}
</script>

<style>
/* Table responsive */
.table-responsive {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

/* Table style */
.admin-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}

.admin-table th {
    background: var(--green);
    color: white;
    padding: 12px 8px;
    text-align: left;
}

.admin-table td {
    padding: 10px 8px;
    border-bottom: 1px solid #eee;
    vertical-align: top;
}

/* Style pour mobile */
@media (max-width: 768px) {
    .admin-table {
        display: block;
    }
    
    .admin-table thead {
        display: none;
    }
    
    .admin-table tr {
        display: block;
        margin-bottom: 15px;
        border: 1px solid #ddd;
        border-radius: 5px;
        padding: 10px;
    }
    
    .admin-table td {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 5px;
        border-bottom: 1px dashed #eee;
    }
    
    .admin-table td:before {
        content: attr(data-label);
        font-weight: bold;
        margin-right: 15px;
        color: var(--green-dark);
    }
    
    .admin-table td.actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        border-bottom: none;
    }
    
    .admin-table td.actions:before {
        display: none;
    }
}

/* Cases à cocher */
.exam-checkbox {
    transform: scale(1.2);
    cursor: pointer;
}

#selectAll {
    cursor: pointer;
}

/* Actions groupées */
.bulk-actions {
    background: #f8f9fa;
    padding: 12px 15px;
    margin-bottom: 15px;
    border-radius: 5px;
    display: flex;
    align-items: center;
    gap: 15px;
    border: 1px solid #ddd;
}

/* Boutons actions */
.btn-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #f0f0f0;
    color: #555;
    transition: all 0.2s;
}

.btn-action:hover {
    background: var(--green);
    color: white;
}

.btn-delete:hover {
    background: #e74c3c;
}

/* Texte tronqué */
.truncate {
    max-width: 200px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.small-text {
    font-size: 0.85em;
    color: #666;
    margin-top: 3px;
}
</style>

<script>
// Gestion des sélections
document.addEventListener('DOMContentLoaded', function() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.exam-checkbox');
    const bulkActions = document.getElementById('bulkActions');
    const selectedCount = document.getElementById('selectedCount');
    
    // Sélection/désélection globale
    selectAll.addEventListener('change', function() {
        checkboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
        updateBulkActions();
    });
    
    // Mise à jour des actions groupées
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateBulkActions);
    });
    
    function updateBulkActions() {
        const selected = document.querySelectorAll('.exam-checkbox:checked');
        selectedCount.textContent = selected.length;
        
        if (selected.length > 0) {
            bulkActions.style.display = 'flex';
            selectAll.checked = selected.length === checkboxes.length;
        } else {
            bulkActions.style.display = 'none';
            selectAll.checked = false;
        }
    }
    
    // Annulation de la sélection
    window.clearSelection = function() {
        checkboxes.forEach(checkbox => {
            checkbox.checked = false;
        });
        updateBulkActions();
    };
});

// Confirmation suppression multiple
window.confirmBulkDelete = function() {
    const selected = Array.from(document.querySelectorAll('.exam-checkbox:checked'))
                         .map(checkbox => checkbox.dataset.id);
    
    if (selected.length === 0) return;
    
    if (confirm(`Voulez-vous vraiment supprimer les ${selected.length} épreuves sélectionnées ?`)) {
        // Envoyer les IDs sélectionnés au serveur
        fetch('index.php?action=bulk_delete_exams', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ ids: selected })
        })
        .then(response => window.location.reload())
        .catch(error => console.error('Error:', error));
    }
};
</script>


<!-- Statistiques (Nouveau) -->
<?php elseif ($action === 'admin_stats' && isset($stats_data)): ?>
<div class="card">
    <h2><i class="fas fa-chart-line"></i> Statistiques</h2>
    
    <div class="flex stats-container">
        <!-- Derniers utilisateurs -->
        <div class="card stat-card">
            <h3><i class="fas fa-users"></i> Derniers inscrits</h3>
            <ul class="stat-list">
                <?php while($user = mysqli_fetch_assoc($stats_data['recent_users'])): ?>
                <li>
                    <span class="stat-value"><?= h($user['username']) ?></span>
                    <span class="stat-date"><?= date('d/m/Y', strtotime($user['created_at'])) ?></span>
                </li>
                <?php endwhile; ?>
            </ul>
        </div>
        
        <!-- Matières populaires -->
        <div class="card stat-card">
            <h3><i class="fas fa-book"></i> Matières actives</h3>
            <ul class="stat-list">
                <?php while($subject = mysqli_fetch_assoc($stats_data['popular_subjects'])): ?>
                <li>
                    <span class="stat-value"><?= h($subject['name']) ?></span>
                    <span class="stat-count"><?= $subject['count'] ?> épreuves</span>
                </li>
                <?php endwhile; ?>
            </ul>
        </div>
                    <!-- Téléchargements -->
            <div class="card stat-card">
                <h3><i class="fas fa-download"></i> Téléchargements (7j)</h3>
                <ul class="stat-list">
                    <?php while($day = mysqli_fetch_assoc($stats_data['downloads'])): ?>
                    <li>
                        <span class="stat-date"><?= date('d/m', strtotime($day['day'])) ?></span>
                        <span class="stat-count"><?= $day['count'] ?> téléchargements</span>
                    </li>
                    <?php endwhile; ?>
                </ul>
            </div>

        <!-- Activité -->
        <div class="card stat-card">
            <h3><i class="fas fa-calendar-alt"></i> Activité (7j)</h3>
            <ul class="stat-list">
                <?php while($day = mysqli_fetch_assoc($stats_data['activity'])): ?>
                <li>
                    <span class="stat-date"><?= date('d/m', strtotime($day['day'])) ?></span>
                    <span class="stat-count"><?= $day['count'] ?> uploads</span>
                </li>
                <?php endwhile; ?>
            </ul>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
/* Structure */
.flex {
    display: flex;
    gap: 20px;
}
.stats-container {
    flex-wrap: wrap;
    margin-top: 20px;
}
.card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    padding: 20px;
    margin-bottom: 20px;
}
.grow, .stat-card {
    flex: 1;
    min-width: 300px;
}

/* Tableaux */
.admin-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}
.admin-table th {
   
    padding: 12px 15px;
    text-align: left;
    font-weight: 600;
}
.admin-table td {
    padding: 12px 15px;
    
}


/* Statistiques */
.stat-list {
    margin-top: 15px;
}
.stat-list li {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #eee;
}
.stat-value {
    font-weight: 500;
}
.stat-date, .stat-count {
    color: #666;
}

/* Formulaires */
.role-form {
    display: flex;
    align-items: center;
    gap: 8px;
}
.role-select {
    padding: 6px 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    background: white;
}
.role-select:disabled {
    background: #f5f5f5;
    cursor: not-allowed;
}

/* Boutons */
.btn-small {
    padding: 6px 10px;
    background: var(--green);
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.btn-delete {
    color: #e74c3c;
    padding: 6px 10px;
    border-radius: 4px;
    display: inline-flex;
    margin-left: 5px;
}
.btn-delete:hover {
    background: #fdeaea;
}

/* Alertes */
.alert {
    padding: 12px;
    border-radius: 4px;
    margin-bottom: 20px;
}
.alert.error {
    background: #fdeaea;
    color: #e74c3c;
}
.alert.success {
    background: #e8f8f0;
    color: #27ae60;
}

/* Icônes */
.fas {
    font-size: 0.9em;
}
</style>