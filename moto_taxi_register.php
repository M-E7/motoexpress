<?php
session_start();
require_once 'db.php';

// Rediriger si déjà connecté
if (isset($_SESSION['moto_taxi_id'])) {
    header('Location: moto_taxi_home.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $numero_permis = trim($_POST['numero_permis'] ?? '');
    $numero_immatriculation = trim($_POST['numero_immatriculation'] ?? '');
    $marque_moto = trim($_POST['marque_moto'] ?? '');
    $modele_moto = trim($_POST['modele_moto'] ?? '');
    $annee_moto = trim($_POST['annee_moto'] ?? '');
    $couleur_moto = trim($_POST['couleur_moto'] ?? '');
    
    // Validation
    if (!$nom || !$prenom || !$telephone || !$password || !$confirm_password || !$numero_permis || !$numero_immatriculation) {
        $error = "Veuillez remplir tous les champs obligatoires.";
    } elseif ($password !== $confirm_password) {
        $error = "Les mots de passe ne correspondent pas.";
    } elseif (strlen($password) < 6) {
        $error = "Le mot de passe doit contenir au moins 6 caractères.";
    } elseif (!preg_match('/^237[0-9]{9}$/', $telephone)) {
        $error = "Le numéro de téléphone doit être au format 237XXXXXXXXX.";
    } else {
        // Vérifier si le téléphone existe déjà
        $stmt = $pdo->prepare("SELECT id FROM moto_taxis WHERE telephone = ?");
        $stmt->execute([$telephone]);
        if ($stmt->fetch()) {
            $error = "Ce numéro de téléphone est déjà utilisé.";
        } else {
            // Vérifier si l'email existe déjà (si fourni)
            if ($email) {
                $stmt = $pdo->prepare("SELECT id FROM moto_taxis WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $error = "Cette adresse email est déjà utilisée.";
                }
            }
            
            if (!$error) {
                // Traitement de la photo de profil
                $photo_profil = '';
                if (isset($_FILES['photo_profil']) && $_FILES['photo_profil']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = 'uploads/moto_taxis/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    $file_extension = strtolower(pathinfo($_FILES['photo_profil']['name'], PATHINFO_EXTENSION));
                    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                    
                    if (in_array($file_extension, $allowed_extensions)) {
                        $filename = 'moto_taxi_' . time() . '_' . uniqid() . '.' . $file_extension;
                        $filepath = $upload_dir . $filename;
                        
                        if (move_uploaded_file($_FILES['photo_profil']['tmp_name'], $filepath)) {
                            $photo_profil = $filepath;
                        }
                    }
                }
                
                // Traitement des documents
                $documents = [];
                $upload_dir_docs = 'uploads/documents_moto_taxis/';
                if (!is_dir($upload_dir_docs)) {
                    mkdir($upload_dir_docs, 0777, true);
                }
                
                $document_types = ['permis_conduire', 'carte_grise', 'assurance', 'carte_identite'];
                foreach ($document_types as $doc_type) {
                    if (isset($_FILES[$doc_type]) && $_FILES[$doc_type]['error'] === UPLOAD_ERR_OK) {
                        $file_extension = strtolower(pathinfo($_FILES[$doc_type]['name'], PATHINFO_EXTENSION));
                        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
                        
                        if (in_array($file_extension, $allowed_extensions)) {
                            $filename = $doc_type . '_' . time() . '_' . uniqid() . '.' . $file_extension;
                            $filepath = $upload_dir_docs . $filename;
                            
                            if (move_uploaded_file($_FILES[$doc_type]['tmp_name'], $filepath)) {
                                $documents[$doc_type] = $filepath;
                            }
                        }
                    }
                }
                
                try {
                    $pdo->beginTransaction();
                    
                    // Insérer le moto-taxi
                    $stmt = $pdo->prepare("
                        INSERT INTO moto_taxis (
                            nom, prenom, telephone, email, mot_de_passe, 
                            numero_permis, numero_immatriculation, marque_moto, 
                            modele_moto, annee_moto, couleur_moto, photo_profil,
                            statut, solde, points_fidelite, date_inscription
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'en_attente', 0, 0, NOW())
                    ");
                    
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt->execute([
                        $nom, $prenom, $telephone, $email, $hashed_password,
                        $numero_permis, $numero_immatriculation, $marque_moto,
                        $modele_moto, $annee_moto, $couleur_moto, $photo_profil
                    ]);
                    
                    $moto_taxi_id = $pdo->lastInsertId();
                    
                    // Insérer les documents
                    foreach ($documents as $type => $path) {
                        $stmt = $pdo->prepare("
                            INSERT INTO documents_moto_taxi (
                                moto_taxi_id, type_document, chemin_fichier, date_upload
                            ) VALUES (?, ?, ?, NOW())
                        ");
                        $stmt->execute([$moto_taxi_id, $type, $path]);
                    }
                    
                    $pdo->commit();
                    
                    $success = "Inscription réussie ! Votre compte est en attente de validation par l'administrateur. Vous recevrez une notification dès que votre compte sera activé.";
                    
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = "Une erreur est survenue lors de l'inscription. Veuillez réessayer.";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription Moto-Taxi - MotoExpress</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem 1rem;
        }
        .register-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            color: white;
            text-align: center;
            padding: 2rem;
        }
        .logo {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        .title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        .subtitle {
            opacity: 0.9;
            font-size: 0.9rem;
        }
        .form-container {
            padding: 2rem;
        }
        .form-section {
            margin-bottom: 2rem;
        }
        .section-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e5e7eb;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            color: #374151;
            font-weight: 500;
        }
        .form-label.required::after {
            content: ' *';
            color: #dc2626;
        }
        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        .form-file {
            position: relative;
            display: inline-block;
            width: 100%;
        }
        .form-file input[type=file] {
            position: absolute;
            left: -9999px;
        }
        .form-file-label {
            display: block;
            padding: 0.75rem;
            border: 2px dashed #d1d5db;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #f9fafb;
        }
        .form-file-label:hover {
            border-color: #2563eb;
            background: #f0f9ff;
        }
        .form-file-label i {
            font-size: 1.5rem;
            color: #6b7280;
            margin-bottom: 0.5rem;
        }
        .btn {
            padding: 1rem 2rem;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        .btn-primary {
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            color: white;
            width: 100%;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(37, 99, 235, 0.3);
        }
        .btn-secondary {
            background: #f3f4f6;
            color: #374151;
            margin-top: 1rem;
            width: 100%;
        }
        .btn-secondary:hover {
            background: #e5e7eb;
        }
        .error {
            background: #fee2e2;
            color: #991b1b;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border: 1px solid #fecaca;
        }
        .success {
            background: #dcfce7;
            color: #166534;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border: 1px solid #bbf7d0;
        }
        .divider {
            text-align: center;
            margin: 1.5rem 0;
            position: relative;
        }
        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #e5e7eb;
        }
        .divider span {
            background: white;
            padding: 0 1rem;
            color: #6b7280;
            font-size: 0.9rem;
        }
        .info-box {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .info-box h4 {
            color: #0369a1;
            margin-bottom: 0.5rem;
        }
        .info-box p {
            color: #0c4a6e;
            font-size: 0.9rem;
            line-height: 1.5;
        }
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="header">
            <div class="logo">
                <i class="fas fa-motorcycle"></i>
            </div>
            <div class="title">MotoExpress</div>
            <div class="subtitle">Inscription Moto-Taxi</div>
        </div>
        
        <div class="form-container">
            <?php if ($error): ?>
                <div class="error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
                <a href="moto_taxi_login.php" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt"></i> Aller à la connexion
                </a>
            <?php else: ?>
                <div class="info-box">
                    <h4><i class="fas fa-info-circle"></i> Informations importantes</h4>
                    <p>Votre inscription sera examinée par notre équipe. Vous recevrez une notification dès que votre compte sera activé. Assurez-vous que tous vos documents sont valides et lisibles.</p>
                </div>
                
                <form method="post" enctype="multipart/form-data">
                    <!-- Informations personnelles -->
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-user"></i> Informations personnelles
                        </div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label required">Nom</label>
                                <input type="text" name="nom" class="form-input" required 
                                       value="<?php echo htmlspecialchars($_POST['nom'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label required">Prénom</label>
                                <input type="text" name="prenom" class="form-input" required 
                                       value="<?php echo htmlspecialchars($_POST['prenom'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label required">Téléphone</label>
                                <input type="tel" name="telephone" class="form-input" 
                                       placeholder="237612345678" required 
                                       value="<?php echo htmlspecialchars($_POST['telephone'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-input" 
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sécurité -->
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-lock"></i> Sécurité
                        </div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label required">Mot de passe</label>
                                <input type="password" name="password" class="form-input" 
                                       placeholder="Minimum 6 caractères" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label required">Confirmer le mot de passe</label>
                                <input type="password" name="confirm_password" class="form-input" required>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Informations moto -->
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-motorcycle"></i> Informations moto
                        </div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label required">Numéro de permis</label>
                                <input type="text" name="numero_permis" class="form-input" required 
                                       value="<?php echo htmlspecialchars($_POST['numero_permis'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label required">Numéro d'immatriculation</label>
                                <input type="text" name="numero_immatriculation" class="form-input" required 
                                       value="<?php echo htmlspecialchars($_POST['numero_immatriculation'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Marque</label>
                                <input type="text" name="marque_moto" class="form-input" 
                                       value="<?php echo htmlspecialchars($_POST['marque_moto'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Modèle</label>
                                <input type="text" name="modele_moto" class="form-input" 
                                       value="<?php echo htmlspecialchars($_POST['modele_moto'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Année</label>
                                <input type="number" name="annee_moto" class="form-input" 
                                       min="1990" max="2024" 
                                       value="<?php echo htmlspecialchars($_POST['annee_moto'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Couleur</label>
                                <input type="text" name="couleur_moto" class="form-input" 
                                       value="<?php echo htmlspecialchars($_POST['couleur_moto'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Documents -->
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-file-upload"></i> Documents
                        </div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Photo de profil</label>
                                <div class="form-file">
                                    <label class="form-file-label">
                                        <i class="fas fa-camera"></i>
                                        <div>Cliquez pour sélectionner une photo</div>
                                        <small>JPG, PNG, GIF (max 5MB)</small>
                                        <input type="file" name="photo_profil" accept="image/*">
                                    </label>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Permis de conduire</label>
                                <div class="form-file">
                                    <label class="form-file-label">
                                        <i class="fas fa-id-card"></i>
                                        <div>Permis de conduire</div>
                                        <small>JPG, PNG, PDF (max 5MB)</small>
                                        <input type="file" name="permis_conduire" accept="image/*,.pdf">
                                    </label>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Carte grise</label>
                                <div class="form-file">
                                    <label class="form-file-label">
                                        <i class="fas fa-car"></i>
                                        <div>Carte grise</div>
                                        <small>JPG, PNG, PDF (max 5MB)</small>
                                        <input type="file" name="carte_grise" accept="image/*,.pdf">
                                    </label>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Assurance</label>
                                <div class="form-file">
                                    <label class="form-file-label">
                                        <i class="fas fa-shield-alt"></i>
                                        <div>Attestation d'assurance</div>
                                        <small>JPG, PNG, PDF (max 5MB)</small>
                                        <input type="file" name="assurance" accept="image/*,.pdf">
                                    </label>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Carte d'identité</label>
                                <div class="form-file">
                                    <label class="form-file-label">
                                        <i class="fas fa-id-badge"></i>
                                        <div>Carte d'identité</div>
                                        <small>JPG, PNG, PDF (max 5MB)</small>
                                        <input type="file" name="carte_identite" accept="image/*,.pdf">
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> Créer mon compte
                    </button>
                </form>
                
                <div class="divider">
                    <span>ou</span>
                </div>
                
                <a href="moto_taxi_login.php" class="btn btn-secondary">
                    <i class="fas fa-sign-in-alt"></i> J'ai déjà un compte
                </a>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Améliorer l'interface des fichiers
        document.querySelectorAll('input[type="file"]').forEach(input => {
            input.addEventListener('change', function(e) {
                const label = this.parentElement.querySelector('.form-file-label');
                const file = this.files[0];
                
                if (file) {
                    label.innerHTML = `
                        <i class="fas fa-check" style="color: #16a34a;"></i>
                        <div>${file.name}</div>
                        <small>${(file.size / 1024 / 1024).toFixed(2)} MB</small>
                    `;
                    label.style.borderColor = '#16a34a';
                    label.style.background = '#f0fdf4';
                } else {
                    label.innerHTML = `
                        <i class="fas fa-upload"></i>
                        <div>Cliquez pour sélectionner un fichier</div>
                        <small>JPG, PNG, PDF (max 5MB)</small>
                    `;
                    label.style.borderColor = '#d1d5db';
                    label.style.background = '#f9fafb';
                }
            });
        });
        
        // Validation en temps réel
        document.querySelector('form').addEventListener('submit', function(e) {
            const password = document.querySelector('input[name="password"]').value;
            const confirmPassword = document.querySelector('input[name="confirm_password"]').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Les mots de passe ne correspondent pas.');
                return;
            }
            
            if (password.length < 6) {
                e.preventDefault();
                alert('Le mot de passe doit contenir au moins 6 caractères.');
                return;
            }
        });
    </script>
</body>
</html> 