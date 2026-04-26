<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Aws\Ec2\Ec2Client;
use Aws\S3\S3Client;
use Aws\Sns\SnsClient;
use Aws\Sts\StsClient;
use Aws\CloudWatch\CloudWatchClient;
use Aws\Exception\AwsException;
use Illuminate\Support\Facades\Log;

/**
 * AwsController
 *
 * Real-time AWS resource management.
 * Credentials are passed per-request via headers (never stored server-side):
 *   X-Aws-Key, X-Aws-Secret, X-Aws-Region
 */
class AwsController extends Controller
{
    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function creds(Request $req): array
    {
        return [
            'key'    => $req->header('X-Aws-Key',    env('AWS_ACCESS_KEY_ID', '')),
            'secret' => $req->header('X-Aws-Secret', env('AWS_SECRET_ACCESS_KEY', '')),
            'token'  => $req->header('X-Aws-Token',  env('AWS_SESSION_TOKEN', '')),
            'region' => $req->header('X-Aws-Region', env('AWS_DEFAULT_REGION', 'us-east-1')),
        ];
    }

    private function getCredentialsArray(array $c): array
    {
        $creds = ['key' => $c['key'], 'secret' => $c['secret']];
        if (!empty($c['token'])) {
            $creds['token'] = $c['token'];
        }
        return $creds;
    }

    private function ec2(array $c): Ec2Client
    {
        return new Ec2Client([
            'version'     => 'latest',
            'region'      => $c['region'],
            'credentials' => $this->getCredentialsArray($c),
        ]);
    }

    private function s3(array $c): S3Client
    {
        return new S3Client([
            'version'     => 'latest',
            'region'      => $c['region'],
            'credentials' => $this->getCredentialsArray($c),
        ]);
    }

    private function sns(array $c): SnsClient
    {
        return new SnsClient([
            'version'     => 'latest',
            'region'      => $c['region'],
            'credentials' => $this->getCredentialsArray($c),
        ]);
    }

    private function ok(mixed $data): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data]);
    }

    private function err(string $msg, int $code = 400): JsonResponse
    {
        return response()->json(['success' => false, 'error' => $msg], $code);
    }

    // ─── Validate AWS credentials ─────────────────────────────────────────────

    public function validateCredentials(Request $req): JsonResponse
    {
        $c = $this->creds($req);
        if (!$c['key'] || !$c['secret']) {
            return $this->err('Missing AWS credentials');
        }
        if (empty($c['token'])) {
            return $this->err('Token is empty in backend. Headers received: ' . json_encode($req->headers->all()));
        }
        
        // Debug

        try {
            $sts = new StsClient([
                'version'     => 'latest',
                'region'      => $c['region'],
                'credentials' => $this->getCredentialsArray($c),
            ]);

            $identity = $sts->getCallerIdentity([]);

            return $this->ok([
                'account_id' => $identity['Account'],
                'user_arn'   => $identity['Arn'],
                'user_id'    => $identity['UserId'],
                'region'     => $c['region'],
            ]);
        } catch (AwsException $e) {
            return $this->err('Invalid credentials: ' . $e->getAwsErrorMessage(), 401);
        }
    }

    // ─── EC2 ──────────────────────────────────────────────────────────────────

    public function listInstances(Request $req): JsonResponse
    {
        try {
            $ec2 = $this->ec2($this->creds($req));
            $result = $ec2->describeInstances([
                'Filters' => [
                    ['Name' => 'instance-state-name', 'Values' => ['pending', 'running', 'stopping', 'stopped']],
                ],
            ]);

            $instances = [];
            foreach ($result['Reservations'] as $res) {
                foreach ($res['Instances'] as $inst) {
                    $name = '';
                    foreach ($inst['Tags'] ?? [] as $tag) {
                        if ($tag['Key'] === 'Name') { $name = $tag['Value']; break; }
                    }
                    $instances[] = [
                        'id'         => $inst['InstanceId'],
                        'name'       => $name,
                        'type'       => $inst['InstanceType'],
                        'state'      => $inst['State']['Name'],
                        'public_ip'  => $inst['PublicIpAddress'] ?? null,
                        'private_ip' => $inst['PrivateIpAddress'] ?? null,
                        'az'         => $inst['Placement']['AvailabilityZone'] ?? null,
                        'launched'   => isset($inst['LaunchTime'])
                            ? $inst['LaunchTime']->format('Y-m-d H:i:s')
                            : null,
                    ];
                }
            }

            return $this->ok(['instances' => $instances, 'count' => count($instances)]);
        } catch (AwsException $e) {
            return $this->err($e->getAwsErrorMessage());
        }
    }

    public function launchInstance(Request $req): JsonResponse
    {
        $req->validate([
            'instance_type' => 'nullable|string',
            'name'          => 'required|string',
        ]);

        try {
            $c   = $this->creds($req);
            $ec2 = $this->ec2($c);

            // Get Amazon Linux 2023 AMI
            $amiResult = $ec2->describeImages([
                'Filters' => [
                    ['Name' => 'name',         'Values' => ['amzn2-ami-hvm-*-x86_64-gp2']],
                    ['Name' => 'state',        'Values' => ['available']],
                ],
                'Owners' => ['amazon'],
            ]);

            if (empty($amiResult['Images'])) {
                return $this->err('No Amazon Linux AMI found in this region');
            }

            usort($amiResult['Images'], fn($a, $b) => strcmp($b['CreationDate'], $a['CreationDate']));
            $amiId = $amiResult['Images'][0]['ImageId'];

            $result = $ec2->runInstances([
                'ImageId'      => $amiId,
                'InstanceType' => $req->input('instance_type', 't3.micro'),
                'MinCount'     => 1,
                'MaxCount'     => 1,
                'TagSpecifications' => [[
                    'ResourceType' => 'instance',
                    'Tags'         => [
                        ['Key' => 'Name',    'Value' => $req->input('name')],
                        ['Key' => 'Project', 'Value' => 'FabricFlow'],
                        ['Key' => 'ManagedBy', 'Value' => 'FabricFlow-Dashboard'],
                    ],
                ]],
            ]);

            $inst = $result['Instances'][0];
            return $this->ok([
                'instance_id' => $inst['InstanceId'],
                'state'       => $inst['State']['Name'],
                'type'        => $inst['InstanceType'],
                'ami'         => $amiId,
                'message'     => "Instance {$inst['InstanceId']} launched. Wait ~60s for public IP.",
            ]);
        } catch (AwsException $e) {
            return $this->err($e->getAwsErrorMessage());
        }
    }

    public function terminateInstance(Request $req, string $instanceId): JsonResponse
    {
        try {
            $ec2 = $this->ec2($this->creds($req));
            $ec2->terminateInstances(['InstanceIds' => [$instanceId]]);
            return $this->ok(['message' => "Instance {$instanceId} termination initiated."]);
        } catch (AwsException $e) {
            return $this->err($e->getAwsErrorMessage());
        }
    }

    // ─── S3 ───────────────────────────────────────────────────────────────────

    public function listBuckets(Request $req): JsonResponse
    {
        try {
            $s3     = $this->s3($this->creds($req));
            $result = $s3->listBuckets();

            $buckets = array_map(fn($b) => [
                'name'    => $b['Name'],
                'created' => $b['CreationDate']->format('Y-m-d H:i:s'),
            ], $result['Buckets'] ?? []);

            return $this->ok(['buckets' => $buckets, 'count' => count($buckets)]);
        } catch (AwsException $e) {
            return $this->err($e->getAwsErrorMessage());
        }
    }

    public function createBucket(Request $req): JsonResponse
    {
        $req->validate(['name' => 'required|string|min:3|max:63']);

        try {
            $c    = $this->creds($req);
            $s3   = $this->s3($c);
            $name = strtolower($req->input('name'));

            $params = ['Bucket' => $name];
            if ($c['region'] !== 'us-east-1') {
                $params['CreateBucketConfiguration'] = ['LocationConstraint' => $c['region']];
            }

            $s3->createBucket($params);

            // Block public access immediately
            $s3->putPublicAccessBlock([
                'Bucket'                         => $name,
                'PublicAccessBlockConfiguration' => [
                    'BlockPublicAcls'       => true,
                    'BlockPublicPolicy'     => true,
                    'IgnorePublicAcls'      => true,
                    'RestrictPublicBuckets' => true,
                ],
            ]);

            // Tag it
            $s3->putBucketTagging([
                'Bucket'  => $name,
                'Tagging' => ['TagSet' => [
                    ['Key' => 'Project', 'Value' => 'FabricFlow'],
                    ['Key' => 'ManagedBy', 'Value' => 'FabricFlow-Dashboard'],
                ]],
            ]);

            return $this->ok(['bucket' => $name, 'region' => $c['region'],
                'message' => "Bucket '{$name}' created and secured."]);
        } catch (AwsException $e) {
            return $this->err($e->getAwsErrorMessage());
        }
    }

    public function deleteBucket(Request $req, string $bucket): JsonResponse
    {
        try {
            $s3 = $this->s3($this->creds($req));
            $s3->deleteBucket(['Bucket' => $bucket]);
            return $this->ok(['message' => "Bucket '{$bucket}' deleted."]);
        } catch (AwsException $e) {
            return $this->err($e->getAwsErrorMessage());
        }
    }

    // ─── SNS Notifications ────────────────────────────────────────────────────

    /**
     * Create an SNS topic for FabricFlow notifications.
     */
    public function createSnsTopic(Request $req): JsonResponse
    {
        $req->validate(['name' => 'required|string']);
        try {
            $sns    = $this->sns($this->creds($req));
            $result = $sns->createTopic(['Name' => $req->input('name')]);
            return $this->ok(['topic_arn' => $result['TopicArn']]);
        } catch (AwsException $e) {
            return $this->err($e->getAwsErrorMessage());
        }
    }

    /**
     * Subscribe an email to an SNS topic.
     */
    public function subscribeEmail(Request $req): JsonResponse
    {
        $req->validate(['topic_arn' => 'required|string', 'email' => 'required|email']);
        try {
            $sns = $this->sns($this->creds($req));
            $sns->subscribe([
                'TopicArn' => $req->input('topic_arn'),
                'Protocol' => 'email',
                'Endpoint' => $req->input('email'),
            ]);
            return $this->ok(['message' => "Confirmation email sent to {$req->input('email')}"]);
        } catch (AwsException $e) {
            return $this->err($e->getAwsErrorMessage());
        }
    }

    /**
     * Publish a notification event to SNS (called by order/stage events).
     */
    public function publishNotification(Request $req): JsonResponse
    {
        $req->validate([
            'topic_arn' => 'required|string',
            'subject'   => 'required|string',
            'message'   => 'required|string',
        ]);

        try {
            $sns = $this->sns($this->creds($req));
            $sns->publish([
                'TopicArn' => $req->input('topic_arn'),
                'Subject'  => $req->input('subject'),
                'Message'  => $req->input('message'),
            ]);
            return $this->ok(['message' => 'Notification published via SNS']);
        } catch (AwsException $e) {
            return $this->err($e->getAwsErrorMessage());
        }
    }

    /**
     * List SNS topics.
     */
    public function listSnsTopics(Request $req): JsonResponse
    {
        try {
            $sns    = $this->sns($this->creds($req));
            $result = $sns->listTopics();
            $topics = array_map(fn($t) => ['arn' => $t['TopicArn']], $result['Topics'] ?? []);
            return $this->ok(['topics' => $topics, 'count' => count($topics)]);
        } catch (AwsException $e) {
            return $this->err($e->getAwsErrorMessage());
        }
    }

    // ─── CloudWatch Metrics ────────────────────────────────────────────────────

    public function getMetrics(Request $req): JsonResponse
    {
        try {
            $c  = $this->creds($req);
            $cw = new CloudWatchClient([
                'version'     => 'latest',
                'region'      => $c['region'],
                'credentials' => $this->getCredentialsArray($c),
            ]);

            $alarms = $cw->describeAlarms(['MaxRecords' => 20]);
            $alarmList = array_map(fn($a) => [
                'name'  => $a['AlarmName'],
                'state' => $a['StateValue'],
                'reason'=> $a['StateReason'] ?? '',
            ], $alarms['MetricAlarms'] ?? []);

            return $this->ok(['alarms' => $alarmList, 'count' => count($alarmList)]);
        } catch (AwsException $e) {
            return $this->err($e->getAwsErrorMessage());
        }
    }
}
