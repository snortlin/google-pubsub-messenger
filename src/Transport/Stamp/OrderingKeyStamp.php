<?php

namespace Snortlin\GooglePubsubMessenger\Transport\Stamp;

use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;

class OrderingKeyStamp implements NonSendableStampInterface
{
    public function __construct(private string $orderingKey)
    {
    }

    public function getOrderingKey(): string
    {
        return $this->orderingKey;
    }
}
