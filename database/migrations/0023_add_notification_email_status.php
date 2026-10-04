<?php

declare(strict_types=1);

use Muh\Core\DB;

/**
 * Notification center send-status (B2): track whether the automatic e-mail for
 * a notification was dispatched (sent / failed) and when, so the notification
 * center can surface reliable e-mail delivery state per item.
 */
return new class {
    public function up(): void
    {
        $cols = [];
        foreach (DB::select('SHOW COLUMNS FROM notifications') as $c) {
            $cols[strtolower((string) $c['Field'])] = true;
        }
        if (!isset($cols['email_status'])) {
            DB::execute('ALTER TABLE notifications ADD COLUMN email_status VARCHAR(16) NULL DEFAULT NULL AFTER is_read');
        }
        if (!isset($cols['email_sent_at'])) {
            DB::execute('ALTER TABLE notifications ADD COLUMN email_sent_at DATETIME NULL DEFAULT NULL AFTER email_status');
        }
    }

    public function down(): void
    {
        DB::execute('ALTER TABLE notifications DROP COLUMN IF EXISTS email_status');
        DB::execute('ALTER TABLE notifications DROP COLUMN IF EXISTS email_sent_at');
    }
};
