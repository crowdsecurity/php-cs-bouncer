<?php

use PHPUnit\Framework\TestCase;
use CrowdSecBouncer\Helper;
use org\bovigo\vfs\vfsStream;
use CrowdSecBouncer\Tests\Unit\FailingStreamWrapper;

/**
 * Test for helper functions.
 *
 * @author    CrowdSec team
 *
 * @see      https://crowdsec.net CrowdSec Official Website
 *
 *
 * @covers \CrowdSecBouncer\Helper::buildRawBodyFromSuperglobals
 * @covers \CrowdSecBouncer\Helper::getMultipartRawBody
 * @covers \CrowdSecBouncer\Helper::getRawInput
 * @covers \CrowdSecBouncer\Helper::buildFormData
 * @covers \CrowdSecBouncer\Helper::extractBoundary
 * @covers \CrowdSecBouncer\Helper::readStream()
 * @covers \CrowdSecBouncer\Helper::appendFileData
 */

class HelperTest extends TestCase
{
    use Helper;

    /**
     * @var vfsStreamDirectory
     */
    private $root;

    protected function setUp(): void
    {
        $this->root = vfsStream::setup('/tmp');
    }

    public function testBuildRawBodyFromSuperglobals_withMultipartContentType_returnsMultipartRawBody()
    {

        file_put_contents($this->root->url(). '/tmp-test.txt', 'THIS IS A TEST FILE');

        $serverData = ['CONTENT_TYPE' => 'multipart/form-data; boundary=----WebKitFormBoundary'];
        $postData = ['key' => 'value'];
        $filesData = ['file' => ['name' => 'test.txt', 'tmp_name' => $this->root->url(). '/tmp-test.txt', 'type' => 'text/plain']];
        $maxBodySize = 1;

        $result = $this->buildRawBodyFromSuperglobals($maxBodySize, null, $serverData, $postData, $filesData);

        $this->assertStringContainsString('Content-Disposition: form-data; name="key"', $result);
        $this->assertStringContainsString('Content-Disposition: form-data; name="file"; filename="test.txt"', $result);

        $this->assertEquals(248, strlen($result));
        $this->assertStringContainsString('THIS IS A TEST FILE', $result);
    }

    public function testBuildRawBodyFromSuperglobals_noBoundaryShouldThrowException()
    {
        $streamType = 'php://memory';
        $inputStream = fopen($streamType, 'r+');
        fwrite($inputStream, '');
        rewind($inputStream);

        file_put_contents($this->root->url(). '/tmp-test.txt', 'THIS IS A TEST FILE');

        $serverData = ['CONTENT_TYPE' => 'multipart/form-data; badstring=----WebKitFormBoundary'];
        $postData = ['key' => 'value'];
        $filesData = ['file' => ['name' => 'test.txt', 'tmp_name' => $this->root->url(). '/tmp-test.txt', 'type' => 'text/plain']];
        $maxBodySize = 1;

        $error = '';

        try {
            $this->buildRawBodyFromSuperglobals($maxBodySize, $inputStream, $serverData, $postData, $filesData);
        } catch (\CrowdSecBouncer\BouncerException $e) {
            $error = $e->getMessage();
        }
        $this->assertEquals('Failed to read multipart raw body: Failed to extract boundary from Content-Type: (multipart/form-data; badstring=----WebKitFormBoundary)', $error);
    }


    public function testBuildRawBodyFromSuperglobals_withNonMultipartContentType_returnsRawInput()
    {
        $serverData = ['CONTENT_TYPE' => 'application/json'];
        $streamType = 'php://memory';
        $inputStream = fopen($streamType, 'r+');
        fwrite($inputStream, '{"key": "value"}');
        rewind($inputStream);
        $maxBodySize = 15;

        $result = $this->buildRawBodyFromSuperglobals($maxBodySize, $inputStream, $serverData, [], []);

        $this->assertEquals('{"key": "value"}', $result);
    }

    public function testBuildRawBodyFromSuperglobals_withNoStream_shouldThrowException()
    {
        $serverData = ['CONTENT_TYPE' => 'application/json'];
        $streamType = 'php://temp';
        $inputStream = fopen($streamType, 'r+');
        fwrite($inputStream, '{"key": "value"}');
        // We are closing the stream so it becomes unavailable
        fclose($inputStream);
        $maxBodySize = 15;

        $error = '';
        try {
            $this->buildRawBodyFromSuperglobals($maxBodySize, $inputStream, $serverData, [], []);
        } catch (\CrowdSecBouncer\BouncerException $e) {
            $error = $e->getMessage();
        }

        $this->assertEquals('Stream is not a valid resource', $error);

    }

    public function testReadStream_withFreadFailure_shouldThrowException()
    {
        // Register custom stream wrapper that fails on fread
        stream_wrapper_register('failing', FailingStreamWrapper::class);

        // Open a stream using the failing stream wrapper
        $mockStream = fopen('failing://test', 'r+');

        // Set the threshold (can be any number)
        $threshold = 100;

        $error = '';
        try {
            $this->readStream($mockStream, $threshold);
        } catch (\CrowdSecBouncer\BouncerException $e) {
            $error = $e->getMessage();
        }

        // Assert that the correct exception message was thrown
        $this->assertStringStartsWith('Failed to read stream:', $error);

        // Clean up the custom stream wrapper
        stream_wrapper_unregister('failing');
    }



    public function testBuildRawBodyFromSuperglobals_withEmptyContentType_returnsRawInput()
    {
        $serverData = [];
        $streamType = 'php://memory';
        $inputStream = fopen($streamType, 'r+');
        fwrite($inputStream, '{"key": "value"}');
        rewind($inputStream);
        $maxBodySize = 1;

        $result = $this->buildRawBodyFromSuperglobals($maxBodySize, $inputStream, $serverData, [], []);

        $this->assertEquals('{"key": "value"}', $result);
    }

    public function testBuildRawBodyFromSuperglobals_withLargeBody_truncatesBody()
    {
        $serverData = ['CONTENT_TYPE' => 'application/json'];
        $streamType = 'php://memory';
        $inputStream = fopen($streamType, 'r+');
        fwrite($inputStream, str_repeat('a', 2048));
        rewind($inputStream);
        $maxBodySize = 1;

        $result = $this->buildRawBodyFromSuperglobals($maxBodySize, $inputStream, $serverData, [], []);

        $this->assertEquals(str_repeat('a', 1025), $result);
    }

    public function testGetMultipartRawBody_withLargePostData_truncatesBody()
    {
        $contentType = 'multipart/form-data; boundary=----WebKitFormBoundary';
        $postData = ['key' => str_repeat('a', 2048)];
        $filesData = [];
        $threshold = 1025;

        $result = $this->getMultipartRawBody($contentType, $threshold, $postData, $filesData);

        $this->assertEquals(1025, strlen($result));
        $this->assertStringContainsString('Content-Disposition: form-data; name="key"', $result);
        $this->assertStringContainsString(str_repeat('a', 953), $result);
    }

    public function testGetMultipartRawBody_withLargeFileData_truncatesBody()
    {
        $contentType = 'multipart/form-data; boundary=----WebKitFormBoundary';
        $postData = [];
        $filesData = ['file' => ['name' => 'test.txt', 'tmp_name' => $this->root->url() . '/phpYzdqkD', 'type' =>
            'text/plain']];
        file_put_contents($this->root->url().'/phpYzdqkD', 'THIS_IS_THE_CONTENT'. str_repeat('a', 2048));
        $threshold = 1025;

        $result = $this->getMultipartRawBody($contentType, $threshold, $postData, $filesData);

        $this->assertEquals(1025, strlen($result));
        $this->assertStringContainsString('THIS_IS_THE_CONTENT', $result);
    }

    /**
     * @group unit
     */
    public function testGetMultipartRawBody_withLargeFileName_truncatesBody()
    {
        $contentType = 'multipart/form-data; boundary=----WebKitFormBoundary';
        $postData = [];
        $filesData = ['file' => ['name' => str_repeat('a', 2048) . '.txt', 'tmp_name' => $this->root->url() . '/phpYzdqkD', 'type' =>
            'text/plain']];
        file_put_contents($this->root->url().'/phpYzdqkD', 'THIS_IS_THE_CONTENT');
        $threshold = 1025;

        $result = $this->getMultipartRawBody($contentType, $threshold, $postData, $filesData);

        $this->assertEquals(1025, strlen($result));
        $this->assertStringNotContainsString('THIS_IS_THE_CONTENT', $result);
    }

    public function testGetMultipartRawBody_withLargeFileData_truncatesBodyEnBoundary()
    {
        $contentType = 'multipart/form-data; boundary=----WebKitFormBoundary';
        $postData = [];
        $filesData = ['file' => ['name' => 'test.txt', 'tmp_name' => $this->root->url() . '/phpYzdqkD', 'type' =>
            'text/plain']];
        file_put_contents($this->root->url().'/phpYzdqkD', str_repeat('a', 2045));
        // Total size without adding boundary is 2167
        $threshold = 2168;

        $result = $this->getMultipartRawBody($contentType, $threshold, $postData, $filesData);

        $this->assertEquals(2168, strlen($result));
    }

    public function testGetMultipartRawBody_withLargeFileData_shouldThrowException()
    {
        $contentType = 'multipart/form-data; boundary=----WebKitFormBoundary';
        $postData = [];
        $filesData = ['file' => ['name' => 'test.txt', 'tmp_name' => $this->root->url() . '/phpYzdqkD', 'type' =>
            'text/plain']];
        // We don't create the file so it will throw an exception

        $error = '';
        try {
            $this->getMultipartRawBody($contentType, 1025, $postData, $filesData);
        } catch (\CrowdSecBouncer\BouncerException $e) {
            $error = $e->getMessage();
        }

        $this->assertStringContainsString('Failed to read multipart raw body', $error);
        $this->assertStringContainsString('fopen(vfs://tmp/phpYzdqkD)', $error);
    }
}
