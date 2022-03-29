# Google Pub/Sub Messenger

## Installation

### Step 1: Install

The preferred method of installation is via [Composer](https://getcomposer.org/):

```bash
composer require snortlin/google-pubsub-messenger
```

### Step 2: Register Messenger Transport

```yaml
# config/services.yaml
services:
    Snortlin\GooglePubsubMessenger\Transport\GpsTransportFactory:
        tags: [ messenger.transport_factory ]
```

### Step 3: Configure Symfony Messenger

Create a connection based on the DSN and options:

```yaml
framework:
    messenger:
        transports:
            google-pubsub:
                dsn: 'gps://default?topic=topic_name&subscription=subscription_name&key=base64_key'
```

## Usage

### Configuration options

* `topic`: Topic name
* `subscription`: Subscription name
* `key`: GPS key JSON format
* `pull_max_messages`: Limit the amount of messages pulled; default=0 (GPC default => 1000)
* `pull_ack_deadline`: The new ack deadline pulled messages; default=0 (GPC default => 10)
* `redelivery_ack_deadline`: Default ack deadline on redelivered messages, default=0

### OrderingKeyStamp for ordering messages

```php
use Snortlin\GooglePubsubMessenger\Transport\Stamp\OrderingKeyStamp
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

public function index(MessageBusInterface $bus)
{
    $bus->dispatch(new MyMessage('...'), [
        new OrderingKeyStamp('my_ordering_key'),
    ]);

    // ...
}
```

[Official documentation](https://cloud.google.com/pubsub/docs/ordering)
