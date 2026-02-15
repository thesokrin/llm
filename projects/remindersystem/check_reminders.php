#!/usr/bin/env php
<?php

/**
 * Reminder Notification Checker
 * 
 * This script should be run every minute via cron:
 * * * * * * /usr/bin/php /path/to/check_reminders.php
 * 
 * It checks for due reminders and outputs them in a format
 * that can be sent to Discord users
 */

// Adjust these paths to match your setup
$ROOT = dirname(__FILE__);
require_once $ROOT . '/reminder_system.php';
require_once $ROOT . '/discord_integration.php';

// Path to your reminders storage file
$storageFile = $ROOT . '/discord_reminders.json';

// Initialize the bot
$bot = new DiscordReminderBot($storageFile);

// Get due notifications
$notifications = $bot->getDiscordNotifications();

// Output notifications (you can process these however you need)
foreach ($notifications as $notif) {
    // Output in a format your bot can process
    // Format: USER_ID|MESSAGE
    echo "{$notif['user_id']}|{$notif['message']}\n";
    
    // Or if you have a function to send Discord DMs:
    // sendDiscordDM($notif['user_id'], $notif['message']);
    
    // Or write to a log file:
    // file_put_contents($ROOT . '/reminder_notifications.log', 
    //     date('Y-m-d H:i:s') . " - User {$notif['user_id']}: {$notif['message']}\n", 
    //     FILE_APPEND);
}

// If no notifications, output nothing (silent)
// This makes it easy to use in cron without getting empty emails

?>
