<?php

declare(strict_types=1);

namespace CrowdSecBouncer\Fixes\Memcached;

use Symfony\Component\Cache\Adapter\TagAwareAdapter as SymfonyTagAwareAdapter;

// This class is used only to know explicitly that we instantiate a Memcached adapter
class TagAwareAdapter extends SymfonyTagAwareAdapter
{
}
