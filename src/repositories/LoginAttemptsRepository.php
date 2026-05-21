<?php
declare(strict_types=1);

final class LoginAttemptsRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function find(string $identifierHash): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT attempts, locked_until
             FROM login_attempts
             WHERE identifier_hash = :identifier_hash'
        );
        $stmt->execute(['identifier_hash' => $identifierHash]);
        return $stmt->fetch() ?: null;
    }

    public function recordFailure(string $identifierHash, int $maxAttempts, int $lockSeconds): void
    {
        $lockedUntil = date('Y-m-d H:i:s', time() + $lockSeconds);

        $stmt = $this->db->prepare(
            'INSERT INTO login_attempts (identifier_hash, attempts, locked_until, last_attempt_at)
             VALUES (:identifier_hash, 1, NULL, CURRENT_TIMESTAMP)
             ON CONFLICT (identifier_hash)
             DO UPDATE SET attempts = CASE
                               WHEN login_attempts.locked_until IS NOT NULL
                                    AND login_attempts.locked_until > CURRENT_TIMESTAMP
                                   THEN login_attempts.attempts
                               WHEN login_attempts.locked_until IS NOT NULL
                                    AND login_attempts.locked_until <= CURRENT_TIMESTAMP
                                   THEN 1
                               ELSE login_attempts.attempts + 1
                           END,
                           locked_until = CASE
                               WHEN login_attempts.locked_until IS NOT NULL
                                    AND login_attempts.locked_until > CURRENT_TIMESTAMP
                                   THEN login_attempts.locked_until
                               WHEN login_attempts.locked_until IS NOT NULL
                                    AND login_attempts.locked_until <= CURRENT_TIMESTAMP
                                   THEN NULL
                               WHEN login_attempts.attempts + 1 >= :max_attempts
                                   THEN :locked_until
                               ELSE NULL
                           END,
                           last_attempt_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([
            'identifier_hash' => $identifierHash,
            'max_attempts' => $maxAttempts,
            'locked_until' => $lockedUntil,
        ]);
    }

    public function recordAudit(string $emailHash, string $ipHash, string $userAgent, string $reason): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO login_audit (email_hash, ip_hash, user_agent, reason)
             VALUES (:email_hash, :ip_hash, :user_agent, :reason)'
        );
        $stmt->execute([
            'email_hash' => $emailHash,
            'ip_hash' => $ipHash,
            'user_agent' => substr($userAgent, 0, 255),
            'reason' => $reason,
        ]);
    }

    public function clear(string $identifierHash): void
    {
        $stmt = $this->db->prepare('DELETE FROM login_attempts WHERE identifier_hash = :identifier_hash');
        $stmt->execute(['identifier_hash' => $identifierHash]);
    }

    public function isLocked(?array $attempt): bool
    {
        if (!$attempt || empty($attempt['locked_until'])) {
            return false;
        }

        return strtotime((string) $attempt['locked_until']) > time();
    }
}
