<?php

namespace CrowdSecBouncer\tests\Unit;

class FailingStreamWrapper
{
    public $context;

    public function stream_open($path, $mode, $options, &$opened_path)
    {
        return true;
    }

    public function stream_read($count)
    {
        // Simulate a failure during fread
        return false; // This will cause fread to fail and return false
    }

    public function stream_eof()
    {
        return false; // Keep the stream open for further reads
    }

    public function stream_close()
    {
        // Do nothing for now
    }
}
