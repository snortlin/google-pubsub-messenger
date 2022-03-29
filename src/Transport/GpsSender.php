<?php

namespace Snortlin\GooglePubsubMessenger\Transport;

use Snortlin\GooglePubsubMessenger\Transport\Stamp\OrderingKeyStamp;
use Snortlin\GooglePubsubMessenger\Transport\Stamp\RedeliveryAsModifyAckDeadlineStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

final class GpsSender implements SenderInterface
{
    public function __construct(private Connection           $connection,
                                private ?SerializerInterface $serializer = null)
    {
        $this->serializer = $serializer ?? new PhpSerializer();
    }

    /**
     * @inheritDoc
     * @throws \Exception
     */
    public function send(Envelope $envelope): Envelope
    {
        if ($envelope->last(RedeliveryStamp::class) instanceof RedeliveryStamp) {
            if ($envelope->last(RedeliveryAsModifyAckDeadlineStamp::class) instanceof RedeliveryAsModifyAckDeadlineStamp) {
                return $envelope;
            }
        }

        $encodedMessage = $this->serializer->encode($envelope);
        $orderingKeyStamp = $envelope->last(OrderingKeyStamp::class);

        try {
            $id = $this->connection->publish(
                $encodedMessage['body'],
                $encodedMessage['headers'] ?? [],
                $orderingKeyStamp instanceof OrderingKeyStamp ? $orderingKeyStamp->getOrderingKey() : null
            )['messageIds'];
        } catch (\Throwable $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }

        return $envelope->with(new TransportMessageIdStamp($id));
    }
}
