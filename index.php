<?php

$botToken = 'YOUR BOT TOKEN';


$telegramAPI = 'https://api.telegram.org/bot' . $botToken . '/';


$webhookURL = 'WEBHOOK_URL'; // Replace this with your actual webhook URL


$channelUsername = 'devsnp'; // Replace with your channel's username


$api_endpoints = array(
    'TinyURL' => 'https://tinyurl.com/api-create.php?url=',
    'is.gd' => 'https://is.gd/create.php?format=simple&url=',
    'v.gd' => 'https://v.gd/create.php?format=simple&url=',
    'da.gd' => 'https://da.gd/s?url=',
    'CleanURI' => 'https://cleanuri.com/api/v1/shorten'
);

function shorten_url($url) {
    global $api_endpoints, $telegramAPI;
    $shortened_urls = array();
    foreach ($api_endpoints as $service => $endpoint) {
        sendTyping($url);
        usleep(700000); 
        if ($service == 'CleanURI') {
            $postData = array('url' => $url);
            $ch = curl_init($endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($httpCode == 200) {
                $json_response = json_decode($response, true);
                $shortened_urls[$service] = $json_response['result_url'];
            }
        } elseif ($service == 'da.gd') {
            $response = file_get_contents($endpoint . urlencode($url));
            if ($response !== false && strpos($response, 'http') === 0) {
                $shortened_urls[$service] = trim($response);
            }
        } else {
            $response = file_get_contents($endpoint . $url);
            $json_response = json_decode($response, true);
            if ($json_response && array_key_exists('shorturl', $json_response)) {
                $shortened_urls[$service] = $json_response['shorturl'];
            } elseif ($json_response && array_key_exists('shortenedUrl', $json_response)) {
                $shortened_urls[$service] = $json_response['shortenedUrl'];
            } else {
                $shortened_urls[$service] = $response;
            }
        }
    }
    return $shortened_urls;
}


function sendTyping($chatID) {
    global $telegramAPI;
    file_get_contents($telegramAPI . 'sendChatAction?chat_id=' . $chatID . '&action=typing');
}


function isMember($chatID, $user_id) {
    global $telegramAPI, $channelUsername;
    $check = json_decode(file_get_contents($telegramAPI . 'getChatMember?chat_id=@' . $channelUsername . '&user_id=' . $user_id), true);
    return ($check['ok'] && ($check['result']['status'] === 'member' || $check['result']['status'] === 'administrator' || $check['result']['status'] === 'creator'));
}


$webhookResponse = file_get_contents($telegramAPI . 'setWebhook?url=' . $webhookURL);

if ($webhookResponse) {
    echo "Webhook set successfully!";
} else {
    echo "Error setting webhook!";
}


$update = json_decode(file_get_contents('php://input'), true);
$message = $update['message']['text'];
$chatID = $update['message']['chat']['id'];
$userID = $update['message']['from']['id'];


if (strpos($message, '/start') !== false) {
    $welcomeMessage = "👋 <b>Welcome!</b>\n\nI'm your URL Shortener Bot.\nSend me a URL and I'll provide shortened links for you. 😊";

    $keyboard = array(
        "inline_keyboard" => array(
            array(
                array("text" => "Join @devsnp", "url" => "https://t.me/devsnp")
            )
        )
    );

    $encodedKeyboard = json_encode($keyboard);
    $replyMarkup = '&reply_markup=' . urlencode($encodedKeyboard);

    file_get_contents($telegramAPI . 'sendMessage?chat_id=' . $chatID . '&text=' . urlencode($welcomeMessage) . '&parse_mode=HTML' . $replyMarkup);
} elseif (strpos($message, 'http') === 0) {
    if (isMember($chatID, $userID)) {
        $response = shorten_url($message);
        $reply = "*Shortened URLs:*\n";
        foreach ($response as $service => $short_url) {
            $reply .= "*$service*: [$short_url]($short_url)\n";
            sendTyping($chatID); 
            usleep(700000); 
        }
        file_get_contents($telegramAPI . 'sendMessage?chat_id=' . $chatID . '&text=' . urlencode($reply) . '&parse_mode=Markdown');
    } else {
        $errorMessage = "⚠️ Please join @$channelUsername to access the URL shortening service.";
        file_get_contents($telegramAPI . 'sendMessage?chat_id=' . $chatID . '&text=' . urlencode($errorMessage) . '&parse_mode=HTML');
    }
} else {
    $errorMessage = "❗️ Please send a URL to shorten.";
    file_get_contents($telegramAPI . 'sendMessage?chat_id=' . $chatID . '&text=' . urlencode($errorMessage) . '&parse_mode=HTML');
}
?>
