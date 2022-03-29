<?php

namespace Snortlin\GooglePubsubMessenger\Transport;

use Google\Cloud\PubSub\Message;
use Snortlin\GooglePubsubMessenger\Transport\Stamp\GpsReceivedStamp;
use Snortlin\GooglePubsubMessenger\Transport\Stamp\RedeliveryAsModifyAckDeadlineStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

final class GpsReceiver implements ReceiverInterface
{
    public function __construct(private Connection           $connection,
                                private ?SerializerInterface $serializer = null)
    {
        $this->serializer = $serializer ?? new PhpSerializer();
    }

    /**
     * @inheritDoc
     */
    public function get(): iterable
    {
        try {
            foreach ($this->connection->get() as $message) {
                yield from $this->getEnvelope($message);
            }
        } catch (MessageDecodingFailedException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function ack(Envelope $envelope): void
    {
        try {
            $this->connection->ack($this->findReceivedStamp($envelope)->getMessage());
        } catch (\Throwable $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function reject(Envelope $envelope): void
    {
        try {
            $stamp = $envelope->last(RedeliveryAsModifyAckDeadlineStamp::class);

            if ($stamp instanceof RedeliveryAsModifyAckDeadlineStamp) {
                $this->connection->modifyAckDeadline($this->findReceivedStamp($envelope)->getMessage(), $stamp->getDelay());
            } else {
                $this->connection->ack($this->findReceivedStamp($envelope)->getMessage());
            }
        } catch (\Throwable $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }
    }

    private function getEnvelope(Message $message): iterable
    {
        try {
            $envelope = $this->serializer->decode([
                'body' => $message->data(),
                'headers' => $message->attributes(),
                'message' => $message,
            ]);
        } catch (MessageDecodingFailedException $e) {
            $this->connection->reject($message);

            throw $e;
        }

        yield $envelope->with(new GpsReceivedStamp($message));
    }

    private function findReceivedStamp(Envelope $envelope): GpsReceivedStamp
    {
        $stamp = $envelope->last(GpsReceivedStamp::class);

        return $stamp instanceof GpsReceivedStamp
            ? $stamp
            : throw new \LogicException(sprintf('No "%s" stamp found on the Envelope.', GpsReceivedStamp::class));
    }
}
