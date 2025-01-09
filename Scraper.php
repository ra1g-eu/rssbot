<?php

class Scraper
{
    protected string $apiUrl;
    protected string $outputFile;
    private string $discordToken;
    private string $discordChannelId;
    private string $processingUrl;
    private string $secretToken;
    private array $filterData;

    /**
     * @param string $apiUrl
     * @param string $outputFile
     * @param string $discordToken
     * @param string $discordChannelId
     * @param string $processingUrl
     * @param string $secretToken
     * @param array $filterData
     */
    public function __construct(
        string $apiUrl,
        string $outputFile,
        string $discordToken,
        string $discordChannelId,
        string $processingUrl,
        string $secretToken,
        array  $filterData
    )
    {
        $this->apiUrl = $apiUrl;
        $this->outputFile = $outputFile;
        $this->discordToken = $discordToken;
        $this->discordChannelId = $discordChannelId;
        $this->processingUrl = $processingUrl;
        $this->secretToken = $secretToken;
        $this->filterData = $filterData;
    }

    /**
     * @param string $string
     * @return string
     */
    public function cleanString(string $string): string
    {
        return trim(preg_replace('/[^[:print:]]/', '', $string));
    }

    /**
     * @param int $page
     * @return array|null
     */
    public function fetchPage(int $page): ?array
    {
        $postData = '{"idCategory":' . $this->filterData["idCategory"] . ',"producers":"","parameters":[],"idPrefix":0,"prefixType":0,"page":' . $page . ',"pageTo":' . $page . ',"availabilityType":0,"newsOnly":false,"commodityWears":[0],"upperDescriptionStatus":0,"branchId":-2,"sort":1,"categoryType":1,"searchTerm":"","append":false,"yearFrom":null,"yearTo":null,"artistId":null,"minPrice":' . $this->filterData["minPrice"] . ',"maxPrice":' . $this->filterData["maxPrice"] . ',"showOnlyActionCommodities":false,"useRatingThreshold":false,"showOnlyAlzaPlusCommodities":false,"callFromParametrizationDialog":false,"configurationId":9,"scroll":0,"hash":"#f&limit=' . $this->filterData["minPrice"] . '--' . $this->filterData["maxPrice"] . '&cst=0&cud=0&pg=' . $page . '&pn=1&prod=","counter":8}';

        $ch = curl_init();

        curl_setopt_array($ch, array(
            CURLOPT_URL => $this->apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_HTTPHEADER => array(
                'Accept: application/json',
                'Content-Type: application/json',
                'Cookie: VST=c167bcd3-0442-47b5-a276-0ca1d2ff9add; VZTX=11644869514; __cf_bm=1DIlpl2S2OJk0jh8brMkQx..A1.9DxKIWTC4qXrG6sc-1735989365-1.0.1.1-sdqAdbhoxxcew25zp8s6N45zqmPQ37GOFiKz.3poSDvhF8IRFqdXMYbQ0kYCYUCn1ZqBlKDiw0az8Cw_VbTXeQ; CriticalCSS=14008455; lb_id=15490c53d80f1bf6a9477acd84f6b9a7',
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:133.0) Gecko/20100101 Firefox/133.0',
                'Referer: https://www.alza.sk/',
                'Host: www.alza.sk',
                'Origin: https://www.alza.sk',
            ),
        ));

        $response = curl_exec($ch);

        if (curl_getinfo($ch, CURLINFO_HTTP_CODE) !== 200) {
            echo 'Error fetching page: ' . curl_error($ch) . "\n";
            curl_close($ch);
            return null;
        }

        curl_close($ch);
        return json_decode($response, true);
    }

    /**
     * @param string $htmlContent
     * @param array $savedJson
     * @param string $currentDateAndHour
     * @return void
     */
    public function parseHtml(string $htmlContent, array &$savedJson, string $currentDateAndHour): void
    {
        $dom = new DOMDocument();
        @$dom->loadHTML($htmlContent);

        $xpath = new DOMXPath($dom);
        $productNodes = $xpath->query("//div[contains(@class, 'box browsingitem')]");

        foreach ($productNodes as $productNode) {
            $name = $this->cleanString(
                $xpath->query(".//a[contains(@class, 'name browsinglink')]", $productNode)?->item(0)?->textContent ?? ''
            );
            $link = $this->cleanString(
                $xpath->query(".//a[contains(@class, 'name browsinglink')]", $productNode)?->item(0)?->getAttribute('href') ?? ''
            );
            $price = $this->cleanString(
                $xpath->query(".//span[contains(@class, 'price-box__price')]", $productNode)?->item(0)?->textContent ?? ''
            );
            $availability = (int)preg_replace(
                '/[^0-9]/',
                '',
                $this->cleanString(
                    $xpath->query(".//span[contains(@class, 'avlVal')]", $productNode)?->item(0)?->textContent ?? '0'
                )
            );

            $imageNode = $xpath->query(".//img[contains(@class, 'js-box-image')]", $productNode)?->item(0);
            $productImage = null;
            if ($imageNode) {
                $srcset = $imageNode->getAttribute('srcset') ?? '';
                $srcsetUrls = array_map('trim', explode(',', $srcset));
                $firstUrl = $srcsetUrls[0] ?? null;
                if ($firstUrl) {
                    $productImage = explode(' ', $firstUrl)[0]; // Get the URL before any width specifier
                }
            }

            if (isset($savedJson[$name])) {
                $savedJson[$name]["image"] = $productImage;
            }

            if (isset($savedJson[$name][$currentDateAndHour])) {
                continue;
            }

            if (isset($savedJson[$name])) {
                $savedJson[$name][$currentDateAndHour] = [
                    "price" => $price,
                    "availability" => $availability,
                ];
            } else {
                $savedJson[$name] = [
                    "link" => "https://www.alza.sk" . $link,
                    "name" => $name,
                    "image" => $productImage,
                    $currentDateAndHour => [
                        "price" => $price,
                        "availability" => $availability,
                    ]
                ];
            }
        }
    }

    /**
     * @param array $data
     * @return void
     */
    public function saveToFile(array $data): void
    {
        file_put_contents($this->outputFile, json_encode($data, JSON_PRETTY_PRINT));
    }

    /**
     * @return array
     */
    public function getFromFile(): array
    {
        if (!file_exists($this->outputFile)) {
            file_put_contents($this->outputFile, json_encode([]));
            return [];
        }

        return json_decode(file_get_contents($this->outputFile), true);
    }

    /**
     * @param array $data
     * @return void
     */
    public function sendData(array $data): void
    {
        $postData = json_encode([
            'filename' => basename($this->outputFile),
            'secret_token' => $this->secretToken,
            'content' => $data,
        ]);

        $ch = curl_init($this->processingUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
        ]);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            echo 'Error: ' . curl_error($ch);
        } else {
            echo 'Response: ' . $response;
        }

        curl_close($ch);
    }

    /**
     * @param int $count
     * @param string $downloadLink
     * @return void
     */
    public function sendNotification(int $count, string $downloadLink): void
    {
        $message = "- Prices Scraper Notification\n" .
            "- Processed **$count** items. Download: **$downloadLink** \n" .
            "- Visualizer link: **https://ra1g.eu/webvisualizer.html**";

        $payload = json_encode(['content' => $message]);

        $ch = curl_init("https://discord.com/api/v10/channels/$this->discordChannelId/messages");
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bot $this->discordToken",
                "Content-Type: application/json",
            ],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
        ]);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            echo 'Error: ' . curl_error($ch);
            curl_close($ch);
            return;
        }

        echo "Discord notification sent.\n";
        curl_close($ch);
    }

    /**
     * @param int $pagesToProcess
     * @param int $sleepTime
     * @return void
     */
    public function run(int $pagesToProcess, int $sleepTime): void
    {
        $savedJson = $this->getFromFile();
        $currentDateAndHour = date('d-m-Y');

        for ($page = 1; $page <= $pagesToProcess; $page++) {
            echo "Fetching page $page...\n";

            $response = $this->fetchPage($page);
            if (!$response || empty($response['d']['Boxes'])) {
                echo "No HTML content found in the response. Exiting.\n";
                break;
            }

            $this->parseHtml($response['d']['Boxes'], $savedJson, $currentDateAndHour);

            echo "Page $page processed. Sleeping...\n";
            sleep($sleepTime); // Avoid overloading the server
        }

        $totalItems = count($savedJson);
        $this->saveToFile($savedJson);
        $this->sendData($savedJson);
        $this->sendNotification($totalItems, "https://ra1g.eu/" . basename($this->outputFile));
    }
}

$gpuScraper = new Scraper(
    'https://www.alza.sk/Services/EShopService.svc/Filter',
    __DIR__ . '/gpus.json',
    'bot_token',
    'channel_token',
    'https://ra1g.eu/process_prices.php',
    'secret_token',
    ['idCategory' => 18842862, 'minPrice' => 200, 'maxPrice' => 2500]
);

$gpuScraper->run(11, 10);

sleep(5);

$cpuScraper = new Scraper(
    'https://www.alza.sk/Services/EShopService.svc/Filter',
    __DIR__ . '/cpus.json',
    'bot_token',
    'channel_token',
    'https://ra1g.eu/process_prices.php',
    'secret_token',
    ['idCategory' => 18842843, 'minPrice' => -1, 'maxPrice' => -1]
);

$cpuScraper->run(10, 10);

class DatacompScraper extends Scraper
{
    /**
     * @param int $page
     * @return array|null
     */
    public function fetchPage(int $page): ?array
    {
        $pageUrl = $this->apiUrl . ($page > 1 ? 'page=' . $page : '');

        $headers = [
            'Accept: */*',
            'Cookie: VST=c167bcd3-0442-47b5-a276-0ca1d2ff9add; VZTX=11644869514; __cf_bm=1DIlpl2S2OJk0jh8brMkQx..A1.9DxKIWTC4qXrG6sc-1735989365-1.0.1.1-sdqAdbhoxxcew25zp8s6N45zqmPQ37GOFiKz.3poSDvhF8IRFqdXMYbQ0kYCYUCn1ZqBlKDiw0az8Cw_VbTXeQ; CriticalCSS=14008455; lb_id=15490c53d80f1bf6a9477acd84f6b9a7',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:133.0) Gecko/20100101 Firefox/133.0',
            'Referer: https://www.datacomp.sk',
            'Host: datacomp.sk',
        ];

        $options = [
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers),
                'timeout' => 30,
                'follow_location' => 1,
            ],
        ];

        $context = stream_context_create($options);

        $response = file_get_contents($pageUrl, false, $context);

        if ($response === false) {
            echo "Failed to fetch the URL: $pageUrl";
            return [];
        }

        if (empty($response)) {
            return [];
        }

        return [0 => $response];
    }

    /**
     * @param string $htmlContent
     * @param array $savedJson
     * @param string $currentDateAndHour
     * @return void
     */
    public function parseHtml(string $htmlContent, array &$savedJson, string $currentDateAndHour): void
    {
        $dom = new DOMDocument();
        @$dom->loadHTML($htmlContent);

        $xpath = new DOMXPath($dom);
        $productNodes = $xpath->query("//div[contains(@class, 'prodbox')]");

        foreach ($productNodes as $productNode) {
            $name = $this->cleanString(
                $xpath->query(".//a[contains(@class, 'stiplname')]", $productNode)?->item(0)?->textContent ?? ''
            );
            $link = $this->cleanString(
                $xpath->query(".//a[contains(@class, 'stiplname')]", $productNode)?->item(0)?->getAttribute('href') ?? ''
            );
            $price = $this->cleanString(
                $xpath->query(".//div[contains(@class, 'wvat')]/span", $productNode)?->item(0)?->textContent ?? ''
            );
            $availability = (int)preg_replace(
                '/[^0-9]/',
                '',
                $this->cleanString(
                    $xpath->query(".//div[contains(@class, 'stock yes')]", $productNode)?->item(0)?->textContent ? 1 : 0
                )
            );

            $imageNode = $xpath->query("//a[@data-link='product']//img")?->item(0);

            $productImage = null;
            if ($imageNode) {
                $productImage = $imageNode->getAttribute('src') ?? null;
            }

            if (isset($savedJson[$name])) {
                $savedJson[$name]["image"] = $productImage;
            }

            if (isset($savedJson[$name][$currentDateAndHour])) {
                continue;
            }

            if (isset($savedJson[$name])) {
                $savedJson[$name][$currentDateAndHour] = [
                    "price" => trim(str_replace(['€', ' '], '', $price)),
                    "availability" => $availability,
                ];
            } else {
                $savedJson[$name] = [
                    "link" => "https://www.datacomp.sk" . $link,
                    "name" => $name,
                    "image" => $productImage,
                    $currentDateAndHour => [
                        "price" => $price,
                        "availability" => $availability,
                    ]
                ];
            }
        }
    }

    /**
     * @param int $pagesToProcess
     * @param int $sleepTime
     * @return void
     */
    public function run(int $pagesToProcess, int $sleepTime): void
    {
        $savedJson = $this->getFromFile();
        $currentDateAndHour = date('d-m-Y');

        for ($page = 1; $page <= $pagesToProcess; $page++) {
            echo "Fetching page $page...\n";

            $response = $this->fetchPage($page);
            if (empty($response)) {
                echo "No HTML content found in the response. Exiting.\n";
                break;
            }

            $this->parseHtml($response[0], $savedJson, $currentDateAndHour);

            echo "Page $page processed. Sleeping...\n";
            sleep($sleepTime); // Avoid overloading the server
        }

        $totalItems = count($savedJson);
        $this->saveToFile($savedJson);
        $this->sendData($savedJson);
        $this->sendNotification($totalItems, "https://ra1g.eu/" . basename($this->outputFile));
    }
}

$datacompGpus = new DatacompScraper(
    'https://datacomp.sk/default_jx.asp?cls=spresenttrees&strid=9&stipricetotfrom=200&',
    __DIR__ . '/gpus-datacomp.json',
    'bot_token',
    'channel_token',
    'https://ra1g.eu/process_prices.php',
    'secret_token',
    []
);

$datacompGpus->run(11, 10);

$datacompCpus = new DatacompScraper(
    'https://datacomp.sk/default_jx.asp?cls=spresenttrees&strid=111&stipricetotfrom=80&',
    __DIR__ . '/cpus-datacomp.json',
    'bot_token',
    'channel_token',
    'https://ra1g.eu/process_prices.php',
    'secret_token',
    []
);

$datacompCpus->run(11, 10);
