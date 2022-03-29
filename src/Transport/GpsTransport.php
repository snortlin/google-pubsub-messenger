<?php

namespace Snortlin\GooglePubsubMessenger\Transport;

use JetBrains\PhpStorm\ArrayShape;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

class GpsTransport implements TransportInterface
{
    private GpsReceiver $receiver;
    private GpsSender $sender;

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
        return $this->getReceiver()->get();
    }

    /**
     * @inheritDoc
     */
    public function ack(Envelope $envelope): void
    {
        $this->getReceiver()->ack($envelope);
    }

    /**
     * @inheritDoc
     */
    public function reject(Envelope $envelope): void
    {
        $this->getReceiver()->reject($envelope);
    }

    /**
     * @inheritDoc
     * @throws \Exception
     */
    public function send(Envelope $envelope): Envelope
    {
        return $this->getSender()->send($envelope);
    }

    #[ArrayShape([
        'client' => 'array',
        'subscription' => 'array',
    ])]
    public function getDebugInfo(): array
    {
        return $this->connection->getDebugInfo();
    }

    private function getReceiver(): GpsReceiver
    {
        if (!isset($this->receiver)) {
            $this->receiver = new GpsReceiver($this->connection, $this->serializer);
        }

        return $this->receiver;
    }

    private function getSender(): GpsSender
    {
        if (!isset($this->sender)) {
            $this->sender = new GpsSender($this->connection, $this->serializer);
        }

        return $this->sender;
    }
}
