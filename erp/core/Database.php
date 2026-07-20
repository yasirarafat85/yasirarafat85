<?php
/**
 * ---------------------------------------------------------------
 *  Database — পুনঃব্যবহারযোগ্য ডাটাবেস লেয়ার (PDO ভিত্তিক)
 * ---------------------------------------------------------------
 *  যেকোনো module বা পেজ থেকে এভাবে ব্যবহার করা যাবে:
 *
 *    $db = Database::getInstance();
 *    $users = $db->fetchAll("SELECT * FROM users WHERE status = ?", [1]);
 *    $one   = $db->fetch("SELECT * FROM users WHERE id = ?", [$id]);
 *    $id    = $db->insert('users', ['name' => 'Rahim', 'email' => 'a@b.com']);
 *    $db->update('users', ['name' => 'Karim'], 'id = ?', [$id]);
 *    $db->delete('users', 'id = ?', [$id]);
 *
 *  এভাবে নতুন প্রজেক্ট বানানোর সময় SQL নিয়ে বারবার ভাবতে হবে না।
 */
class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;

    private function __construct()
    {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            if (APP_DEBUG) {
                die('Database connection failed: ' . $e->getMessage());
            }
            die('ডাটাবেসে সংযোগ ব্যর্থ হয়েছে। অনুগ্রহ করে কনফিগ চেক করুন।');
        }
    }

    /** Singleton — পুরো অ্যাপে একটাই কানেকশন */
    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    /** সরাসরি PDO অবজেক্ট দরকার হলে */
    public function pdo(): PDO
    {
        return $this->pdo;
    }

    /** যেকোনো query চালিয়ে statement ফেরত দেয় */
    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /** একাধিক row (array) */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    /** একটি row (array বা null) */
    public function fetch(string $sql, array $params = [])
    {
        $row = $this->query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** একটি single value (যেমন COUNT) */
    public function scalar(string $sql, array $params = [])
    {
        return $this->query($sql, $params)->fetchColumn();
    }

    /** সহজ INSERT — নতুন row এর id ফেরত দেয় */
    public function insert(string $table, array $data): int
    {
        $cols        = array_keys($data);
        $placeholders = array_map(fn($c) => ':' . $c, $cols);
        $sql = "INSERT INTO `$table` (`" . implode('`, `', $cols) . "`) "
             . "VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $this->pdo->prepare($sql);
        foreach ($data as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->execute();
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * সহজ UPDATE — কতগুলো row বদলালো তা ফেরত দেয়।
     * সব placeholder positional (?) রাখা হয়েছে যাতে WHERE-এর ?-এর সাথে
     * মিশে সমস্যা না হয় (named ও positional মেশানো PDO-তে অনির্ভরযোগ্য)।
     */
    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $set = implode(', ', array_map(fn($c) => "`$c` = ?", array_keys($data)));
        $sql = "UPDATE `$table` SET $set WHERE $where";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge(array_values($data), array_values($whereParams)));
        return $stmt->rowCount();
    }

    /** সহজ DELETE */
    public function delete(string $table, string $where, array $params = []): int
    {
        $stmt = $this->pdo->prepare("DELETE FROM `$table` WHERE $where");
        $stmt->execute($params);
        return $stmt->rowCount();
    }
}
