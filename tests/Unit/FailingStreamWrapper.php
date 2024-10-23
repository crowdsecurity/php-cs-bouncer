<?php

namespace CrowdSecBouncer\tests\Unit;

class FailingStreamWrapper
{
    public $context;

    public static $readResult = false;

    public static $eofResult = false;

    public function stream_open($path, $mode, $options, &$opened_path)
    {
        return true;
    }

    public function stream_read($count)
    {
        // Returns false to simulate a failure during fread
        return self::$readResult;
    }

    public function stream_eof()
    {
        return self::$eofResult; // Returns false to keep the stream open for further reads
    }

    public function stream_close()
    {
        // Do nothing for now
    }
}
