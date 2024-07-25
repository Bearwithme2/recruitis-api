<?php

namespace App\Service;

use Psr\Cache\InvalidArgumentException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Psr\Log\LoggerInterface;

class RecruitisApiClient
{
    private HttpClientInterface $client;
    private CacheInterface $cache;
    private LoggerInterface $logger;
    private string $apiBaseUrl;
    private string $apiToken;

    public function __construct(HttpClientInterface $client, CacheInterface $cache, LoggerInterface $logger, string $apiBaseUrl, string $apiToken)
    {
        $this->client = $client;
        $this->cache = $cache;
        $this->logger = $logger;
        $this->apiBaseUrl = $apiBaseUrl;
        $this->apiToken = $apiToken;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function getJobListings(int $page = 1): array
    {
        $cacheKey = 'job_listings_page_' . $page;

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($page) {
            $item->expiresAfter(3600);

            try {
                $response = $this->client->request('GET', $this->apiBaseUrl . 'jobs', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->apiToken,
                    ],
                    'query' => [
                        'page' => $page,
                    ],
                ]);

                if ($response->getStatusCode() !== 200) {
                    throw new \RuntimeException('Failed to fetch job listings');
                }

                return $response->toArray();
            } catch (\Exception $e) {
                $this->logger->error('Error fetching job listings: ' . $e->getMessage());
                throw $e;
            }
        });
    }
}
