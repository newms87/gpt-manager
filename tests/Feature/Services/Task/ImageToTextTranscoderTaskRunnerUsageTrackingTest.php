<?php

namespace Tests\Feature\Services\Task;

use App\Api\ImageToText\ImageToTextOcrApi;
use App\Models\Task\TaskProcess;
use App\Models\Usage\UsageEvent;
use App\Services\Task\Runners\ImageToTextTranscoderTaskRunner;
use Newms87\Danx\Models\Utilities\StoredFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ImageToTextTranscoderTaskRunnerUsageTrackingTest extends TestCase
{
    #[Test]
    public function it_tracks_usage_when_performing_ocr_conversion()
    {
        // Set up pricing config
        config([
            'apis.' . ImageToTextOcrApi::class . '.pricing' => [
                'per_request' => 0.001,
            ],
        ]);

        $taskProcess = TaskProcess::factory()->create();
        $storedFile  = StoredFile::factory()->create([
            'filename' => 'test-image.jpg',
            'url'      => 'https://example.com/test-image.jpg',
            'size'     => 102400, // 100KB
        ]);

        // Mock the OCR API
        $mockApi = $this->mock(ImageToTextOcrApi::class);
        $mockApi->shouldReceive('convert')
            ->with($storedFile->url)
            ->andReturn('This is the extracted text from the image.');

        $this->app->instance(ImageToTextOcrApi::class, $mockApi);

        // Create the task runner
        $runner = new ImageToTextTranscoderTaskRunner();
        $runner->setTaskProcess($taskProcess);

        // Call the OCR transcoding method
        $result = $runner->getOcrTranscode($storedFile);

        $this->assertNotNull($result);

        // Check that usage event was created
        $usageEvent = UsageEvent::where('object_type', TaskProcess::class)
            ->where('object_id', $taskProcess->id)
            ->where('api_name', ImageToTextOcrApi::class)
            ->where('event_type', 'ocr_conversion')
            ->first();

        $this->assertNotNull($usageEvent);
        $this->assertEquals(ImageToTextOcrApi::class, $usageEvent->api_name);
        $this->assertEquals('ocr_conversion', $usageEvent->event_type);
        $this->assertEquals(1, $usageEvent->request_count);
        $this->assertEquals(0.001, $usageEvent->input_cost);
        $this->assertEquals(0, $usageEvent->output_cost);
        $this->assertGreaterThanOrEqual(0, $usageEvent->run_time_ms);

        // Check metadata
        $this->assertEquals('test-image.jpg', $usageEvent->metadata['filename']);
        $this->assertEquals(102400, $usageEvent->metadata['file_size']);
        $this->assertEquals('https://example.com/test-image.jpg', $usageEvent->metadata['url']);

        // Check data volume (length of extracted text)
        $expectedDataVolume = strlen('This is the extracted text from the image.');
        $this->assertEquals($expectedDataVolume, $usageEvent->data_volume);
    }

    #[Test]
    public function it_does_not_track_usage_when_transcode_already_exists()
    {
        $taskProcess = TaskProcess::factory()->create();
        $storedFile  = StoredFile::factory()->create();

        // Create existing OCR transcode
        $storedFile->transcodes()->create([
            'transcode_name' => ImageToTextTranscoderTaskRunner::TRANSCODE_NAME_OCR,
            'filename'       => 'test.ocr.txt',
            'content'        => 'Existing OCR text',
        ]);

        $runner = new ImageToTextTranscoderTaskRunner();
        $runner->setTaskProcess($taskProcess);

        // Should not make API call or track usage
        $result = $runner->getOcrTranscode($storedFile);

        $this->assertNotNull($result);

        // No usage event should be created
        $usageEvent = UsageEvent::where('object_type', TaskProcess::class)
            ->where('object_id', $taskProcess->id)
            ->first();

        $this->assertNull($usageEvent);
    }

    #[Test]
    public function it_handles_api_errors_gracefully()
    {
        $taskProcess = TaskProcess::factory()->create();
        $storedFile  = StoredFile::factory()->create();

        // Mock API to throw exception
        $mockApi = $this->mock(ImageToTextOcrApi::class);
        $mockApi->shouldReceive('convert')
            ->andThrow(new \Exception('API Error'));

        $this->app->instance(ImageToTextOcrApi::class, $mockApi);

        $runner = new ImageToTextTranscoderTaskRunner();
        $runner->setTaskProcess($taskProcess);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('API Error');

        $runner->getOcrTranscode($storedFile);

        // No usage event should be created on error
        $usageEvent = UsageEvent::where('object_type', TaskProcess::class)
            ->where('object_id', $taskProcess->id)
            ->first();

        $this->assertNull($usageEvent);
    }
}
