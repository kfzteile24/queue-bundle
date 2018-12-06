<?php

namespace PetsDeli\QueueBundle\Client\Aws;

use Aws\Result;
use Aws\Sns\Message;
use Aws\Sns\MessageValidator;

/**
 * @method \Aws\Result addPermission(array $args = [])
 * @method \GuzzleHttp\Promise\Promise addPermissionAsync(array $args = [])
 * @method \Aws\Result changeMessageVisibility(array $args = [])
 * @method \GuzzleHttp\Promise\Promise changeMessageVisibilityAsync(array $args = [])
 * @method \Aws\Result changeMessageVisibilityBatch(array $args = [])
 * @method \GuzzleHttp\Promise\Promise changeMessageVisibilityBatchAsync(array $args = [])
 * @method \Aws\Result createQueue(array $args = [])
 * @method \GuzzleHttp\Promise\Promise createQueueAsync(array $args = [])
 * @method \Aws\Result deleteMessage(array $args = [])
 * @method \GuzzleHttp\Promise\Promise deleteMessageAsync(array $args = [])
 * @method \Aws\Result deleteMessageBatch(array $args = [])
 * @method \GuzzleHttp\Promise\Promise deleteMessageBatchAsync(array $args = [])
 * @method \Aws\Result deleteQueue(array $args = [])
 * @method \GuzzleHttp\Promise\Promise deleteQueueAsync(array $args = [])
 * @method \Aws\Result getQueueAttributes(array $args = [])
 * @method \GuzzleHttp\Promise\Promise getQueueAttributesAsync(array $args = [])
 * @method \Aws\Result getQueueUrl(array $args = [])
 * @method \GuzzleHttp\Promise\Promise getQueueUrlAsync(array $args = [])
 * @method \Aws\Result listDeadLetterSourceQueues(array $args = [])
 * @method \GuzzleHttp\Promise\Promise listDeadLetterSourceQueuesAsync(array $args = [])
 * @method \Aws\Result listQueues(array $args = [])
 * @method \GuzzleHttp\Promise\Promise listQueuesAsync(array $args = [])
 * @method \Aws\Result purgeQueue(array $args = [])
 * @method \GuzzleHttp\Promise\Promise purgeQueueAsync(array $args = [])
 * @method \Aws\Result receiveMessage(array $args = [])
 * @method \GuzzleHttp\Promise\Promise receiveMessageAsync(array $args = [])
 * @method \Aws\Result removePermission(array $args = [])
 * @method \GuzzleHttp\Promise\Promise removePermissionAsync(array $args = [])
 * @method \Aws\Result sendMessage(array $args = [])
 * @method \GuzzleHttp\Promise\Promise sendMessageAsync(array $args = [])
 * @method \Aws\Result sendMessageBatch(array $args = [])
 * @method \GuzzleHttp\Promise\Promise sendMessageBatchAsync(array $args = [])
 * @method \Aws\Result setQueueAttributes(array $args = [])
 * @method \GuzzleHttp\Promise\Promise setQueueAttributesAsync(array $args = [])
 */
class SqsClient extends AbstractAwsClient
{
    const RESOURCE_NAME = 'QueueUrl';

    /**
     * @var MessageValidator
     */
    private $validator;

    /**
     * @param MessageValidator $validator
     */
    public function setValidator(MessageValidator $validator)
    {
        $this->validator = $validator;
    }

    /**
     * @param string|array $message
     *
     * @return Result
     *
     */
    public function send($message)
    {
        if (!is_array($message) || !array_key_exists('MessageBody', $message)) {
            $message = ['MessageBody' => json_encode($message)];
        }

        return $this->sendMessage($message);
    }

    /**
     * @param array $args
     *
     * @return Result
     */
    public function receive(array $args = [])
    {
        $response = $this->receiveMessage($args);
        $messages = $response['Messages'];

        if (null !== $messages) {
            $messages = array_map(
                function ($message) {
                    $body = json_decode($message['Body'], true);

                    if (JSON_ERROR_NONE === json_last_error() && is_array($body)) {
                        $message['Body'] = $this->handleSnsMessageBody($body);
                    }

                    return $message;
                },
                $messages
            );

            $response['Messages'] = $messages;
        }

        return $response;
    }

    /**
     * @param array $body
     *
     * @return string
     */
    private function handleSnsMessageBody(array $body)
    {
        // determining whether this is originally a SNS message by loading it
        // into a model which is checking for the presence of keys unique to
        // the SNS envelop and throws an exception otherwise
        try {
            $message = new Message($body);

            // if message is legit and valid unfold the body to get rid of the envelop
            if (null !== $this->validator && $this->validator->isValid($message)) {
                return $body['Message'];
            }
        } catch (\InvalidArgumentException $e) {
            // the constructor of the Message class throws the exception if the passed
            // data simply isn't representing a SNS Message…which is perfectly ok
        }

        return json_encode($body);
    }
}
