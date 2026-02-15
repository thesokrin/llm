# Complete Implementation Guide

## File Structure

```
/home/shayne/vbot/chatbot/
├── reminder_system.php          # Core reminder system with JSON storage
├── discord_integration.php      # Discord command handling
├── discord_reminders.json       # Storage file (auto-created)
├── check_reminders.php          # Cron job script
└── legacy/
    └── ai.php                   # Your main bot script
```

## Step 1: Update Your ai.php

```php
<?php
// At the top with other requires
$ROOT = dirname(__DIR__); // Go up one level from legacy/
require_once $ROOT . '/reminder_system.php';
require_once $ROOT . '/discord_integration.php';

// Initialize the bot ONCE (outside any functions)
$discordBot = new DiscordReminderBot($ROOT . '/discord_reminders.json');

// In your function where the switch statement is
function yourFunction($mode, $input, $author) {
    global $discordBot;  // Access the global bot instance
    
    switch($mode) {
        case "--reminder":
            $response = $discordBot->handleCommand($input, $author);
            break;
            
        case "--list-reminders":
            $response = $discordBot->listReminders($author);
            break;
            
        // ... other cases
    }
    
    return $response;
}
```

## Step 2: Set Up Cron Job for Notifications

Edit your crontab:
```bash
crontab -e
```

Add this line (adjust paths as needed):
```bash
* * * * * /usr/bin/php /home/shayne/vbot/chatbot/check_reminders.php >> /home/shayne/vbot/chatbot/reminder_cron.log 2>&1
```

This will check every minute for due reminders.

## Step 3: Process Notifications in Your Bot

Modify `check_reminders.php` to integrate with your Discord bot's message sending:

```php
<?php
require_once __DIR__ . '/reminder_system.php';
require_once __DIR__ . '/discord_integration.php';

// Your Discord bot initialization code here
// require_once __DIR__ . '/your_discord_bot.php';

$bot = new DiscordReminderBot(__DIR__ . '/discord_reminders.json');
$notifications = $bot->getDiscordNotifications();

foreach ($notifications as $notif) {
    // Replace this with your actual Discord DM sending function
    sendDiscordDM($notif['user_id'], $notif['message']);
    
    // Or log to file for processing
    // file_put_contents('notifications.txt', 
    //     json_encode($notif) . "\n", FILE_APPEND);
}
?>
```

## Commands Your Users Can Use

### Natural Language (no prefix needed)
- "i need to call barry next friday at around noon"
- "remind me to take out trash tomorrow at 7pm"
- "check the oven in 30 minutes"
- "submit report next monday at 9am"

### Explicit Commands
- `!remind [task] [when]` - Create a reminder
- `!list` - Show all reminders
- `!delete [number]` - Cancel a reminder
- `!help` - Show help message

## Storage Format

Reminders are stored in `discord_reminders.json`:

```json
[
  {
    "id": "rem_678a1b2c3d4e5f",
    "user_id": 123456789,
    "message": "call barry",
    "cron": "0 11 16 1 *",
    "datetime": "2026-01-16 12:00:00",
    "remind_before": 60,
    "created_at": "2026-01-13 15:30:00",
    "notified": false
  }
]
```

## Troubleshooting

### "Class ReminderBot not found"
- Make sure you're requiring `reminder_system.php` BEFORE `discord_integration.php`
- Check file paths are correct

### "Call to private method"
- Make sure you're using the updated `reminder_system.php` with `protected` methods

### Reminders not firing
- Check cron job is running: `crontab -l`
- Check cron log: `tail -f /home/shayne/vbot/chatbot/reminder_cron.log`
- Verify `check_reminders.php` has execution permission: `chmod +x check_reminders.php`

### JSON file permission errors
- Make sure the directory is writable: `chmod 755 /home/shayne/vbot/chatbot/`
- If needed, create the file manually: `touch discord_reminders.json && chmod 666 discord_reminders.json`

## Testing

Run the test script:
```bash
cd /home/shayne/vbot/chatbot
php test_reminder.php
```

This will:
1. Create test reminders
2. List them
3. Delete one
4. Show the storage format
5. Clean up after itself

## Advanced: Database Storage (Optional)

If you want to use a database instead of JSON:

```php
class DatabaseReminderSystem extends ReminderSystem {
    private $db;
    
    public function __construct($dbConnection) {
        $this->db = $dbConnection;
    }
    
    protected function saveReminder($reminder) {
        $stmt = $this->db->prepare(
            "INSERT INTO reminders (id, user_id, message, cron, datetime, remind_before, created_at) 
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        
        $id = uniqid('rem_', true);
        $datetime = $reminder['datetime'] instanceof DateTime 
            ? $reminder['datetime']->format('Y-m-d H:i:s') 
            : $reminder['datetime'];
            
        $stmt->execute([
            $id,
            $reminder['user_id'],
            $reminder['message'],
            $reminder['cron'],
            $datetime,
            $reminder['remind_before'],
            $reminder['created_at']
        ]);
        
        return $id;
    }
    
    protected function getRemindersForUser($userId) {
        $stmt = $this->db->prepare(
            "SELECT * FROM reminders WHERE user_id = ? ORDER BY datetime ASC"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // ... implement other methods similarly
}
```

## Example Discord Bot Integration

```php
// In your Discord bot message handler
function onDiscordMessage($message, $userId, $channelId) {
    global $discordBot;
    
    $response = $discordBot->handleCommand($message, $userId);
    
    if ($response !== null) {
        // Send response back to Discord
        sendDiscordMessage($channelId, $response);
    }
}

// In your cron job or background worker
function checkReminders() {
    global $discordBot;
    
    $notifications = $discordBot->getDiscordNotifications();
    
    foreach ($notifications as $notif) {
        sendDiscordDM($notif['user_id'], $notif['message']);
    }
}
```

## Support

If you encounter issues:
1. Check the storage file exists and is writable
2. Verify file paths are correct
3. Check PHP error logs
4. Run test_reminder.php to verify basic functionality
5. Make sure cron job is actually running
