<?php

declare(strict_types=1);

/*
 * This file is part of the community-maintained Playwright PHP project.
 * It is not affiliated with or endorsed by Microsoft.
 *
 * (c) 2025-Present - Playwright PHP - https://github.com/playwright-php
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Playwright\Tests\Functional\Tracing;

use PHPUnit\Framework\Attributes\CoversClass;
use Playwright\API\APIRequest;
use Playwright\API\APIRequestContext;
use Playwright\Browser\BrowserContext;
use Playwright\Configuration\PlaywrightConfig;
use Playwright\Regex;
use Playwright\Tests\Functional\FunctionalTestCase;
use Playwright\Tracing\Options\StartHarOptions;
use Playwright\Tracing\Tracing;
use Playwright\Transport\TransportFactory;
use Psr\Log\NullLogger;

#[CoversClass(Tracing::class)]
#[CoversClass(StartHarOptions::class)]
#[CoversClass(APIRequestContext::class)]
#[CoversClass(BrowserContext::class)]
final class TracingApiTest extends FunctionalTestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir().'/playwright-tracing-api-'.uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tempDir.'/*') ?: [] as $file) {
            @unlink($file);
        }
        if (is_dir($this->tempDir)) {
            @rmdir($this->tempDir);
        }
        parent::tearDown();
    }

    public function testTracingStartAndStopProduceATraceArchive(): void
    {
        $tracePath = $this->tempDir.'/trace.zip';

        $this->goto('/index.html');

        $tracing = $this->context->tracing();
        $tracing->start(['screenshots' => false, 'snapshots' => true]);

        $this->page->locator('#heading')->textContent();

        $tracing->stop(['path' => $tracePath]);

        $this->assertFileExists($tracePath);
        $this->assertGreaterThan(0, (int) filesize($tracePath));
        $this->assertStringContainsString('textContent', $this->readTraceEvents($tracePath));
    }

    public function testGroupsAppearInTheTrace(): void
    {
        $tracePath = $this->tempDir.'/trace-group.zip';

        $this->goto('/index.html');

        $tracing = $this->context->tracing();
        $tracing->start(['snapshots' => true]);

        $tracing->group('my custom step');
        $this->page->locator('#heading')->isVisible();
        $tracing->groupEnd();

        $tracing->stop(['path' => $tracePath]);

        $events = $this->readTraceEvents($tracePath);
        $this->assertStringContainsString('"method":"tracingGroup"', $events);
        $this->assertStringContainsString('my custom step', $events);
    }

    public function testChunksProduceSeparateArchives(): void
    {
        $chunkPath = $this->tempDir.'/chunk.zip';

        $this->goto('/index.html');

        $tracing = $this->context->tracing();
        $tracing->start(['snapshots' => true]);

        $tracing->startChunk(['title' => 'chunk one']);
        $this->page->locator('#heading')->textContent();
        $tracing->stopChunk(['path' => $chunkPath]);

        $tracing->stop();

        $this->assertFileExists($chunkPath);
        $this->assertGreaterThan(0, (int) filesize($chunkPath));
    }

    public function testHarRecordingCapturesNetworkActivity(): void
    {
        $harPath = $this->tempDir.'/network.har';

        $tracing = $this->context->tracing();
        $tracing->startHar($harPath, ['content' => 'omit', 'mode' => 'full', 'urlFilter' => '**/*']);

        $this->goto('/index.html');

        $tracing->stopHar();

        $this->assertFileExists($harPath);
        $entries = $this->readHarEntries($harPath);
        $this->assertNotSame([], $entries);
        $this->assertStringContainsString('/index.html', json_encode($entries, \JSON_THROW_ON_ERROR));
    }

    public function testHarRecordingHonoursARegexUrlFilter(): void
    {
        $harPath = $this->tempDir.'/filtered.har';

        $tracing = $this->context->tracing();
        $tracing->startHar($harPath, ['urlFilter' => new Regex('/nothing-matches-this/')]);

        $this->goto('/index.html');

        $tracing->stopHar();

        $this->assertFileExists($harPath);
        $this->assertSame([], $this->readHarEntries($harPath));
    }

    public function testHarRecordingIsAvailableOnTheApiRequestContext(): void
    {
        $harPath = $this->tempDir.'/api.har';

        $tracing = $this->context->request()->tracing();
        $tracing->startHar($harPath);

        $response = $this->context->request()->get($this->getBaseUrl().'/index.html');

        $tracing->stopHar();

        $this->assertSame(200, $response->status());
        $this->assertFileExists($harPath);
        $entries = $this->readHarEntries($harPath);
        $this->assertNotSame([], $entries);
        $this->assertStringContainsString('/index.html', json_encode($entries, \JSON_THROW_ON_ERROR));
    }

    public function testStandaloneApiHarRecordingCapturesRequests(): void
    {
        $transport = (new TransportFactory())->create(new PlaywrightConfig(), new NullLogger());
        $transport->connect();

        try {
            $request = (new APIRequest($transport))->newContext();
            try {
                $harPath = $this->tempDir.'/standalone.har';
                $tracing = $request->tracing();
                $tracing->startHar($harPath);
                $response = $request->get($this->getBaseUrl().'/index.html');
                $tracing->stopHar();

                $this->assertSame(200, $response->status());
                $this->assertStringContainsString('/index.html', json_encode($this->readHarEntries($harPath), \JSON_THROW_ON_ERROR));
            } finally {
                $request->dispose();
            }
        } finally {
            $transport->disconnect();
        }
    }

    public function testBrowserAndApiHarRecordingsKeepTheirRequests(): void
    {
        $browserHar = $this->tempDir.'/browser.har';
        $apiHar = $this->tempDir.'/request.har';
        $browserTracing = $this->context->tracing();
        $request = $this->context->request();
        $apiTracing = $request->tracing();

        $browserTracing->startHar($browserHar);
        $this->goto('/index.html');
        $browserTracing->stopHar();

        $apiTracing->startHar($apiHar);
        $request->get($this->getBaseUrl().'/api/echo');
        $apiTracing->stopHar();

        $this->assertContains($this->getBaseUrl().'/api/echo', array_column(array_column($this->readHarEntries($apiHar), 'request'), 'url'));
        $this->assertContains($this->getBaseUrl().'/index.html', array_column(array_column($this->readHarEntries($browserHar), 'request'), 'url'));
    }

    public function testApiTraceGroupsAndChunksKeepTheirEvents(): void
    {
        $browserPath = $this->tempDir.'/browser-trace.zip';
        $apiPath = $this->tempDir.'/api-chunk.zip';
        $browserTracing = $this->context->tracing();
        $request = $this->context->request();
        $apiTracing = $request->tracing();

        $apiTracing->start();
        $apiTracing->startChunk(['title' => 'API chunk']);
        $apiTracing->group('api-only-step');
        $request->get($this->getBaseUrl().'/api/echo');
        $apiTracing->groupEnd();
        $apiTracing->stopChunk(['path' => $apiPath]);
        $apiTracing->stop();

        $browserTracing->start();
        $browserTracing->group('browser-only-step');
        $this->goto('/index.html');
        $browserTracing->groupEnd();
        $browserTracing->stop(['path' => $browserPath]);

        $apiEvents = $this->readTraceEvents($apiPath);
        $browserEvents = $this->readTraceEvents($browserPath);
        $this->assertStringContainsString('api-only-step', $apiEvents);
        $this->assertStringContainsString('/api/echo', $apiEvents);
        $this->assertStringNotContainsString('browser-only-step', $apiEvents);
        $this->assertStringContainsString('browser-only-step', $browserEvents);
        $this->assertStringNotContainsString('api-only-step', $browserEvents);
    }

    /**
     * @return array<int, mixed>
     */
    private function readHarEntries(string $harPath): array
    {
        $this->assertFileExists($harPath);
        $raw = file_get_contents($harPath);
        $this->assertIsString($raw);

        $har = json_decode($raw, true, 512, \JSON_THROW_ON_ERROR);
        $this->assertIsArray($har);
        $this->assertArrayHasKey('log', $har);
        $this->assertIsArray($har['log']);
        $this->assertArrayHasKey('entries', $har['log']);
        $this->assertIsArray($har['log']['entries']);

        return array_values($har['log']['entries']);
    }

    private function readTraceEvents(string $zipPath): string
    {
        $this->assertFileExists($zipPath);
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($zipPath));

        $events = '';
        for ($i = 0; $i < $zip->numFiles; ++$i) {
            $name = (string) $zip->getNameIndex($i);
            if (str_ends_with($name, '.trace')) {
                $events .= (string) $zip->getFromIndex($i);
            }
        }
        $zip->close();

        $this->assertNotSame('', $events, 'No .trace events file found in the archive');

        return $events;
    }
}
