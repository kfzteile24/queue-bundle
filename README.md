QueueBundle
===========

This Bundle has been created by refactoring the petsdeli/queue-bundle 

## Installation

1. Run the following command to install the bundle

```bash
composer require kfzteile24/queue-bundle
```

2. Configure the clients you want to use in your application 

```yaml
# app/config/config.yml

kfz24_queue:
    clients:
        notify:
            type:                        "sns"
            region:                      "eu-central-1"
            resource:                    "arn:aws:sns:eu-central-1:123456789012:topic"
            access_key:                  "AKIAABCDEFGHIJKLMNOP"
            secret_access_key:           "s3CR3t4Cc3S5K3y"
        one_consumer:
            type:                        "sqs"
            region:                      "eu-central-1"
            resource:                    "https://sqs.eu-central-1.amazonaws.com/123456789012/one-queue"
            access_key:                  "AKIAABCDEFGHIJKLMNOP"
            secret_access_key:           "s3CR3t4Cc3S5K3y"
        another_consumer:
            type:                        "sqs"
            region:                      "eu-central-1"
            resource:                    "https://sqs.eu-central-1.amazonaws.com/123456789012/another-queue"
            access_key:                  "AKIAABCDEFGHIJKLMNOP"
            secret_access_key:           "s3CR3t4Cc3S5K3y"
            role_based:
              web_identity_token_file:    "%AWS_WEB_IDENTITY_TOKEN_FILE%"
              role_arn:                   "arn-role-XYZ"
              session_name:               "ABC-session-name"
            large_payload_client:
                region:                  "eu-central-1"
                endpoint:                "http://s3-eu-central-1.amazonaws.com/consumer_bucket"
                bucket:                  "consumer_bucket"
                access_key:              "AKIAABCDEFGHIJKLMNOP"
                secret_access_key:       "s3CR3t4Cc3S5K3y"
                use_path_style_endpoint: "true" | "false"
```

## Usage

Get your configured services from the container

```php
/** @var \Kfz24\QueueBundle\Client\Aws\SnsClient $client */
$client = $container->get('kfz24.queue.client.notify');

$client->send([
    'type' => 'notification', 
    'data' => [1, 2, 3]
]);
```

or inject them in your services as you see fit.

```xml
    <service id="app.consumer_command" class="AppBundle\Command\Consumer">
        <argument type="service" id="kfz24.queue.client.one_consumer" />
        
        <tag name="console.command" />
    </service>
```

## What's this about?

The QueueBundle provides an abstraction on top of AWS' PHP SDK `SqsClient` and `SnsClient` and makes them DI friendly. 
The purpose of the abstraction is to allow for more flexibility than when using the original client implementations directly. 

This is particularly the case when you decide to change your queue setup from e.g. a point-to-point queue 
between 1 producer and 1 consumer to a point-to-multipoint queue setup because you might need a 2nd, 3rd, … consumer
processing the same messages. This can easily been achieved by creating an SNS topic and subscribe as many queues 
as you like to it, instead of sending messages directly to a queue.
 
Using the native clients has drawbacks in situations like that. They don't share a common interface and expose their 
respective API methods as class methods. For instance the calls to send a message to either of them look different:

```php
$snsClient = new \Aws\Sns\SnsClient([…]);
$sqsClient = new \Aws\Sqs\SqsClient([…]);

$result = $snsClient->publish([
    'Message' => 'My Message'
]);

$result = $sqsClient->sendMessage([
    'MessageBody' => 'My Message'
]);
```

This alone turns an architectural decision of having one or more consumers into a refactoring nightmare. But you 
would also need awareness of the architecture of your queues on the consumer side. As a matter of facts, the same
message looks quite differently depending on whether it was posted directly to the SQS queue or was forwarded there
through an SNS topic. Let's assume a message

```json
{"foo": "bar"}
```

When sent directly to a queue the `MessageBody` looks as you would expect it:

```json
{"foo": "bar"}
```

But if the message was queued through an SNS topic an SNS envelop is added turning the `MessageBody` into:

```json
{
    "Type" : "Notification",
    "MessageId" : "4743aa35-e3cd-4562-bd9e-b25778778206",
    "TopicArn" : "arn:aws:sns:eu-central-1:123456789012:sns-test",
    "Message" : "{\"foo\": \"bar\"}",
    "Timestamp" : "2017-05-23T09:58:23.595Z",
    "SignatureVersion" : "1",
    "Signature" : "MDEyMzQ1Njc4OTAwMTIzNDU2Nzg5MDAxMjM0NTY3ODkwMDEyMzQ1Njc4OTAwMTIzNDU2N …",
    "SigningCertURL" : "https://sns.eu-central-1.amazonaws.com/SimpleNotificationService …",
    "UnsubscribeURL" : "https://sns.eu-central-1.amazonaws.com/?Action=Unsubscribe&Subsc …",
    "MessageAttributes" : {
        "AWS.SNS.MOBILE.MPNS.Type" : {"Type":"String","Value":"token"},
        "AWS.SNS.MOBILE.MPNS.NotificationClass" : {"Type":"String","Value":"realtime"},
        "AWS.SNS.MOBILE.WNS.Type" : {"Type":"String","Value":"wns/badge"}
    }
}
```

Therefore this bundle's `SqsClient` implementation detects whether the `MessageBody` contains an SNS envelop and if so 
first validates its integrity and then automatically unwraps its content. This is completely transparent to you.
