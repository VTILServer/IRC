<?php
// ---------------------------------------------------------------- //
// Made by: ErringPaladin10 (ErringPaladin10@VTILServer.com)
// Creation Date: 09/19/2025
// Last Updated: 09/20/2025
// ---------------------------------------------------------------- //

// Accepts: ApiKey, SessionId, ChannelId, Messages, RequestEmojis
// Returns a JSON array of messages with: sessionId, messageId, speaker, userId, message, icon

define('DATA_DIR', __DIR__ . '/data');      // make sure the webserver can write here!
define('MAX_RETURN_MESSAGES', 100);

// Map of valid API keys to userIds (extend as needed)
$API_KEYS = [
    'F0BA8E63-1CC5-4709-8D30-C2089B5A46E9' => '[Server]',
];

////////////////////////////////////////////////////

function respond_json($data, $status = 200)
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function get_post_param($key, $default = null)
{
    // Accept JSON body or application/x-www-form-urlencoded / multipart/form-data
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        static $jsonParsed = null;
        if ($jsonParsed === null) {
            $raw = file_get_contents('php://input');
            $jsonParsed = json_decode($raw, true) ?: [];
        }
        return array_key_exists($key, $jsonParsed) ? $jsonParsed[$key] : $default;
    }
    return $_POST[$key] ?? $default;
}

// Basic emoji extractor (attempts to find first emoji in text).
// This isn't perfect (emoji sets are huge), but it catches most common emoji ranges.
function extract_first_emoji($text)
{
    // Unicode ranges for common emoji blocks
    $regex = '/([\x{1F300}-\x{1F6FF}]|[\x{1F900}-\x{1F9FF}]|[\x{2600}-\x{26FF}]|[\x{2700}-\x{27BF}])/u';
    if (preg_match($regex, $text, $m))
        return $m[0];
    return null;
}

function make_message_id()
{
    // unique id: time + random
    return uniqid('IRC.', true);
}

function ensure_data_dir()
{
    if (!file_exists(DATA_DIR)) {
        if (!mkdir(DATA_DIR, 0775, true)) {
            respond_json(['error' => 'Server cannot create data directory.', 'success' => false, 'status' => 'HTTP 500 Internal Server Error'], 500);
        }
    }
}

// Load the messages for a channel (returns array)
function load_channel_messages($channelId)
{
    $file = DATA_DIR . '/' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $channelId) . '.json';
    if (!file_exists($file))
        return [];
    $raw = file_get_contents($file);
    $arr = json_decode($raw, true);
    return is_array($arr) ? $arr : [];
}

// Save the messages back to channel file (overwrites)
function save_channel_messages($channelId, $messages)
{
    $file = DATA_DIR . '/' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $channelId) . '.json';
    file_put_contents($file, json_encode($messages, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

////////////////////////////////////////////////////

// Enforce POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond_json(['error' => 'Only POST allowed', 'success' => false, 'status' => 'HTTP 405 Method Not Allowed'], 405);
}

// Gather params
$apiKey = get_post_param('ApiKey');
$sessionId = get_post_param('SessionId');
$channelId = get_post_param('ChannelId');
$messagesParam = get_post_param('Messages'); // can be a string or a JSON array
$requestEmojis = get_post_param('RequestEmojis', true);

// Basic validation
if (!$apiKey || !$sessionId || !$channelId) {
    respond_json(['error' => 'Missing required parameters: ApiKey, SessionId, ChannelId', 'success' => false, 'status' => 'HTTP 400 Bad Request'], 400);
}

// Authenticate
global $API_KEYS;
if (!array_key_exists($apiKey, $API_KEYS)) {
    respond_json(['error' => 'Invalid ApiKey', 'success' => false, 'status' => 'HTTP 401 Unauthorized'], 401);
}
$speaker = $API_KEYS[$apiKey];

// Normalize the RequestEmojis to boolean
if (is_string($requestEmojis)) {
    $r = strtolower($requestEmojis);
    $requestEmojis = in_array($r, ['1', 'true', 'yes', 'on'], true);
} else {
    $requestEmojis = (bool) $requestEmojis;
}

// Normalize the Messages:
// - if it's JSON array, decode and use that
// - if single string, treat as single message
$newMessages = [];
if ($messagesParam !== null && $messagesParam !== '') {
    if (is_array($messagesParam)) {
        $newMessages = $messagesParam;
    } elseif (is_string($messagesParam)) {
        // try to decode JSON
        $maybe = json_decode($messagesParam, true);
        if (is_array($maybe))
            $newMessages = $maybe;
        else
            $newMessages = [$messagesParam];
    } else {
        // fallback
        $newMessages = [(string) $messagesParam];
    }
}

// Prepare storage
ensure_data_dir();
$existing = load_channel_messages($channelId);

// Append new messages (if any)
foreach ($newMessages as $m) {
    // m may be string or object with fields (we accept either)
    if (is_array($m)) {
        $text = isset($m['message']) ? (string) $m['message'] : (string) ($m['text'] ?? '');
        $msgUser = isset($m['speaker']) ? (string) $m['speaker'] : $speaker;
        $msgUserId = isset($m['userId']) ? (string) $m['userId'] : $userId;
    } else {
        $text = (string) $m;
        $msgUser = $speaker;
    }

    $msgId = make_message_id();

    // Determine the speakers icon:
    $icon = null;
    if ($requestEmojis) {
        $icon = extract_first_emoji($text);
    }
    if ($icon === null) {
        // fallback: use the first two letters of the speaker as "icon"
        $icon = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $msgUser), 0, 2));
        if ($icon === '')
            $icon = '??';
    }

    $entry = [
        'sessionId' => $sessionId,
        'messageId' => $msgId,
        'speaker' => $msgUser,
        'userId' => '1',
        'message' => $text,
        'icon' => $icon,
        'timestamp' => date('c'),
    ];
    $existing[] = $entry;
}

if (count($existing) > 10000) {
    // keep most recent 10000
    $existing = array_slice($existing, -10000);
}

// Save the Messages
save_channel_messages($channelId, $existing);

// Prepare a response: return latest messages (limit)
$toReturn = array_slice($existing, -MAX_RETURN_MESSAGES);

// Strip the timestamp or keep? 
// Requirement lists sessionId, messageId, speaker, userId, message, icon.
// We'll include exactly those fields (and optionally timestamp).
$response = array_map(function ($m) {
    return [
        'sessionId' => $m['sessionId'] ?? null,
        'messageId' => $m['messageId'] ?? null,
        'speaker' => $m['speaker'] ?? null,
        'userId' => $m['userId'] ?? null,
        'message' => $m['message'] ?? null,
        'icon' => $m['icon'] ?? null,
    ];
}, $toReturn);

// Return
respond_json(['channel' => $channelId, 'messages' => $response, 'success' => true, 'status' => 'HTTP 200 OK']);