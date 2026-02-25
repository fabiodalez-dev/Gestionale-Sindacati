<?php
// user_functions.php

if (!function_exists('createUser')) {
    /**
     * Crea un nuovo utente.
     *
     * @param string $username
     * @param string $email
     * @param string $password
     * @param string $role
     * @param int|null $sede_id Se NULL o vuoto, l'utente sarà associato a tutte le sedi.
     * @return bool
     */
    function createUser($username, $email, $password, $role, $sede_id = null) {
        global $mysqli;
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $query = "INSERT INTO users (username, email, password, role, sede_id) VALUES (?, ?, ?, ?, ?)";
        // Se $sede_id è vuoto, passiamo NULL
        $sede_id = ($sede_id === '' || $sede_id === null) ? null : intval($sede_id);
        return executeQuery($query, [$username, $email, $hashedPassword, $role, $sede_id], 'ssssi') !== false;
    }
}

if (!function_exists('deleteUser')) {
    function deleteUser($id) {
        global $mysqli;
        $query = "DELETE FROM users WHERE id = ?";
        return executeQuery($query, [$id], 'i') !== false;
    }
}

if (!function_exists('updateUser')) {
    /**
     * Aggiorna i dati di un utente.
     *
     * @param int $id
     * @param string $username
     * @param string $email
     * @param string $role
     * @param string|null $password Se fornita, verrà aggiornata anche la password.
     * @param int|null $sede_id Se NULL o vuoto, l'utente sarà associato a tutte le sedi.
     * @return bool
     */
    function updateUser($id, $username, $email, $role, $password = null, $sede_id = null) {
        global $mysqli;
        // Se $sede_id è vuoto, passiamo NULL
        $sede_id = ($sede_id === '' || $sede_id === null) ? null : intval($sede_id);

        if ($password) {
            // Se viene fornita una nuova password, hashala e includila nell'aggiornamento
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $query = "UPDATE users SET username = ?, email = ?, role = ?, password = ?, sede_id = ? WHERE id = ?";
            return executeQuery($query, [$username, $email, $role, $hashedPassword, $sede_id, $id], 'ssssii') !== false;
        } else {
            // Altrimenti, aggiorna solo username, email, role e sede
            $query = "UPDATE users SET username = ?, email = ?, role = ?, sede_id = ? WHERE id = ?";
            return executeQuery($query, [$username, $email, $role, $sede_id, $id], 'ssssi') !== false;
        }
    }
}
?>
