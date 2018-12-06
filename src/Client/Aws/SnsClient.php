<?php

namespace Kfz24\QueueBundle\Client\Aws;

use Aws\Result;

/**
 * This client is used to interact with the **Amazon Simple Notification Service (Amazon SNS)**.
 *
 * @method \Aws\Result addPermission(array $args = [])
 * @method \GuzzleHttp\Promise\Promise addPermissionAsync(array $args = [])
 * @method \Aws\Result checkIfPhoneNumberIsOptedOut(array $args = [])
 * @method \GuzzleHttp\Promise\Promise checkIfPhoneNumberIsOptedOutAsync(array $args = [])
 * @method \Aws\Result confirmSubscription(array $args = [])
 * @method \GuzzleHttp\Promise\Promise confirmSubscriptionAsync(array $args = [])
 * @method \Aws\Result createPlatformApplication(array $args = [])
 * @method \GuzzleHttp\Promise\Promise createPlatformApplicationAsync(array $args = [])
 * @method \Aws\Result createPlatformEndpoint(array $args = [])
 * @method \GuzzleHttp\Promise\Promise createPlatformEndpointAsync(array $args = [])
 * @method \Aws\Result createTopic(array $args = [])
 * @method \GuzzleHttp\Promise\Promise createTopicAsync(array $args = [])
 * @method \Aws\Result deleteEndpoint(array $args = [])
 * @method \GuzzleHttp\Promise\Promise deleteEndpointAsync(array $args = [])
 * @method \Aws\Result deletePlatformApplication(array $args = [])
 * @method \GuzzleHttp\Promise\Promise deletePlatformApplicationAsync(array $args = [])
 * @method \Aws\Result deleteTopic(array $args = [])
 * @method \GuzzleHttp\Promise\Promise deleteTopicAsync(array $args = [])
 * @method \Aws\Result getEndpointAttributes(array $args = [])
 * @method \GuzzleHttp\Promise\Promise getEndpointAttributesAsync(array $args = [])
 * @method \Aws\Result getPlatformApplicationAttributes(array $args = [])
 * @method \GuzzleHttp\Promise\Promise getPlatformApplicationAttributesAsync(array $args = [])
 * @method \Aws\Result getSMSAttributes(array $args = [])
 * @method \GuzzleHttp\Promise\Promise getSMSAttributesAsync(array $args = [])
 * @method \Aws\Result getSubscriptionAttributes(array $args = [])
 * @method \GuzzleHttp\Promise\Promise getSubscriptionAttributesAsync(array $args = [])
 * @method \Aws\Result getTopicAttributes(array $args = [])
 * @method \GuzzleHttp\Promise\Promise getTopicAttributesAsync(array $args = [])
 * @method \Aws\Result listEndpointsByPlatformApplication(array $args = [])
 * @method \GuzzleHttp\Promise\Promise listEndpointsByPlatformApplicationAsync(array $args = [])
 * @method \Aws\Result listPhoneNumbersOptedOut(array $args = [])
 * @method \GuzzleHttp\Promise\Promise listPhoneNumbersOptedOutAsync(array $args = [])
 * @method \Aws\Result listPlatformApplications(array $args = [])
 * @method \GuzzleHttp\Promise\Promise listPlatformApplicationsAsync(array $args = [])
 * @method \Aws\Result listSubscriptions(array $args = [])
 * @method \GuzzleHttp\Promise\Promise listSubscriptionsAsync(array $args = [])
 * @method \Aws\Result listSubscriptionsByTopic(array $args = [])
 * @method \GuzzleHttp\Promise\Promise listSubscriptionsByTopicAsync(array $args = [])
 * @method \Aws\Result listTopics(array $args = [])
 * @method \GuzzleHttp\Promise\Promise listTopicsAsync(array $args = [])
 * @method \Aws\Result optInPhoneNumber(array $args = [])
 * @method \GuzzleHttp\Promise\Promise optInPhoneNumberAsync(array $args = [])
 * @method \Aws\Result publish(array $args = [])
 * @method \GuzzleHttp\Promise\Promise publishAsync(array $args = [])
 * @method \Aws\Result removePermission(array $args = [])
 * @method \GuzzleHttp\Promise\Promise removePermissionAsync(array $args = [])
 * @method \Aws\Result setEndpointAttributes(array $args = [])
 * @method \GuzzleHttp\Promise\Promise setEndpointAttributesAsync(array $args = [])
 * @method \Aws\Result setPlatformApplicationAttributes(array $args = [])
 * @method \GuzzleHttp\Promise\Promise setPlatformApplicationAttributesAsync(array $args = [])
 * @method \Aws\Result setSMSAttributes(array $args = [])
 * @method \GuzzleHttp\Promise\Promise setSMSAttributesAsync(array $args = [])
 * @method \Aws\Result setSubscriptionAttributes(array $args = [])
 * @method \GuzzleHttp\Promise\Promise setSubscriptionAttributesAsync(array $args = [])
 * @method \Aws\Result setTopicAttributes(array $args = [])
 * @method \GuzzleHttp\Promise\Promise setTopicAttributesAsync(array $args = [])
 * @method \Aws\Result subscribe(array $args = [])
 * @method \GuzzleHttp\Promise\Promise subscribeAsync(array $args = [])
 * @method \Aws\Result unsubscribe(array $args = [])
 * @method \GuzzleHttp\Promise\Promise unsubscribeAsync(array $args = [])
 */
class SnsClient extends AbstractAwsClient
{
    const RESOURCE_NAME = 'TopicArn';

    /**
     * @param mixed $message
     *
     * @return Result
     */
    public function send($message)
    {
        $args = ['Message' => json_encode($message)];

        return $this->publish($args);
    }

    /**
     * @param array $args
     *
     * @return void
     */
    public function receive(array $args = [])
    {
        throw new \LogicException("This client is configured as SNS client. You can't actively read from SNS!");
    }
}
