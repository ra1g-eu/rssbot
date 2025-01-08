<?php

class RssToDiscordBot
{
    private $headers;
    private $apiUrl;
    private $rssFeeds;
    private $timestampFile;

    public function __construct()
    {
        $this->headers = [
            "Authorization: Bot bot_token",
            "Content-Type: application/json"
        ];

        $channelId = "channel_token"; // Replace with the channel's ID
        $this->apiUrl = "https://discord.com/api/v10/channels/$channelId/messages";

        // RSS Feed URLs
        $this->rssFeeds = [
            "https://www.techpowerup.com/rss/news" => "TechPowerUp",
            "https://www.techpowerup.com/rss/reviews" => "TechPowerUpReviews",
            "https://www.tomshardware.com/feeds/all" => "TomsHardware",
            "https://gamersnexus.net/rss.xml" => "GamersNexus",
            "https://www.guru3d.com/rss.xml" => "Guru3D",
            "https://videocardz.com/rss-feed" => "VideoCardz",
        ];

        // Timestamp File
        $this->timestampFile = __DIR__ . "/last_run_timestamp.txt";
    }

    private function readLastTimestamp()
    {
        if (!file_exists($this->timestampFile)) {
            return 0; // Default to 0 if the file doesn't exist
        }
        return (int)trim(file_get_contents($this->timestampFile));
    }

    private function saveCurrentTimestamp($timestamp)
    {
        file_put_contents($this->timestampFile, $timestamp);
    }

    private function fetchRss($rssFeedUrl, $lastTimestamp)
    {
        $rssContent = $this->fetchWithCurl($rssFeedUrl);

        // If cURL fails, fallback to file_get_contents
        if ($rssContent === false) {
            echo "cURL failed, attempting file_get_contents as fallback.\n";
            $rssContent = $this->fetchWithFileGetContents($rssFeedUrl);
        }

        if ($rssContent === false) {
            echo "Failed to fetch RSS feed after both attempts.\n";
            return [];
        }

        $rssXml = @simplexml_load_string($rssContent);
        if ($rssXml === false) {
            echo "Failed to parse RSS feed: $rssFeedUrl\n";
            return [];
        }

        $items = [];
        foreach ($rssXml->channel->item as $item) {
            $pubDate = strtotime((string)$item->pubDate);
            if ($pubDate > $lastTimestamp) {
                $items[] = [
                    'title' => (string)$item->title,
                    'link' => (string)$item->link,
                    'pubDate' => $pubDate,
                ];
            }
        }

        return $items;
    }

    private function fetchWithCurl($rssFeedUrl)
    {
        $ch = curl_init($rssFeedUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36");

        $rssContent = curl_exec($ch);

        if (curl_errno($ch)) {
            echo "cURL error: " . curl_error($ch) . "\n";
            curl_close($ch);
            return false;
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpCode != 200) {
            echo "Failed to fetch RSS feed: HTTP $httpCode\n";
            curl_close($ch);
            return false;
        }

        curl_close($ch);
        return $rssContent;
    }

    private function fetchWithFileGetContents($rssFeedUrl)
    {
        // Check if allow_url_fopen is enabled
        if (!ini_get('allow_url_fopen')) {
            echo "allow_url_fopen is disabled in php.ini.\n";
            return false;
        }

        $options = [
            'http' => [
                'method' => 'GET',
            ],
        ];
        $context = stream_context_create($options);

        $rssContent = @file_get_contents($rssFeedUrl, false, $context);
        if ($rssContent === false) {
            echo "Failed to fetch RSS feed using file_get_contents.\n";
        }

        return $rssContent;
    }


    private function sendToDiscord($items, $feedName)
    {
        foreach ($items as $item) {
            $message = "> $feedName | _" . date("F j, Y, g:i a", $item['pubDate']) . "_\n" .
                "- **{$item['title']}**\n" .
                "- {$item['link']}\n" .
                "---------------------- \n";

            $payload = json_encode(['content' => $message]);

            $ch = curl_init($this->apiUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $response = curl_exec($ch);

            if (curl_errno($ch)) {
                echo "Curl error: " . curl_error($ch) . "\n";
            } else {
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                if ($httpCode != 200 && $httpCode != 204) {
                    echo "Failed to send message: HTTP $httpCode\n";
                    echo "Response: $response\n";
                }
            }

            curl_close($ch);

            sleep(2); // Avoid hitting rate limits
        }
    }

    public function run()
    {
        $presenceUrl = "https://discord.com/api/v10/users/@me/settings"; // Endpoint for updating bot's status
        //$this->updateBotStatus($presenceUrl, $this->headers, 'online', 'Praise huang 🙏');

        $lastTimestamp = $this->readLastTimestamp();

        foreach ($this->rssFeeds as $rssFeedUrl => $feedName) {
            echo "Processing feed: $feedName\n";

            $items = $this->fetchRss($rssFeedUrl, $lastTimestamp);

            if (!empty($items)) {
                $this->sendToDiscord($items, $feedName);

                $latestTimestamp = time();
                $this->saveCurrentTimestamp($latestTimestamp);
            } else {
                echo "No new items for $feedName since the last run.\n";
            }

            echo "\n";
        }
    }

    private function updateBotStatus($presenceUrl, array $headers, $status, $gameName)
    {
        $data = [
            'status' => $status, // 'online', 'idle', 'dnd', 'invisible'
            'afk' => false, // Set whether the bot is away or not
            'activity' => [
                'name' => $gameName, // The game name will appear as the bot's activity
                'type' => 0, // 0: Playing, 1: Streaming, 2: Listening, 3: Watching
            ],
        ];

        $jsonData = json_encode($data);

        $ch = curl_init($presenceUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH'); // Using PATCH request
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);

        // Execute the request
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            echo "Curl error: " . curl_error($ch) . "\n";
        } else {
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($httpCode != 200 && $httpCode != 204) {
                echo "Failed to send message: HTTP $httpCode\n";
                echo "Response: $response\n";
            } else {
                echo "Bot status updated to online with game name: $gameName\n";
            }
        }

        // Close the cURL session
        curl_close($ch);
    }
}

// Execute the bot
$bot = new RssToDiscordBot();
$bot->run();
