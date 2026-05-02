<?php
// Compter les messages et suggestions
$messages_count = $conn->query("SELECT COUNT(*) FROM messages")->fetch_row()[0];
$suggestions_count = $conn->query("SELECT COUNT(*) FROM suggestions")->fetch_row()[0];
?>
<div class="admin-container">
    <h2><i class="fas fa-envelope"></i> Messages reçus (<?= $messages_count ?>)</h2>
    
    <?php if (isset($_SESSION['flash_success'])): ?>
        <div class="alert success"><?= $_SESSION['flash_success'] ?></div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>
    
    <?php if (mysqli_num_rows($messages) > 0): ?>
        <div class="message-grid">
            <?php while($msg = mysqli_fetch_assoc($messages)): ?>
                <div class="message-card">
                    <div class="message-header">
                        <div class="message-meta">
                            <div class="message-sender">
                                <i class="fas fa-user"></i> <?= h($msg['name']) ?>
                            </div>
                            <div class="message-email">
                                <i class="fas fa-envelope"></i> <?= h($msg['email']) ?>
                            </div>
                        </div>
                        <div class="message-date">
                            <?= date('d/m/Y à H:i', strtotime($msg['sent_at'])) ?>
                        </div>
                    </div>
                    
                    <div class="message-content">
                        <?= nl2br(h($msg['message'])) ?>
                    </div>
                    
                    <div class="message-actions">
                        <a href="index.php?action=delete_message&id=<?= $msg['id'] ?>" 
                           class="btn-action btn-delete"
                           onclick="return confirm('Supprimer définitivement ce message ?')">
                            <i class="fas fa-trash"></i> Supprimer
                        </a>
                        
                        <a href="mailto:<?= h($msg['email']) ?>?subject=RE: Votre message" 
                           class="btn-action btn-reply">
                            <i class="fas fa-reply"></i> Répondre
                        </a>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
        
        <div class="pagination">
            <!-- Ajoutez ici votre système de pagination si nécessaire -->
            <a href="index.php?action=admin_dashboard" class="btn btn-back">
                <i class="fas fa-arrow-left"></i> Retour au tableau de bord
            </a>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-inbox fa-3x"></i>
            <h3>Aucun message reçu</h3>
            <p>Vous n'avez aucun message pour le moment.</p>
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

/* Grille des messages */
.message-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
    margin: 30px 0;
}

/* Carte de message */
.message-card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    border-top: 3px solid var(--green);
}

/* En-tête */
.message-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 15px;
    background: #f8f9fa;
    border-bottom: 1px solid #eee;
}

.message-meta {
    flex: 1;
}

.message-sender {
    font-weight: 600;
    color: var(--green-dark);
    margin-bottom: 5px;
}

.message-email {
    color: #666;
    font-size: 0.9em;
}

.message-date {
    color: #666;
    font-size: 0.85em;
    white-space: nowrap;
    margin-left: 10px;
}

/* Contenu */
.message-content {
    padding: 15px;
    flex: 1;
    line-height: 1.6;
    color: #333;
}

/* Actions */
.message-actions {
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
    color: var(--green-dark);
    border: 1px solid var(--green);
}

.btn-reply:hover {
    background: var(--green);
    color: white;
}

/* État vide */
.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: #666;
}

.empty-state i {
    color: #ddd;
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

/* Responsive */
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