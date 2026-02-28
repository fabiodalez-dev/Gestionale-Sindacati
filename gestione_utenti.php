<?php
// gestione_utenti.php
require_once 'config.php';
require_once 'user_functions.php'; // Includi le funzioni utente una sola volta
checkLogin();

// Verifica se l'utente corrente è admin
checkUserRole('admin');

$error = '';
$success = '';

// Genera un token CSRF per il form
generateCsrfToken();

// Recupera la lista delle sedi per il menu a tendina
$sediList = [];
$stmtSedi = executeQuery("SELECT id, nome FROM sedi ORDER BY nome ASC", [], '');
if ($stmtSedi) {
    $resultSedi = $stmtSedi->get_result();
    while ($row = $resultSedi->fetch_assoc()) {
        $sediList[] = $row;
    }
    $resultSedi->free();
    $stmtSedi->close();
}

// Gestione delle azioni (Creazione, Eliminazione, Aggiornamento)
$action = $_POST['action'] ?? '';

if ($action === 'create') {
    // Verifica il token CSRF
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token CSRF non valido.';
    } else {
        // Recupera i dati dal form
        $username = sanitizeInput($_POST['username'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = sanitizeInput($_POST['role'] ?? '');
        $sede_id = (isset($_POST['sede_id']) && $_POST['sede_id'] !== '') ? intval($_POST['sede_id']) : null;

        // Validazione dei campi
        if (empty($username) || empty($email) || empty($password) || empty($role)) {
            $error = "Tutti i campi sono obbligatori.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Indirizzo email non valido.";
        } elseif (!in_array($role, ['admin', 'operatore'])) {
            $error = "Ruolo non valido.";
        } else {
            // Controlla se l'utente o l'email esistono già
            $stmt = executeQuery("SELECT * FROM users WHERE username = ? OR email = ?", [$username, $email], 'ss');
            if ($stmt) {
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $error = "Username o email già in uso.";
                }
                $result->free();
                $stmt->close();
            }
            if (empty($error)) {
                // Si presume che createUser() accetti un ulteriore parametro per la sede
                if (createUser($username, $email, $password, $role, $sede_id)) {
                    $success = "Utente creato con successo.";
                } else {
                    $error = "Errore nella creazione dell'utente.";
                }
            }
        }
    }
}

if ($action === 'delete') {
    // Verifica il token CSRF
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token CSRF non valido.';
    } else {
        $id = intval($_POST['id'] ?? 0);
        if ($id === $_SESSION['user_id']) {
            $error = "Non puoi eliminare te stesso.";
        } else {
            if (deleteUser($id)) {
                $success = "Utente eliminato con successo.";
            } else {
                $error = "Errore nell'eliminazione dell'utente.";
            }
        }
    }
}

if ($action === 'update') {
    // Verifica il token CSRF
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token CSRF non valido.';
    } else {
        $id = intval($_POST['id'] ?? 0);
        $username = sanitizeInput($_POST['username'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $role = sanitizeInput($_POST['role'] ?? '');
        $password = $_POST['password'] ?? ''; // Nuova password (opzionale)
        $sede_id = (isset($_POST['sede_id']) && $_POST['sede_id'] !== '') ? intval($_POST['sede_id']) : null;

        // Validazione dei campi
        if (empty($username) || empty($email) || empty($role)) {
            $error = "Tutti i campi obbligatori (tranne la password) devono essere compilati.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Indirizzo email non valido.";
        } elseif (!in_array($role, ['admin', 'operatore'])) {
            $error = "Ruolo non valido.";
        } else {
            // Controlla se l'utente o l'email esistono già per altri utenti
            $stmt = executeQuery("SELECT * FROM users WHERE (username = ? OR email = ?) AND id != ?", [$username, $email, $id], 'ssi');
            if ($stmt) {
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $error = "Username o email già in uso.";
                }
                $result->free();
                $stmt->close();
            }
            if (empty($error)) {
                $newPassword = !empty($password) ? $password : null;
                // Si presume che updateUser() accetti anche il parametro sede_id
                if (updateUser($id, $username, $email, $role, $newPassword, $sede_id)) {
                    $success = "Utente aggiornato con successo.";
                } else {
                    $error = "Errore nell'aggiornamento dell'utente.";
                }
            }
        }
    }
}

// Recupera la lista degli utenti insieme alla sede (se presente)
$query = "SELECT u.id, u.username, u.email, u.role, u.created_at, u.sede_id, s.nome as sede_nome 
          FROM users u 
          LEFT JOIN sedi s ON u.sede_id = s.id 
          ORDER BY u.created_at DESC";
$stmt = executeQuery($query, [], '');
$users = [];
if ($stmt) {
    $resultUsers = $stmt->get_result();
    while ($row = $resultUsers->fetch_assoc()) {
        $users[] = $row;
    }
    $resultUsers->free();
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Gestione Utenti - CRM Admin</title>
    <!-- Meta viewport per la responsività -->
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- SB Admin 2 CSS -->
    <link href="<?php echo sanitizeForHTML($base_url); ?>theme/css/sb-admin-2.min.css?v=2.5" rel="stylesheet">
    <!-- FontAwesome -->
    <link href="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <!-- Custom CSS -->
    <link href="<?php echo sanitizeForHTML($base_url); ?>styles.css?v=2.5" rel="stylesheet">
    <!-- jQuery UI CSS per l'autocomplete (se necessario) -->
    <link rel="stylesheet" href="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/jquery-ui/jquery-ui.min.css">
    <style>
        /* Stili personalizzati per migliorare la visibilità su dispositivi mobili */
        @media (max-width: 767.98px) {
            .form-row { flex-direction: column; }
            .form-group { width: 100%; }
            .table-responsive { overflow-x: auto; }
            .modal-dialog { max-width: 90%; margin: 1.75rem auto; }
            .user-card { margin-bottom: 1rem; }
        }
    </style>
</head>
<body id="page-top">

    <!-- Page Wrapper -->
    <div id="wrapper">

        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>
        <!-- End of Sidebar -->

        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">

            <!-- Main Content -->
            <div id="content">

                <!-- Topbar -->
                <?php include 'topbar.php'; ?>
                <!-- End of Topbar -->

                <!-- Begin Page Content -->
                <div class="container-fluid">

                    <!-- Titolo della Pagina -->
                    <h1 class="h3 mb-4 text-gray-800">Gestione Utenti</h1>

                    <!-- Messaggi di Successo o Errore -->
                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php echo sanitizeForHTML($success); ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Chiudi">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo sanitizeForHTML($error); ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Chiudi">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>

                    <!-- Form di Creazione Utente -->
                    <div class="card mb-4">
                        <div class="card-header">
                            Crea Nuovo Utente
                        </div>
                        <div class="card-body">
                            <form method="POST" action="gestione_utenti.php">
                                <input type="hidden" name="action" value="create">
                                <?php csrfInputField(); ?>
                                <div class="row mb-3">
                                    <div class="col-12 col-md-3 mb-3">
                                        <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                                        <input type="text" name="username" id="username" class="form-control" required value="<?php echo isset($_POST['username']) ? sanitizeForHTML($_POST['username']) : ''; ?>">
                                    </div>
                                    <div class="col-12 col-md-3 mb-3">
                                        <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                        <input type="email" name="email" id="email" class="form-control" required value="<?php echo isset($_POST['email']) ? sanitizeForHTML($_POST['email']) : ''; ?>">
                                    </div>
                                    <div class="col-12 col-md-3 mb-3">
                                        <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                                        <input type="password" name="password" id="password" class="form-control" required>
                                    </div>
                                    <div class="col-12 col-md-3 mb-3">
                                        <label for="role" class="form-label">Ruolo <span class="text-danger">*</span></label>
                                        <select name="role" id="role" class="form-control" required>
                                            <option value="operatore" <?php echo (isset($_POST['role']) && $_POST['role'] === 'operatore') ? 'selected' : ''; ?>>Operatore</option>
                                            <option value="admin" <?php echo (isset($_POST['role']) && $_POST['role'] === 'admin') ? 'selected' : ''; ?>>Admin</option>
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-3 mb-3">
                                        <label for="sede" class="form-label">Sede</label>
                                        <select name="sede_id" id="sede" class="form-control">
                                            <option value="">Tutte le sedi</option>
                                            <?php foreach ($sediList as $sede): ?>
                                                <option value="<?php echo sanitizeForHTML($sede['id']); ?>"
                                                    <?php echo (isset($_POST['sede_id']) && $_POST['sede_id'] == $sede['id']) ? 'selected' : ''; ?>>
                                                    <?php echo sanitizeForHTML($sede['nome']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-success">Crea Utente</button>
                            </form>
                        </div>
                    </div>

                    <!-- Tabella degli Utenti per Desktop -->
                    <div class="card d-none d-md-block">
                        <div class="card-header">
                            Lista Utenti
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped table-hover">
                                    <thead class="thead-dark">
                                        <tr>
                                            <th>ID</th>
                                            <th>Username</th>
                                            <th>Email</th>
                                            <th>Ruolo</th>
                                            <th>Sede</th>
                                            <th>Creato il</th>
                                            <th>Azioni</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($users as $user): ?>
                                            <tr>
                                                <td><?php echo sanitizeForHTML($user['id']); ?></td>
                                                <td><?php echo sanitizeForHTML($user['username']); ?></td>
                                                <td><?php echo sanitizeForHTML($user['email']); ?></td>
                                                <td><?php echo sanitizeForHTML(ucfirst($user['role'])); ?></td>
                                                <td>
                                                    <?php
                                                    if (!empty($user['sede_nome'])) {
                                                        echo sanitizeForHTML($user['sede_nome']);
                                                    } else {
                                                        echo "Tutte le sedi";
                                                    }
                                                    ?>
                                                </td>
                                                <td><?php echo sanitizeForHTML($user['created_at']); ?></td>
                                                <td>
                                                    <!-- Pulsante per Modificare -->
                                                    <div class="d-flex align-items-center gap-2">
                                                    <button class="table-action-icon edit-btn"
                                                        data-id="<?php echo $user['id']; ?>"
                                                        data-username="<?php echo sanitizeForHTML($user['username']); ?>"
                                                        data-email="<?php echo sanitizeForHTML($user['email']); ?>"
                                                        data-role="<?php echo sanitizeForHTML($user['role']); ?>"
                                                        data-sede_id="<?php echo isset($user['sede_id']) ? $user['sede_id'] : ''; ?>"
                                                        title="Modifica">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                        <form method="POST" action="gestione_utenti.php" style="display:inline-block;" onsubmit="return confirm('Sei sicuro di voler eliminare questo utente?');">
                                                            <input type="hidden" name="action" value="delete">
                                                            <?php csrfInputField(); ?>
                                                            <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                                            <button type="submit" class="table-action-icon" title="Elimina">
                                                                <i class="fas fa-trash-alt"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($users)): ?>
                                            <tr>
                                                <td colspan="7" class="text-center">Nessun utente trovato.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Lista degli Utenti in Formato Card per Mobile -->
                    <div class="card d-block d-md-none">
                        <div class="card-header">
                            Lista Utenti
                        </div>
                        <div class="card-body">
                            <?php if (!empty($users)): ?>
                                <?php foreach ($users as $user): ?>
                                    <div class="card mb-3 user-card">
                                        <div class="card-body">
                                            <h5 class="card-title">
                                                <?php echo sanitizeForHTML($user['username']); ?>
                                                <span class="badge badge-<?php echo ($user['role'] === 'admin') ? 'warning' : 'info'; ?>">
                                                    <?php echo ucfirst($user['role']); ?>
                                                </span>
                                            </h5>
                                            <p class="card-text"><strong>Email:</strong> <?php echo sanitizeForHTML($user['email']); ?></p>
                                            <p class="card-text"><strong>ID:</strong> <?php echo sanitizeForHTML($user['id']); ?></p>
                                            <p class="card-text"><strong>Sede:</strong> 
                                                <?php 
                                                if (!empty($user['sede_nome'])) {
                                                    echo sanitizeForHTML($user['sede_nome']);
                                                } else {
                                                    echo "Tutte le sedi";
                                                }
                                                ?>
                                            </p>
                                            <p class="card-text"><strong>Creato il:</strong> <?php echo sanitizeForHTML($user['created_at']); ?></p>
                                            <div class="d-flex align-items-center gap-2">
                                            <button class="table-action-icon edit-btn"
                                                data-id="<?php echo $user['id']; ?>"
                                                data-username="<?php echo sanitizeForHTML($user['username']); ?>"
                                                data-email="<?php echo sanitizeForHTML($user['email']); ?>"
                                                data-role="<?php echo sanitizeForHTML($user['role']); ?>"
                                                data-sede_id="<?php echo isset($user['sede_id']) ? $user['sede_id'] : ''; ?>"
                                                title="Modifica">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                <form method="POST" action="gestione_utenti.php" style="display:inline-block;" onsubmit="return confirm('Sei sicuro di voler eliminare questo utente?');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <?php csrfInputField(); ?>
                                                    <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                                    <button type="submit" class="table-action-icon" title="Elimina">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-center">Nessun utente trovato.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
                <!-- End of Page Content -->

            </div>
            <!-- End of Main Content -->

            <!-- Footer -->
            <?php include 'footer.php'; ?>
            <!-- End of Footer -->

        </div>
        <!-- End of Content Wrapper -->

    </div>
    <!-- End of Page Wrapper -->

    <!-- Modal per Modificare Utente -->
    <div class="modal fade" id="editUserModal" tabindex="-1" role="dialog" aria-labelledby="editUserModalLabel" aria-hidden="true">
      <div class="modal-dialog" role="document">
        <form method="POST" action="gestione_utenti.php">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="editUserModalLabel">Modifica Utente</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Chiudi">
                    <span aria-hidden="true">&times;</span>
                </button>
              </div>
              <div class="modal-body">
                  <input type="hidden" name="action" value="update">
                  <?php csrfInputField(); ?>
                  <input type="hidden" name="id" id="editUserId">
                  <div class="form-group">
                      <label for="editUsername" class="form-label">Username <span class="text-danger">*</span></label>
                      <input type="text" name="username" id="editUsername" class="form-control" required>
                  </div>
                  <div class="form-group">
                      <label for="editEmail" class="form-label">Email <span class="text-danger">*</span></label>
                      <input type="email" name="email" id="editEmail" class="form-control" required>
                  </div>
                  <div class="form-group">
                      <label for="editRole" class="form-label">Ruolo <span class="text-danger">*</span></label>
                      <select name="role" id="editRole" class="form-control" required>
                          <option value="operatore">Operatore</option>
                          <option value="admin">Admin</option>
                      </select>
                  </div>
                  <div class="form-group">
                      <label for="editSede" class="form-label">Sede</label>
                      <select name="sede_id" id="editSede" class="form-control">
                          <option value="">Tutte le sedi</option>
                          <?php foreach ($sediList as $sede): ?>
                              <option value="<?php echo sanitizeForHTML($sede['id']); ?>">
                                  <?php echo sanitizeForHTML($sede['nome']); ?>
                              </option>
                          <?php endforeach; ?>
                      </select>
                  </div>
                  <div class="form-group">
                      <label for="editPassword" class="form-label">Nuova Password</label>
                      <input type="password" name="password" id="editPassword" class="form-control" placeholder="Lascia vuoto per non cambiare">
                  </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Chiudi</button>
                <button type="submit" class="btn btn-primary">Salva Modifiche</button>
              </div>
            </div>
        </form>
      </div>
    </div>

    <!-- Bootstrap core JavaScript-->
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <!-- Core plugin JavaScript-->
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/jquery-easing/jquery.easing.min.js"></script>
    <!-- SB Admin 2 JavaScript-->
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/js/sb-admin-2.min.js"></script>
    <!-- jQuery UI per l'autocomplete (se necessario) -->
    <script src="<?php echo sanitizeForHTML($base_url); ?>theme/vendor/jquery-ui/jquery-ui.min.js"></script>
    <script>
        $(document).ready(function(){
            // Gestione del pulsante Modifica
            $('.edit-btn').on('click', function(){
                var id = $(this).data('id');
                var username = $(this).data('username');
                var email = $(this).data('email');
                var role = $(this).data('role');
                var sede_id = $(this).data('sede_id');

                // Imposta i valori nei campi del modal
                $('#editUserId').val(id);
                $('#editUsername').val(username);
                $('#editEmail').val(email);
                $('#editRole').val(role);
                $('#editSede').val(sede_id);
                $('#editPassword').val(''); // Pulisce il campo password

                // Mostra il modal
                $('#editUserModal').modal('show');
            });
        });
    </script>
</body>
</html>
