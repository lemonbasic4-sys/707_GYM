<?php
/**
 * 707 GYM - Authentication Class
 * Handles admin login, logout, and password management.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';

class Auth
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Attempt to log in an admin by username or email.
     *
     * @param string $username  Username or email
     * @param string $password  Plain-text password
     * @return bool
     */
    public function login(string $username, string $password): bool
    {
        $username = trim($username);

        $stmt = $this->db->prepare(
            "SELECT id, username, email, password, name
               FROM admins
              WHERE (username = :username OR email = :email)
              LIMIT 1"
        );
        $stmt->execute([':username' => $username, ':email' => $username]);
        $admin = $stmt->fetch();

        if (!$admin || !password_verify($password, $admin['password'])) {
            return false;
        }

        // Prevent session fixation
        regenerateSession();

        $_SESSION['logged_in']   = true;
        $_SESSION['admin_id']    = $admin['id'];
        $_SESSION['admin_name']  = $admin['name'];
        $_SESSION['admin_email'] = $admin['email'];
        $_SESSION['admin_username'] = $admin['username'];

        // Update last_login timestamp
        $upd = $this->db->prepare(
            "UPDATE admins SET last_login = NOW() WHERE id = :id"
        );
        $upd->execute([':id' => $admin['id']]);

        return true;
    }

    /**
     * Destroy the current session and redirect to login.
     */
    public function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }

    /**
     * Change an admin's password after verifying the current one.
     *
     * @param int    $adminId      Admin primary key
     * @param string $currentPass  Current plain-text password
     * @param string $newPass      New plain-text password
     * @return bool|string  true on success, error message string on failure
     */
    public function changePassword(int $adminId, string $currentPass, string $newPass): bool|string
    {
        $stmt = $this->db->prepare(
            "SELECT password FROM admins WHERE id = :id LIMIT 1"
        );
        $stmt->execute([':id' => $adminId]);
        $admin = $stmt->fetch();

        if (!$admin) {
            return 'Admin not found.';
        }

        if (!password_verify($currentPass, $admin['password'])) {
            return 'Current password is incorrect.';
        }

        if (strlen($newPass) < 8) {
            return 'New password must be at least 8 characters.';
        }

        $hashed = password_hash($newPass, PASSWORD_BCRYPT);
        $upd = $this->db->prepare(
            "UPDATE admins SET password = :p WHERE id = :id"
        );
        $upd->execute([':p' => $hashed, ':id' => $adminId]);

        return true;
    }

    /**
     * Get admin details by ID.
     *
     * @param int $adminId
     * @return array|null
     */
    public function getAdmin(int $adminId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT id, username, email, name, last_login, created_at
               FROM admins WHERE id = :id LIMIT 1"
        );
        $stmt->execute([':id' => $adminId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
