<?php

/**
 * Discord Bot Integration for Reminder System
 * Handles Discord-specific command parsing and responses
 */

// require_once 'reminder_system.php';


class DiscordReminderBot extends ReminderSystem {
    
    /**
     * Constructor
     * 
     * @param string $storageFile Path to JSON storage file
     */
    public function __construct($storageFile = 'discord_reminders.json') {
        parent::__construct($storageFile);
    }
    
    /**
     * Parse Discord command and route to appropriate function
     * 
     * @param string $message Discord message content
     * @param int $userId Discord user ID
     * @return string Response message for Discord
     */
    public function handleCommand($message, $userId) {
        $message = trim($message);
        $lowerMessage = strtolower($message);
        
        // Command routing
        if ($this->startsWith($lowerMessage, ['!remind', '!reminder', 'remind me'])) {
            return $this->handleCreateReminder($message, $userId);
        }
        
        if ($this->startsWith($lowerMessage, ['!list', '!reminders', 'list reminders', 'my reminders'])) {
            return $this->listReminders($userId);
        }
        
        if ($this->startsWith($lowerMessage, ['!delete', '!cancel', 'delete reminder', 'cancel reminder'])) {
            return $this->handleDeleteReminder($message, $userId);
        }
        
        if ($this->startsWith($lowerMessage, ['!help', 'reminder help'])) {
            return $this->getHelpMessage();
        }
        
        // If no command prefix, treat as natural language reminder
        if ($this->looksLikeReminder($message)) {
            return $this->createReminder($message, $userId);
        }
        
        return null; // Not a reminder command
    }
    
    /**
     * Handle creating a reminder
     */
    private function handleCreateReminder($message, $userId) {
        // Remove command prefix
        $message = preg_replace('/^(!remind|!reminder|remind me)\s*/i', '', $message);
        
        return $this->createReminder($message, $userId);
    }
    
    /**
     * Handle deleting a reminder
     */
    private function handleDeleteReminder($message, $userId) {
        // Extract reminder number or ID
        if (preg_match('/(\d+)/', $message, $matches)) {
            $reminderNum = (int)$matches[1];
            
            $reminders = $this->getRemindersForUser($userId);
            
            if (isset($reminders[$reminderNum - 1])) {
                $reminder = $reminders[$reminderNum - 1];
                return $this->deleteReminder($reminder['id'], $userId);
            } else {
                return "Hmm, I couldn't find reminder #$reminderNum. Try `!list` to see your reminders.";
            }
        }
        
        return "Which reminder should I delete? Use `!list` to see your reminders, then `!delete [number]`.";
    }
    
    /**
     * Get help message
     */
    private function getHelpMessage() {
        return "**Reminder Bot Commands**\n\n" .
               "**Creating Reminders:**\n" .
               "• Just tell me what you need to do naturally!\n" .
               "• `i need to call mom tomorrow at 5pm`\n" .
               "• `remind me to submit report next monday at 9am`\n" .
               "• `check the laundry in 30 minutes`\n\n" .
               "**Managing Reminders:**\n" .
               "• `!list` - Show all your reminders\n" .
               "• `!delete [number]` - Cancel a specific reminder\n\n" .
               "**Tips:**\n" .
               "• I understand times like: noon, midnight, 3pm, 14:30\n" .
               "• I understand dates like: tomorrow, next friday, january 20\n" .
               "• I understand relative times like: in 2 hours, in 30 minutes\n" .
               "• By default, I'll remind you 1 hour before the scheduled time";
    }
    
    /**
     * Check if message starts with any of the given prefixes
     */
    private function startsWith($message, $prefixes) {
        foreach ($prefixes as $prefix) {
            if (strpos($message, $prefix) === 0) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Check if message looks like a reminder request
     */
    private function looksLikeReminder($message) {
        $patterns = [
            '/i (need|have|should|want) to .+(tomorrow|next|this|on|at|in)/i',
            '/(remind|remember|dont forget).+(tomorrow|next|this|on|at|in)/i',
            '/.+(tomorrow|next (monday|tuesday|wednesday|thursday|friday|saturday|sunday)).*at/i',
            '/.+in \d+ (minutes?|hours?|days?)/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Format reminder list for Discord
     */
    public function listReminders($userId) {
        $reminders = $this->getRemindersForUser($userId);
        
        if (empty($reminders)) {
            return "You don't have any reminders set up yet. Just tell me what you need to do and when!";
        }
        
        $response = "**Your Upcoming Reminders:**\n\n";
        
        foreach ($reminders as $i => $reminder) {
            $num = $i + 1;
            $datetime = new DateTime($reminder['datetime']);
            
            // Format date
            $now = new DateTime();
            $diff = $now->diff($datetime);
            
            if ($diff->days === 0) {
                $dateStr = "Today";
            } elseif ($diff->days === 1) {
                $dateStr = "Tomorrow";
            } else {
                $dateStr = $datetime->format('l, M j');
            }
            
            $timeStr = $datetime->format('g:ia');
            
            $response .= "`$num.` $dateStr at $timeStr - **{$reminder['message']}**\n";
        }
        
        $response .= "\n_Use `!delete [number]` to cancel a reminder_";
        
        return $response;
    }
    
    /**
     * Get formatted notifications for Discord
     */
    public function getDiscordNotifications() {
        $notifications = $this->getNotifications();
        $formatted = [];
        
        foreach ($notifications as $notif) {
            $formatted[] = [
                'user_id' => $notif['user_id'],
                'message' => "🔔 " . $notif['message'],
                'reminder_id' => $notif['reminder_id']
            ];
        }
        
        return $formatted;
    }
}

// function reminder($message,$userId) {
    // $discordBot = new DiscordReminderBot('discord_reminders.json');
    // $response = $discordBot->handleCommand($message, $userId);
    // return $response;
// }


// // ====================
// // DISCORD USAGE EXAMPLES
// // ====================

// if (php_sapi_name() === 'cli') {
    // echo "=== DISCORD BOT EXAMPLES ===\n\n";
    
    // $discordBot = new DiscordReminderBot('discord_reminders.json');
    // $userId = 987654321; // Discord user ID
    
    // // Simulate Discord messages
    // $testMessages = [
        // "i need to call barry next friday at around noon",
        // "!remind submit homework tomorrow at 11pm",
        // "remind me to buy milk in 2 hours",
        // "i should walk the dog this saturday at 8am",
        // "!list",
        // "check the cookies in 15 minutes",
        // "!help",
        // "!delete 2"
    // ];
    
    // foreach ($testMessages as $message) {
        // echo "User: $message\n";
        
        // if ($response) {
            // echo "Bot: $response\n";
        // } else {
            // echo "Bot: [Not a reminder command]\n";
        // }
        // echo "\n" . str_repeat("-", 60) . "\n\n";
    // }
    
    // // Show notifications check
    // echo "=== CHECKING FOR DUE NOTIFICATIONS ===\n";
    // $notifications = $discordBot->getDiscordNotifications();
    
    // if (empty($notifications)) {
        // echo "No notifications to send.\n";
    // } else {
        // foreach ($notifications as $notif) {
            // echo "To User {$notif['user_id']}: {$notif['message']}\n";
        // }
    // }
// }

?>