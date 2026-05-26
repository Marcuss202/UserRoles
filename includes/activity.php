<?php
function log_activity(PDO $pdo, ?int $userId, string $action, string $entityType, ?int $entityId, ?string $fieldName, ?string $oldValue, ?string $newValue): void {
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO activity_log (user_id, action, entity_type, entity_id, field_name, old_value, new_value)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $userId,
            $action,
            $entityType,
            $entityId,
            $fieldName,
            $oldValue,
            $newValue,
        ]);
    } catch (Throwable $e) {
        // Log failures should not break the main workflow.
    }
}
