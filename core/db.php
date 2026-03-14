<?php
/*
 * Private Community Website Software
 * Copyright (c) 2026 Ole Rasmussen
 *
 * Free to use, copy, modify, fork, and distribute.
 *
 * NOT allowed:
 * - Selling this software
 * - Redistributing it for profit
 *
 * Provided "AS IS" without warranty.
 */
/**
 * db.php — Database connection (PDO singleton)
 *
 * Usage:
 *   $pdo = db();
 *   $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
 */

declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        DB_HOST,
        DB_PORT,
        DB_NAME,
        DB_CHARSET
    );

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_FOUND_ROWS   => true,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        // Keep MySQL session timezone in sync with PHP (UTC) so that
        // CURRENT_TIMESTAMP values round-trip correctly through strtotime().
        $pdo->exec("SET time_zone = '+00:00'");
    } catch (PDOException $e) {
        if (SITE_DEBUG) {
            throw $e;
        }
        // Avoid leaking credentials in production
        error_log('Database connection failed: ' . $e->getMessage());
        http_response_code(503);
        die('Service temporarily unavailable. Please try again later.');
    }

    return $pdo;
}

/**
 * db_query() — Execute a prepared statement and return all rows.
 *
 * @param string $sql
 * @param array  $params  Positional or named parameters
 * @return array
 */
function db_query(string $sql, array $params = []): array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * db_row() — Return a single row or null.
 *
 * @param string $sql
 * @param array  $params
 * @return array|null
 */
function db_row(string $sql, array $params = []): ?array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/**
 * db_val() — Return a single scalar value or null.
 *
 * @param string $sql
 * @param array  $params
 * @return mixed
 */
function db_val(string $sql, array $params = []): mixed
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $val = $stmt->fetchColumn();
    return $val === false ? null : $val;
}

/**
 * db_exec() — Execute a write statement and return affected rows.
 *
 * @param string $sql
 * @param array  $params
 * @return int  affected rows
 */
function db_exec(string $sql, array $params = []): int
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

/**
 * db_insert() — Insert a row and return the last insert id.
 *
 * @param string $sql
 * @param array  $params
 * @return int|string  last insert ID
 */
function db_insert(string $sql, array $params = []): int|string
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return db()->lastInsertId();
}
