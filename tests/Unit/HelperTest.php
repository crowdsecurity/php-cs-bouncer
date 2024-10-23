<?php

use CrowdSecBouncer\Helper;
use CrowdSecBouncer\Tests\Unit\FailingStreamWrapper;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;

/**
 * Test for helper functions.
 *
 * @author    CrowdSec team
 *
 * @see      https://crowdsec.net CrowdSec Official Website
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

    public function testBuildRawBodyFromSuperglobalsNoBoundaryShouldThrowException()
    {
        $streamType = 'php://memory';
        $inputStream = fopen($streamType, 'r+');
        fwrite($inputStream, '');
        rewind($inputStream);

        file_put_contents($this->root->url() . '/tmp-test.txt', 'THIS IS A TEST FILE');

        $serverData = ['CONTENT_TYPE' => 'multipart/form-data; badstring=----WebKitFormBoundary'];
        $postData = ['key' => 'value'];
        $filesData = ['file' => ['name' => 'test.txt', 'tmp_name' => $this->root->url() . '/tmp-test.txt', 'type' => 'text/plain']];
        $maxBodySize = 1;

        $error = '';

        try {
            $this->buildRawBodyFromSuperglobals($maxBodySize, $inputStream, $serverData, $postData, $filesData);
        } catch (CrowdSecBouncer\BouncerException $e) {
            $error = $e->getMessage();
        }
        $this->assertEquals('Failed to read multipart raw body: Failed to extract boundary from Content-Type: (multipart/form-data; badstring=----WebKitFormBoundary)', $error);
    }

    public function testBuildRawBodyFromSuperglobalsWithEmptyContentTypeReturnsRawInput()
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

    public function testBuildRawBodyFromSuperglobalsWithLargeBodyTruncatesBody()
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

    public function testBuildRawBodyFromSuperglobalsWithMultipartContentTypeReturnsMultipartRawBody()
    {
        file_put_contents($this->root->url() . '/tmp-test.txt', 'THIS IS A TEST FILE');

        $serverData = ['CONTENT_TYPE' => 'multipart/form-data; boundary=----WebKitFormBoundary'];
        $postData = ['key' => 'value'];
        $filesData = ['file' => ['name' => 'test.txt', 'tmp_name' => $this->root->url() . '/tmp-test.txt', 'type' => 'text/plain']];
        $maxBodySize = 1;

        $result = $this->buildRawBodyFromSuperglobals($maxBodySize, null, $serverData, $postData, $filesData);

        $this->assertStringContainsString('Content-Disposition: form-data; name="key"', $result);
        $this->assertStringContainsString('Content-Disposition: form-data; name="file"; filename="test.txt"', $result);

        $this->assertEquals(248, strlen($result));
        $this->assertStringContainsString('THIS IS A TEST FILE', $result);
    }

    public function testBuildRawBodyFromSuperglobalsWithNoStreamShouldThrowException()
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
        } catch (CrowdSecBouncer\BouncerException $e) {
            $error = $e->getMessage();
        }

        $this->assertEquals('Stream is not a valid resource', $error);
    }

    public function testBuildRawBodyFromSuperglobalsWithNonMultipartContentTypeReturnsRawInput()
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

    public function testGetMultipartRawBodyWithLargeFileDataShouldThrowException()
    {
        $contentType = 'multipart/form-data; boundary=----WebKitFormBoundary';
        $postData = [];
        $filesData = ['file' => ['name' => 'test.txt', 'tmp_name' => $this->root->url() . '/phpYzdqkD', 'type' => 'text/plain']];
        // We don't create the file so it will throw an exception

        $error = '';
        try {
            $this->getMultipartRawBody($contentType, 1025, $postData, $filesData);
        } catch (CrowdSecBouncer\BouncerException $e) {
            $error = $e->getMessage();
        }

        $this->assertStringContainsString('Failed to read multipart raw body', $error);
        $this->assertStringContainsString('fopen(vfs://tmp/phpYzdqkD)', $error);
    }

    public function testGetMultipartRawBodyWithLargeFileDataTruncatesBody()
    {
        $contentType = 'multipart/form-data; boundary=----WebKitFormBoundary';
        $postData = [];
        $filesData = ['file' => ['name' => 'test.txt', 'tmp_name' => $this->root->url() . '/phpYzdqkD', 'type' => 'text/plain']];
        file_put_contents($this->root->url() . '/phpYzdqkD', 'THIS_IS_THE_CONTENT' . str_repeat('a', 2048));
        $threshold = 1025;

        $result = $this->getMultipartRawBody($contentType, $threshold, $postData, $filesData);

        $this->assertEquals(1025, strlen($result));
        $this->assertStringContainsString('THIS_IS_THE_CONTENT', $result);
    }

    public function testGetMultipartRawBodyWithLargeFileDataTruncatesBodyEnBoundary()
    {
        $contentType = 'multipart/form-data; boundary=----WebKitFormBoundary';
        $postData = [];
        $filesData = ['file' => ['name' => 'test.txt', 'tmp_name' => $this->root->url() . '/phpYzdqkD', 'type' => 'text/plain']];
        file_put_contents($this->root->url() . '/phpYzdqkD', str_repeat('a', 2045));
        // Total size without adding boundary is 2167
        $threshold = 2168;

        $result = $this->getMultipartRawBody($contentType, $threshold, $postData, $filesData);

        $this->assertEquals(2168, strlen($result));
    }

    /**
     * @group unit
     */
    public function testGetMultipartRawBodyWithLargeFileNameTruncatesBody()
    {
        $contentType = 'multipart/form-data; boundary=----WebKitFormBoundary';
        $postData = [];
        $filesData = ['file' => ['name' => str_repeat('a', 2048) . '.txt', 'tmp_name' => $this->root->url() . '/phpYzdqkD', 'type' => 'text/plain']];
        file_put_contents($this->root->url() . '/phpYzdqkD', 'THIS_IS_THE_CONTENT');
        $threshold = 1025;

        $result = $this->getMultipartRawBody($contentType, $threshold, $postData, $filesData);

        $this->assertEquals(1025, strlen($result));
        $this->assertStringNotContainsString('THIS_IS_THE_CONTENT', $result);
    }

    public function testGetMultipartRawBodyWithLargePostDataTruncatesBody()
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

    public function testReadStreamWithFreadFailureShouldThrowException()
    {
        // Register custom stream wrapper that fails on fread
        stream_wrapper_register('failing', FailingStreamWrapper::class);
        FailingStreamWrapper::$eofResult = false;
        FailingStreamWrapper::$readResult = false;

        // Open a stream using the failing stream wrapper
        $mockStream = fopen('failing://test', 'r+');

        // Set the threshold (can be any number)
        $threshold = 100;

        $error = '';
        try {
            $this->readStream($mockStream, $threshold);
        } catch (CrowdSecBouncer\BouncerException $e) {
            $error = $e->getMessage();
        }

        // Assert that the correct exception message was thrown
        $this->assertStringStartsWith('Failed to read stream: Failed to read chunk from stream', $error);

        // Clean up the custom stream wrapper
        stream_wrapper_unregister('failing');
    }

    public function testReadStreamShouldNotInfiniteLoop()
    {
        // Register custom stream wrapper that will read forever
        stream_wrapper_register('failing', FailingStreamWrapper::class);
        FailingStreamWrapper::$eofResult = false;
        FailingStreamWrapper::$readResult = '';

        // Open a stream using the failing stream wrapper
        $mockStream = fopen('failing://test', 'r+');

        // Set the threshold (can be any number)
        $threshold = 100;

        $error = '';
        try {
            $this->readStream($mockStream, $threshold);
        } catch (CrowdSecBouncer\BouncerException $e) {
            $error = $e->getMessage();
        }

        // Assert that the correct exception message was thrown
        $this->assertStringStartsWith('Failed to read stream: Too many loops while reading stream', $error);

        // Clean up the custom stream wrapper
        stream_wrapper_unregister('failing');
    }

    protected function setUp(): void
    {
        $this->root = vfsStream::setup('/tmp');
    }
}
