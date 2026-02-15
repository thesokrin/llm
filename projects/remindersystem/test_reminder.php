<?php

require_once __DIR__ . '/reminder_system.php';
require_once __DIR__ . '/discord_integration.php';

echo "=== REMINDER SYSTEM TEST ===\n\n";

// Initialize bot with test storage file
$bot = new DiscordReminderBot(__DIR__ . '/test_reminders.json');
$userId = 123456789;

// Test 1: Create a reminder
echo "Test 1: Creating reminder\n";
echo "Input: 'i need to call barry next friday at around noon'\n";
$response = $bot->handleCommand("i need to call barry next friday at around noon", $userId);
echo "Response: $response\n\n";

// Test 2: Create another reminder
echo "Test 2: Creating another reminder\n";
echo "Input: 'remind me to take out trash tomorrow at 7pm'\n";
$response = $bot->handleCommand("remind me to take out trash tomorrow at 7pm", $userId);
echo "Response: $response\n\n";

// Test 3: List reminders
echo "Test 3: Listing reminders\n";
echo "Input: '!list'\n";
$response = $bot->handleCommand("!list", $userId);
echo "Response: $response\n\n";

// Test 4: Check storage file
echo "Test 4: Checking storage file\n";
$storageFile = __DIR__ . '/test_reminders.json';
if (file_exists($storageFile)) {
    $content = file_get_contents($storageFile);
    $reminders = json_decode($content, true);
    echo "Number of reminders stored: " . count($reminders) . "\n";
    echo "Storage file contents:\n";
    echo json_encode($reminders, JSON_PRETTY_PRINT) . "\n\n";
}

// Test 5: Delete a reminder
echo "Test 5: Deleting reminder #1\n";
echo "Input: '!delete 1'\n";
$response = $bot->handleCommand("!delete 1", $userId);
echo "Response: $response\n\n";

// Test 6: List after delete
echo "Test 6: Listing reminders after delete\n";
echo "Input: '!list'\n";
$response = $bot->handleCommand("!list", $userId);
echo "Response: $response\n\n";

// Test 7: Help command
echo "Test 7: Getting help\n";
echo "Input: '!help'\n";
$response = $bot->handleCommand("!help", $userId);
echo "Response: $response\n\n";

// Test 8: Check for due notifications
echo "Test 8: Checking for due notifications\n";
$notifications = $bot->getDiscordNotifications();
if (empty($notifications)) {
    echo "No notifications due\n";
} else {
    foreach ($notifications as $notif) {
        echo "User {$notif['user_id']}: {$notif['message']}\n";
    }
}

echo "\n=== TEST COMPLETE ===\n";

// Clean up test file
if (file_exists($storageFile)) {
    unlink($storageFile);
    echo "Test storage file cleaned up.\n";
}

?>
