<?php
declare(strict_types=1);

namespace CouncilLibrary\Service;

class TaskClaimer
{
    public function __construct(private \PDO $pdo) {}

    /**
     * Atomic claim: UPDATE ... WHERE status='queued' LIMIT 1.
     * Returns the claimed task_id or null if nothing available.
     */
    public function claimTask(string $workerId, string $agentSlug): ?array
    {
        // Step 1: Find a queued task with SKIP LOCKED
        $stmt = $this->pdo->prepare(
            "SELECT task_id FROM task_queue
             WHERE target_agent_slug = :agent AND status = 'queued'
             ORDER BY priority DESC, created_at ASC
             LIMIT 1 FOR UPDATE SKIP LOCKED"
        );
        $stmt->execute(['agent' => $agentSlug]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        // Step 2: Atomic claim — affected_rows check
        $update = $this->pdo->prepare(
            "UPDATE task_queue
             SET status = 'claimed', claimed_by_worker_id = :wid, claimed_at = NOW()
             WHERE task_id = :tid AND status = 'queued'
             LIMIT 1"
        );
        $update->execute(['wid' => $workerId, 'tid' => $row['task_id']]);

        if ($update->rowCount() === 0) {
            return null; // Another worker claimed it first
        }

        return $row;
    }

    /**
     * Mark a task complete with results.
     */
    public function completeTask(string $taskId, array $result): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE task_queue
             SET status = 'completed', completed_at = NOW(), result_json = :result
             WHERE task_id = :tid"
        );
        $stmt->execute(['tid' => $taskId, 'result' => json_encode($result)]);
    }

    /**
     * Mark a task dead-letter with error.
     */
    public function deadLetterTask(string $taskId, string $error, int $maxRetries = 3): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE task_queue
             SET status = CASE WHEN retry_count >= :max THEN 'dead_letter' ELSE 'queued' END,
                 error_message = :err, retry_count = retry_count + 1
             WHERE task_id = :tid"
        );
        $stmt->execute(['tid' => $taskId, 'err' => $error, 'max' => $maxRetries]);
    }
}
