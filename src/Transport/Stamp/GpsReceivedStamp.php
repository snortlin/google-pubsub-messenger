<?php

namespace Snortlin\GooglePubsubMessenger\Transport\Stamp;

use Google\Cloud\PubSub\Message;
use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;

class GpsReceivedStamp implements NonSendableStampInterface
{
    public function __construct(private Message $message)
    {
    }

    public function getMessage(): Message
    {
        return $this->message;
    }
}
