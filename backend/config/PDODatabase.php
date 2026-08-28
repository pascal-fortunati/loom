<?php

// Class Database - Gère la connexion à MySQL via PDO et fournit des méthodes pour les requêtes préparées
class Database
{
    // Instance statique pour le pattern Singleton
    private static $instance = null;
    
    // Connexion PDO
    private $connection;

    /**
     * Constructeur privé - Établit la connexion à la base de données
     */
    private function __construct()
    {
        try {
            // Crée une connexion PDO avec des options de sécurité
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
            // Force le jeu de caractères de la session PDO, indépendamment des
            // réglages client MySQL du conteneur.
            $this->connection->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

            if (APP_DEBUG) {
                error_log('[Database] Connexion établie avec succès');
            }
        } catch (PDOException $e) {
            error_log('[Database] Erreur de connexion : ' . $e->getMessage());
            http_response_code(500);
            die(json_encode([
                'success' => false,
                'message' => 'Erreur de connexion à la base de données'
            ]));
        }
    }

    /**
     * Récupère l'instance unique de la base de données (Singleton)
     * 
     * @return Database L'instance unique de connexion
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Retourne l'objet PDO pour effectuer des requêtes
     * 
     * @return PDO La connexion PDO
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Exécute une requête préparée de manière simple
     * 
     * @param string $sql La requête SQL avec des placeholders
     * @param array $params Les paramètres à injecter
     * @return PDOStatement Le résultat de la requête
     */
    public function execute($sql, $params = [])
    {
        try {
            $statement = $this->connection->prepare($sql);
            $statement->execute($params);
            return $statement;
        } catch (PDOException $e) {
            error_log('[Database] Erreur d\'exécution : ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Récupère une ligne unique
     * 
     * @param string $sql La requête SQL
     * @param array $params Les paramètres
     * @return array|null La ligne trouvée ou null
     */
    public function fetchOne($sql, $params = [])
    {
        // PDO::fetch() retourne false si aucune ligne n'est trouvée.
        // On normalise ici vers null pour garder un contrat clair dans les modèles.
        $result = $this->execute($sql, $params)->fetch();
        return $result === false ? null : $result;
    }

    /**
     * Récupère toutes les lignes
     * 
     * @param string $sql La requête SQL
     * @param array $params Les paramètres
     * @return array Tableau de lignes
     */
    public function fetchAll($sql, $params = [])
    {
        return $this->execute($sql, $params)->fetchAll();
    }

    /**
     * Retourne l'ID de la dernière ligne insérée
     * 
     * @return string L'ID inséré
     */
    public function lastInsertId()
    {
        return $this->connection->lastInsertId();
    }

    /**
     * Empêche le clonage de l'instance (Singleton)
     */
    private function __clone()
    {
    }

    /**
     * Empêche la sérialisation de l'instance (Singleton)
     */
    public function __wakeup()
    {
    }
}
