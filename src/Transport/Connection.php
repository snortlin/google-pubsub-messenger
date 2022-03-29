<?php

namespace Snortlin\GooglePubsubMessenger\Transport;

use Google\Cloud\PubSub\Message;
use Google\Cloud\PubSub\MessageBuilder;
use Google\Cloud\PubSub\PubSubClient;
use JetBrains\PhpStorm\ArrayShape;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;

class Connection
{
    private PubSubClient $client;

    private function __construct(string         $key,
                                 private string $topicName,
                                 private string $subscriptionName,
                                 private int    $pullMaxMessages = 0,
                                 private int    $pullAckDeadline = 0,
                                 private int    $redeliveryAckDeadline = 0)
    {
        try {
            $key = json_decode($key, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new InvalidArgumentException(sprintf('Error when decoding Google Pub/Sub Key: %s', $e->getMessage()), $e->getCode(), $e);
        }

        try {
            $this->client = new PubSubClient([
                'keyFile' => $key,
                'projectId' => $key['project_id'] ?? null,
            ]);
        } catch (\Throwable $e) {
            throw new InvalidArgumentException(sprintf('Error when creating Google Pub/Sub Client: %s', $e->getMessage()), $e->getCode(), $e);
        }
    }

    /**
     * Creates a connection based on the DSN and options.
     *
     * Available options:
     *
     *   * topic: Topic name
     *   * subscription: Subscription name
     *   * key: GPS key JSON format
     *   * pull_max_messages: Limit the amount of messages pulled
     *   * pull_ack_deadline: The new ack deadline pulled messages
     *   * redelivery_ack_deadline: The new ack deadline on reject messages
     *
     * gps://default?topic=topic_name&subscription=subscription_name&key=base64_key
     * gps://default?topic=topic_name&subscription=subscription_name&key=base64_key&pull_max_messages=100
     * gps://default?topic=topic_name&subscription=subscription_name&key=base64_key&pull_max_messages=100&pull_ack_deadline=10&redelivery_ack_deadline=10
     */
    public static function fromDsn(string $dsn, array $options = []): self
    {
        if (false === $parsedUrl = parse_url($dsn)) {
            throw new InvalidArgumentException(sprintf('The given GPS DSN "%s" is invalid.', $dsn));
        }

        parse_str($parsedUrl['query'] ?? '', $parsedQuery);

        $options['topic'] = $parsedQuery['topic'] ?? $options['topic'] ?? '';
        $options['subscription'] = $parsedQuery['subscription'] ?? $options['subscription'] ?? '';
        $options['key'] = base64_decode($parsedQuery['key'] ?? $options['key'] ?? '');
        $options['pull_max_messages'] = (int)($parsedQuery['pull_max_messages'] ?? $options['pull_max_messages'] ?? 0);
        $options['pull_ack_deadline'] = (int)($parsedQuery['pull_ack_deadline'] ?? $options['pull_ack_deadline'] ?? 0);
        $options['redelivery_ack_deadline'] = (int)($parsedQuery['redelivery_ack_deadline'] ?? $options['redelivery_ack_deadline'] ?? 0);

        return new self($options['key'], $options['topic'], $options['subscription'], $options['pull_max_messages'], $options['pull_ack_deadline'], $options['redelivery_ack_deadline']);
    }

    public function publish(string $body, array $attributes = [], ?string $orderingKey = null): array
    {
        $messageBuilder = (new MessageBuilder())
            ->setData($body)
            ->setAttributes($attributes)
            ->setOrderingKey($orderingKey);

        return $this->client
            ->topic($this->topicName)
            ->publish($messageBuilder->build());
    }

    public function get(): iterable
    {
        $options = [];

        if ($this->pullMaxMessages > 0) {
            $options['maxMessages'] = $this->pullMaxMessages;
        }

        $messages = $this->client
            ->subscription($this->subscriptionName)
            ->pull($options);

        if ($this->pullAckDeadline > 0 && !empty($messages)) {
            $this->modifyAckDeadlineBatch($messages, $this->pullAckDeadline);
        }

        return $messages;
    }

    public function ack(Message $message): void
    {
        $this->client
            ->subscription($this->subscriptionName)
            ->acknowledge($message);
    }

    public function reject(Message $message): void
    {
        $this->ack($message);
    }

    public function modifyAckDeadline(Message $message, int $seconds = 0): void
    {
        $this->client
            ->subscription($this->subscriptionName)
            ->modifyAckDeadline($message, $seconds > 0 ? $seconds : $this->redeliveryAckDeadline);
    }

    public function modifyAckDeadlineBatch(array $messages, int $seconds): void
    {
        $this->client
            ->subscription($this->subscriptionName)
            ->modifyAckDeadlineBatch($messages, $seconds);
    }

    #[ArrayShape([
        'client' => 'array',
        'subscription' => 'array',
    ])]
    public function getDebugInfo(): array
    {
        return [
            'client' => $this->client->__debugInfo(),
            'subscription' => $this->client->subscription($this->subscriptionName)->__debugInfo(),
        ];
    }
}
