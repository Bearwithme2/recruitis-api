<?php

declare(strict_types=1);

namespace App\Tests\Service;

use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Psr\Log\LoggerInterface;
use App\Service\RecruitisApiClient;
use Symfony\Component\Cache\Exception\InvalidArgumentException as SymfonyInvalidArgumentException;
use Mockery;

class RecruitisApiClientTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function testGetJobListingsCacheHit(): void
    {
        $client = Mockery::mock(HttpClientInterface::class);
        $cache = Mockery::mock(CacheInterface::class);
        $logger = Mockery::mock(LoggerInterface::class);

        $expectedResult = [['id' => 1, 'title' => 'Test Job']];
        $cacheKey = 'job_listings_page_1';

        $cache->shouldReceive('get')
            ->with($cacheKey, Mockery::type('callable'))
            ->andReturn($expectedResult);

        $recruitisApiClient = new RecruitisApiClient($client, $cache, $logger, 'https://api.example.com/', 'test-token');

        $result = $recruitisApiClient->getJobListings(1);

        $this->assertEquals($expectedResult, $result);
    }

    public function testGetJobListingsCacheMissAndSuccessfulHttpResponse(): void
    {
        $client = Mockery::mock(HttpClientInterface::class);
        $cache = Mockery::mock(CacheInterface::class);
        $logger = Mockery::mock(LoggerInterface::class);
        $cacheItem = Mockery::mock(ItemInterface::class);
        $response = Mockery::mock(ResponseInterface::class);

        $expectedResult = [['id' => 1, 'title' => 'Test Job']];
        $cacheKey = 'job_listings_page_1';

        $cache->shouldReceive('get')
            ->with($cacheKey, Mockery::type('callable'))
            ->andReturnUsing(function ($key, $callback) use ($cacheItem, $expectedResult) {
                $cacheItem->shouldReceive('expiresAfter')->with(3600);
                return $callback($cacheItem);
            });

        $client->shouldReceive('request')
            ->with('GET', 'https://api.example.com/jobs', [
                'headers' => [
                    'Authorization' => 'Bearer test-token',
                ],
                'query' => [
                    'page' => 1,
                ],
            ])
            ->andReturn($response);

        $response->shouldReceive('getStatusCode')->andReturn(200);
        $response->shouldReceive('toArray')->andReturn($expectedResult);

        $recruitisApiClient = new RecruitisApiClient($client, $cache, $logger, 'https://api.example.com/', 'test-token');

        $result = $recruitisApiClient->getJobListings(1);

        $this->assertEquals($expectedResult, $result);
    }

    public function testGetJobListingsHttpError(): void
    {
        $client = Mockery::mock(HttpClientInterface::class);
        $cache = Mockery::mock(CacheInterface::class);
        $logger = Mockery::mock(LoggerInterface::class);
        $cacheItem = Mockery::mock(ItemInterface::class);
        $response = Mockery::mock(ResponseInterface::class);

        $cacheKey = 'job_listings_page_1';

        $cache->shouldReceive('get')
            ->with($cacheKey, Mockery::type('callable'))
            ->andReturnUsing(function ($key, $callback) use ($cacheItem) {
                $cacheItem->shouldReceive('expiresAfter')->with(3600);
                return $callback($cacheItem);
            });

        $client->shouldReceive('request')
            ->with('GET', 'https://api.example.com/jobs', [
                'headers' => [
                    'Authorization' => 'Bearer test-token',
                ],
                'query' => [
                    'page' => 1,
                ],
            ])
            ->andReturn($response);

        $response->shouldReceive('getStatusCode')->andReturn(500);

        $logger->shouldReceive('error')
            ->with(Mockery::on(function ($message) {
                return str_contains($message, 'Error fetching job listings');
            }));

        $this->expectException(\RuntimeException::class);

        $recruitisApiClient = new RecruitisApiClient($client, $cache, $logger, 'https://api.example.com/', 'test-token');

        $recruitisApiClient->getJobListings(1);
    }

    public function testGetJobListingsCacheException(): void
    {
        $client = Mockery::mock(HttpClientInterface::class);
        $cache = Mockery::mock(CacheInterface::class);
        $logger = Mockery::mock(LoggerInterface::class);

        $cacheKey = 'job_listings_page_1';

        $cache->shouldReceive('get')
            ->with($cacheKey, Mockery::type('callable'))
            ->andThrow(new SymfonyInvalidArgumentException());

        $this->expectException(SymfonyInvalidArgumentException::class);

        $recruitisApiClient = new RecruitisApiClient($client, $cache, $logger, 'https://api.example.com/', 'test-token');

        $recruitisApiClient->getJobListings(1);
    }
}
