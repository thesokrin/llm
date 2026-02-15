<?php

/**
 * Reminder and Task Management System
 * Stores reminders in crontab-like format
 * Returns user-friendly strings for Discord bot integration
 */

class ReminderSystem {
    
    protected $storageFile;
    
    /**
     * Constructor
     * 
     * @param string $storageFile Path to JSON storage file
     */
    public function __construct($storageFile = 'reminders.json') {
        $this->storageFile = $storageFile;
        
        // Create storage file if it doesn't exist
        if (!file_exists($this->storageFile)) {
            file_put_contents($this->storageFile, json_encode([]));
        }
    }
    
    /**
     * Parse natural language input and create a reminder
     * 
     * @param string $input Natural language reminder text
     * @param int $userId User identifier
     * @return string User-friendly response message
     */
    public function createReminder($input, $userId = null) {
        $parsed = $this->parseNaturalLanguage($input);
        
        if (!$parsed['success']) {
            return "Hmm, I'm having trouble understanding when you want to be reminded. Could you try rephrasing that?";
        }
        
        $reminder = [
            'user_id' => $userId,
            'message' => $parsed['message'],
            'cron' => $parsed['cron'],
            'datetime' => $parsed['datetime'],
            'remind_before' => $parsed['remind_before'],
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        // Store the reminder (you'll implement your storage logic)
        $reminderId = $this->saveReminder($reminder);
        
        return $this->generateConfirmationMessage($reminder);
    }
    
    /**
     * Parse natural language into structured reminder data
     * 
     * @param string $input Natural language text
     * @return array Parsed reminder data
     */
    private function parseNaturalLanguage($input) {
        $result = [
            'success' => false,
            'message' => '',
            'cron' => '',
            'datetime' => null,
            'remind_before' => 60 // default 1 hour before in minutes
        ];
        
        $input = strtolower(trim($input));
        
        // Extract the actual message/task
        $result['message'] = $this->extractMessage($input);
        
        // Parse time/date information
        $dateTime = $this->extractDateTime($input);
        
        if ($dateTime) {
            $result['datetime'] = $dateTime;
            $result['cron'] = $this->convertToCron($dateTime);
            
            // Check for "remind me X before" patterns
            $result['remind_before'] = $this->extractReminderOffset($input);
            
            $result['success'] = true;
        }
        
        return $result;
    }
    
    /**
     * Extract the actual message/task from the input
     * 
     * @param string $input Natural language text
     * @return string Cleaned message
     */
    private function extractMessage($input) {
        // Remove common reminder phrases
        $patterns = [
            '/^(remind me to|remind me|i need to|i should|i have to|i want to)\s+/i',
            '/\s+(next|this|tomorrow|on|at|by|in)\s+.*$/i'
        ];
        
        $message = $input;
        foreach ($patterns as $pattern) {
            $message = preg_replace($pattern, '', $message);
        }
        
        return trim($message);
    }
    
    /**
     * Extract date and time information from natural language
     * 
     * @param string $input Natural language text
     * @return DateTime|null Parsed datetime object
     */
    private function extractDateTime($input) {
        $now = new DateTime();
        
        // Check for specific patterns
        
        // "next friday at noon"
        if (preg_match('/next\s+(monday|tuesday|wednesday|thursday|friday|saturday|sunday)/i', $input, $matches)) {
            $dayName = strtolower($matches[1]);
            $dateTime = new DateTime("next $dayName");
            
            // Extract time
            $time = $this->extractTime($input);
            if ($time) {
                $dateTime->setTime($time['hour'], $time['minute']);
            }
            
            return $dateTime;
        }
        
        // "this friday at 3pm"
        if (preg_match('/this\s+(monday|tuesday|wednesday|thursday|friday|saturday|sunday)/i', $input, $matches)) {
            $dayName = strtolower($matches[1]);
            $currentDay = (int)$now->format('N');
            $targetDay = $this->getDayNumber($dayName);
            
            if ($targetDay > $currentDay) {
                $dateTime = new DateTime("this $dayName");
            } else {
                $dateTime = new DateTime("next $dayName");
            }
            
            $time = $this->extractTime($input);
            if ($time) {
                $dateTime->setTime($time['hour'], $time['minute']);
            }
            
            return $dateTime;
        }
        
        // "tomorrow at 9am"
        if (preg_match('/tomorrow/i', $input)) {
            $dateTime = new DateTime('tomorrow');
            
            $time = $this->extractTime($input);
            if ($time) {
                $dateTime->setTime($time['hour'], $time['minute']);
            } else {
                $dateTime->setTime(9, 0); // default morning
            }
            
            return $dateTime;
        }
        
        // "in 2 hours"
        if (preg_match('/in\s+(\d+)\s+(minute|minutes|hour|hours|day|days|week|weeks)/i', $input, $matches)) {
            $amount = (int)$matches[1];
            $unit = strtolower($matches[2]);
            
            $dateTime = clone $now;
            
            switch ($unit) {
                case 'minute':
                case 'minutes':
                    $dateTime->modify("+$amount minutes");
                    break;
                case 'hour':
                case 'hours':
                    $dateTime->modify("+$amount hours");
                    break;
                case 'day':
                case 'days':
                    $dateTime->modify("+$amount days");
                    break;
                case 'week':
                case 'weeks':
                    $dateTime->modify("+$amount weeks");
                    break;
            }
            
            return $dateTime;
        }
        
        // "on january 15 at 2pm" or "on 1/15 at 2pm"
        if (preg_match('/on\s+([a-z]+\s+\d+|\d+\/\d+)/i', $input, $matches)) {
            try {
                $dateStr = $matches[1];
                $dateTime = new DateTime($dateStr);
                
                $time = $this->extractTime($input);
                if ($time) {
                    $dateTime->setTime($time['hour'], $time['minute']);
                }
                
                return $dateTime;
            } catch (Exception $e) {
                return null;
            }
        }
        
        return null;
    }
    
    /**
     * Extract time from input string
     * 
     * @param string $input Natural language text
     * @return array|null Array with hour and minute, or null
     */
    private function extractTime($input) {
        // "at 3pm" or "at 15:30" or "at noon" or "at midnight"
        
        // Check for noon/midnight
        if (preg_match('/\b(noon|midday)\b/i', $input)) {
            return ['hour' => 12, 'minute' => 0];
        }
        
        if (preg_match('/\bmidnight\b/i', $input)) {
            return ['hour' => 0, 'minute' => 0];
        }
        
        // Check for "around noon" - 11am
        if (preg_match('/around\s+(noon|midday)/i', $input)) {
            return ['hour' => 11, 'minute' => 0];
        }
        
        // Check for 12-hour format with am/pm
        if (preg_match('/(\d{1,2})(?::(\d{2}))?\s*(am|pm)/i', $input, $matches)) {
            $hour = (int)$matches[1];
            $minute = isset($matches[2]) ? (int)$matches[2] : 0;
            $meridiem = strtolower($matches[3]);
            
            if ($meridiem === 'pm' && $hour !== 12) {
                $hour += 12;
            } elseif ($meridiem === 'am' && $hour === 12) {
                $hour = 0;
            }
            
            return ['hour' => $hour, 'minute' => $minute];
        }
        
        // Check for 24-hour format
        if (preg_match('/(\d{1,2}):(\d{2})/i', $input, $matches)) {
            return ['hour' => (int)$matches[1], 'minute' => (int)$matches[2]];
        }
        
        return null;
    }
    
    /**
     * Extract how long before the event to send reminder
     * 
     * @param string $input Natural language text
     * @return int Minutes before event to remind
     */
    private function extractReminderOffset($input) {
        // "an hour before" or "30 minutes before"
        if (preg_match('/(\d+)\s+(minute|minutes|hour|hours)\s+before/i', $input, $matches)) {
            $amount = (int)$matches[1];
            $unit = strtolower($matches[2]);
            
            if ($unit === 'hour' || $unit === 'hours') {
                return $amount * 60;
            }
            return $amount;
        }
        
        if (preg_match('/(an|one)\s+hour\s+before/i', $input)) {
            return 60;
        }
        
        return 60; // default 1 hour
    }
    
    /**
     * Convert DateTime to cron format
     * 
     * @param DateTime $dateTime DateTime object
     * @return string Cron expression
     */
    private function convertToCron($dateTime) {
        $minute = $dateTime->format('i');
        $hour = $dateTime->format('H');
        $day = $dateTime->format('d');
        $month = $dateTime->format('m');
        $dayOfWeek = '*'; // We're using specific dates, so day of week is *
        
        return "$minute $hour $day $month $dayOfWeek";
    }
    
    /**
     * Get day number (1=Monday, 7=Sunday)
     * 
     * @param string $dayName Day name
     * @return int Day number
     */
    private function getDayNumber($dayName) {
        $days = [
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6,
            'sunday' => 7
        ];
        
        return $days[strtolower($dayName)] ?? 1;
    }
    
    /**
     * Generate user-friendly confirmation message
     * 
     * @param array $reminder Reminder data
     * @return string Confirmation message
     */
    private function generateConfirmationMessage($reminder) {
        $datetime = new DateTime($reminder['datetime']->format('Y-m-d H:i:s'));
        $now = new DateTime();
        
        // Calculate when to actually send the reminder
        $notifyTime = clone $datetime;
        $notifyTime->modify("-{$reminder['remind_before']} minutes");
        
        $responses = [
            "Got it! Saved your reminder and will let you know",
            "Done! I'll remind you",
            "All set! You'll get a ping",
            "Reminder saved! I'll give you a heads up",
            "Perfect! I'll send you a reminder"
        ];
        
        $response = $responses[array_rand($responses)];
        
        // Format the time
        $timeStr = $this->formatFriendlyTime($notifyTime, $datetime);
        
        // Add what they need to do
        $action = $reminder['message'];
        
        return "$response $timeStr to $action!";
    }
    
    /**
     * Format time in a friendly way
     * 
     * @param DateTime $notifyTime When to send reminder
     * @param DateTime $eventTime When event happens
     * @return string Friendly time string
     */
    private function formatFriendlyTime($notifyTime, $eventTime) {
        $now = new DateTime();
        $diff = $now->diff($eventTime);
        
        $minutesBefore = (int)(($eventTime->getTimestamp() - $notifyTime->getTimestamp()) / 60);
        
        $timeStr = '';
        
        // Add "before" part if there's advance notice
        if ($minutesBefore > 0) {
            if ($minutesBefore === 60) {
                $timeStr = "an hour before ";
            } elseif ($minutesBefore < 60) {
                $timeStr = "$minutesBefore minutes before ";
            } else {
                $hours = round($minutesBefore / 60, 1);
                $timeStr = "$hours hours before ";
            }
        }
        
        // Add the actual time
        $dayStr = '';
        if ($diff->days === 0) {
            $dayStr = 'today';
        } elseif ($diff->days === 1) {
            $dayStr = 'tomorrow';
        } else {
            $dayStr = $eventTime->format('l'); // Day name
        }
        
        $hourStr = $eventTime->format('g:ia');
        if ($eventTime->format('i') === '00') {
            $hourStr = $eventTime->format('ga'); // Remove minutes if :00
        }
        
        return $timeStr . $dayStr . ' at ' . $hourStr;
    }
    
    /**
     * Save reminder to storage (implement based on your needs)
     * 
     * @param array $reminder Reminder data
     * @return int Reminder ID
     */
    protected function saveReminder($reminder) {
        $reminders = $this->loadAllReminders();
        
        // Generate unique ID
        $id = uniqid('rem_', true);
        $reminder['id'] = $id;
        
        // Convert DateTime to string for storage
        if ($reminder['datetime'] instanceof DateTime) {
            $reminder['datetime'] = $reminder['datetime']->format('Y-m-d H:i:s');
        }
        
        $reminder['notified'] = false;
        
        $reminders[] = $reminder;
        
        file_put_contents($this->storageFile, json_encode($reminders, JSON_PRETTY_PRINT));
        
        return $id;
    }
    
    /**
     * List all reminders for a user
     * 
     * @param int $userId User identifier
     * @return string Formatted list of reminders
     */
    public function listReminders($userId) {
        $reminders = $this->getRemindersForUser($userId);
        
        if (empty($reminders)) {
            return "You don't have any reminders set up yet. Want me to create one?";
        }
        
        $response = "Here are your upcoming reminders:\n\n";
        
        foreach ($reminders as $i => $reminder) {
            $num = $i + 1;
            $datetime = new DateTime($reminder['datetime']);
            $timeStr = $datetime->format('l, F j \a\t g:ia');
            $response .= "**$num.** $timeStr - {$reminder['message']}\n";
        }
        
        return $response;
    }
    
    /**
     * Delete a reminder
     * 
     * @param int $reminderId Reminder ID
     * @param int $userId User identifier
     * @return string Confirmation message
     */
    public function deleteReminder($reminderId, $userId) {
        $reminders = $this->loadAllReminders();
        
        // Filter out the reminder to delete
        $reminders = array_filter($reminders, function($r) use ($reminderId, $userId) {
            return !($r['id'] === $reminderId && $r['user_id'] == $userId);
        });
        
        // Re-index array
        $reminders = array_values($reminders);
        
        file_put_contents($this->storageFile, json_encode($reminders, JSON_PRETTY_PRINT));
        
        $messages = [
            "Done! I've cancelled that reminder.",
            "Got it, reminder deleted!",
            "All set, that reminder has been removed.",
            "No problem, I've cleared that one out."
        ];
        
        return $messages[array_rand($messages)];
    }
    
    /**
     * Get reminders for a user (implement based on your storage)
     * 
     * @param int $userId User identifier
     * @return array Array of reminders
     */
    protected function getRemindersForUser($userId) {
        $reminders = $this->loadAllReminders();
        
        // Filter by user
        $userReminders = array_filter($reminders, function($r) use ($userId) {
            return isset($r['user_id']) && $r['user_id'] == $userId;
        });
        
        // Sort by datetime
        usort($userReminders, function($a, $b) {
            return strtotime($a['datetime']) - strtotime($b['datetime']);
        });
        
        return array_values($userReminders);
    }
    
    /**
     * Check for due reminders (call this periodically)
     * 
     * @return array Array of due reminders
     */
    public function checkDueReminders() {
        $reminders = $this->loadAllReminders();
        $now = new DateTime();
        
        $due = [];
        
        foreach ($reminders as $reminder) {
            // Skip already notified reminders
            if (isset($reminder['notified']) && $reminder['notified']) {
                continue;
            }
            
            // Calculate when to notify (event time minus remind_before minutes)
            $eventTime = new DateTime($reminder['datetime']);
            $notifyTime = clone $eventTime;
            $notifyTime->modify("-{$reminder['remind_before']} minutes");
            
            // Check if it's time to notify
            if ($now >= $notifyTime) {
                $due[] = $reminder;
            }
        }
        
        return $due;
    }
    
    /**
     * Load all reminders from storage
     * 
     * @return array All reminders
     */
    protected function loadAllReminders() {
        if (!file_exists($this->storageFile)) {
            return [];
        }
        
        $content = file_get_contents($this->storageFile);
        $reminders = json_decode($content, true);
        
        return is_array($reminders) ? $reminders : [];
    }
    
    /**
     * Mark a reminder as notified
     * 
     * @param string $reminderId Reminder ID
     */
    public function markAsNotified($reminderId) {
        $reminders = $this->loadAllReminders();
        
        foreach ($reminders as &$reminder) {
            if ($reminder['id'] === $reminderId) {
                $reminder['notified'] = true;
                break;
            }
        }
        
        file_put_contents($this->storageFile, json_encode($reminders, JSON_PRETTY_PRINT));
    }
    
    /**
     * Get notifications (due reminders formatted for sending)
     * 
     * @return array Array of notifications
     */
    public function getNotifications() {
        $due = $this->checkDueReminders();
        $notifications = [];
        
        foreach ($due as $reminder) {
            $datetime = new DateTime($reminder['datetime']);
            $timeStr = $datetime->format('g:ia');
            
            $message = "⏰ Reminder: {$reminder['message']} (scheduled for $timeStr)";
            
            $notifications[] = [
                'user_id' => $reminder['user_id'],
                'message' => $message,
                'reminder_id' => $reminder['id']
            ];
            
            // Mark as notified
            $this->markAsNotified($reminder['id']);
        }
        
        return $notifications;
    }
}

// exit;

// // Example usage
// $reminderSystem = new ReminderSystem();

// // Example 1: "i need to call barry next friday at around noon"
// echo $reminderSystem->createReminder("i need to call barry next friday at around noon", 12345) . "\n\n";

// // Example 2: "remind me to take out the trash tomorrow at 7pm"
// echo $reminderSystem->createReminder("remind me to take out the trash tomorrow at 7pm", 12345) . "\n\n";

// // Example 3: "i should buy groceries in 3 hours"
// echo $reminderSystem->createReminder("i should buy groceries in 3 hours", 12345) . "\n\n";

// // Example 4: "meeting on january 20 at 2pm"
// echo $reminderSystem->createReminder("meeting on january 20 at 2pm", 12345) . "\n\n";

?>