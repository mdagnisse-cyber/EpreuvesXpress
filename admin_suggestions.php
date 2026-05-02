<?php
// Compter les messages et suggestions
$messages_count = $conn->query("SELECT COUNT(*) FROM messages")->fetch_row()[0];
$suggestions_count = $conn->query("SELECT COUNT(*) FROM suggestions")->fetch_row()[0];
?>
<div class="admin-container">
    <h2><i class="fas fa-lightbulb"></i> Suggestions reçues (<?= $suggestions_count ?>)</h2>
    
    <?php if (isset($_SESSION['flash_success'])): ?>
        <div class="alert success"><?= $_SESSION['flash_success'] ?></div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>
    
    <?php if (mysqli_num_rows($suggestions) > 0): ?>
        <div class="suggestion-grid">
            <?php while($sugg = mysqli_fetch_assoc($suggestions)): ?>
                <div class="suggestion-card">
                    <div class="suggestion-header">
                        <div class="suggestion-meta">
                            <div class="suggestion-author">
                                <i class="fas fa-user"></i> <?= $sugg['name'] ? h($sugg['name']) : 'Anonyme' ?>
                            </div>
                        </div>
                        <div class="suggestion-date">
                            <?= date('d/m/Y à H:i', strtotime($sugg['submitted_at'])) ?>
                        </div>
                    </div>
                    
                    <div class="suggestion-content">
                        <?= nl2br(h($sugg['suggestion'])) ?>
                    </div>
                    
                    <div class="suggestion-actions">
                        <a href="index.php?action=delete_suggestion&id=<?= $sugg['id'] ?>" 
                           class="btn-action btn-delete"
                           onclick="return confirm('Supprimer définitivement cette suggestion ?')">
                            <i class="fas fa-trash"></i> Supprimer
                        </a>
                        
                        <?php if ($sugg['name']): ?>
                        <a href="mailto:<?= h($sugg['email'] ?? '') ?>?subject=RE: Votre suggestion" 
                           class="btn-action btn-reply">
                            <i class="fas fa-reply"></i> Répondre
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
        
        <div class="pagination">
            <a href="index.php?action=admin_dashboard" class="btn btn-back">
                <i class="fas fa-arrow-left"></i> Retour au tableau de bord
            </a>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-lightbulb fa-3x"></i>
            <h3>Aucune suggestion reçue</h3>
            <p>Aucune suggestion n'a été soumise pour le moment.</p>
            <a href="index.php?action=admin_dashboard" class="btn">
                <i class="fas fa-arrow-left"></i> Retour au tableau de bord
            </a>
        </div>
    <?php endif; ?>
</div>

<style>
/* Structure principale */
.admin-container {
    max-width: 1200px;
    margin: 20px auto;
    padding: 20px;
}

/* Grille des suggestions */
.suggestion-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
    margin: 30px 0;
}

/* Carte de suggestion */
.suggestion-card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    border-top: 3px solid #f6c23e; /* Couleur différente pour les suggestions */
}

/* En-tête */
.suggestion-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 15px;
    background: #f8f9fa;
    border-bottom: 1px solid #eee;
}

.suggestion-meta {
    flex: 1;
}

.suggestion-author {
    font-weight: 600;
    color: #6c757d;
    margin-bottom: 5px;
}

.suggestion-date {
    color: #666;
    font-size: 0.85em;
    white-space: nowrap;
    margin-left: 10px;
}

/* Contenu */
.suggestion-content {
    padding: 15px;
    flex: 1;
    line-height: 1.6;
    color: #333;
    font-style: italic; /* Pour différencier visuellement les suggestions */
}

/* Actions */
.suggestion-actions {
    display: flex;
    justify-content: flex-end;
    padding: 12px 15px;
    border-top: 1px solid #eee;
    gap: 10px;
}

.btn-action {
    padding: 8px 12px;
    border-radius: 4px;
    font-size: 0.9em;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    text-decoration: none;
    transition: all 0.2s;
}

.btn-delete {
    background: #f8f8f8;
    color: #e74c3c;
    border: 1px solid #e74c3c;
}

.btn-delete:hover {
    background: #e74c3c;
    color: white;
}

.btn-reply {
    background: #f8f8f8;
    color: #6c757d;
    border: 1px solid #6c757d;
}

.btn-reply:hover {
    background: #6c757d;
    color: white;
}

/* État vide */
.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: #666;
}

.empty-state i {
    color: #f6c23e; /* Couleur jaune pour l'icône ampoule */
    margin-bottom: 15px;
}

.empty-state h3 {
    color: #444;
    margin-bottom: 10px;
}

/* Bouton de retour */
.btn-back {
    margin-top: 20px;
    background: var(--green);
    color: white;
}

/* Responsive*/
@media (max-width: 768px) {
    .message-grid {
        grid-template-columns: 1fr;
    }
    
    .message-header {
        flex-direction: column;
    }
    
    .message-date {
        margin-left: 0;
        margin-top: 5px;
    }
}
</style>