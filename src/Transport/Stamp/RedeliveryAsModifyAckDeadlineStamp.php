<?php

namespace Snortlin\GooglePubsubMessenger\Transport\Stamp;

use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;

class RedeliveryAsModifyAckDeadlineStamp implements NonSendableStampInterface
{
    public function __construct(private int $delay = 0)
    {
    }

    public function getDelay(): int
    {
        return $this->delay;
    }
}
