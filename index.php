<?php

use Aws\MediaLive\MediaLiveClient;
use Aws\MediaPackage\MediaPackageClient;
use Illuminate\Support\Facades\App;
require __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

function env(string $string, $default = '')
{
    return array_key_exists($string, $_ENV) ? $_ENV[$string] : $default;
}

function createFromTemplate(array $options)
{
    $template = json_decode(file_get_contents('media-live-template.json'), true);


    return array_merge($template, $options);
}

$config = [
    'credentials' => [
        'AWS_ACCESS_KEY_ID' => env('AWS_ACCESS_KEY_ID'),
        'AWS_SECRET_ACCESS_KEY' => env('AWS_SECRET_ACCESS_KEY'),
        'AWS_MEDIA_LIVE_REGION' => env('AWS_MEDIA_LIVE_REGION'),
    ],
    'media_live' => [
        'ROLE_ARN' => env('AWS_MEDIA_LIVE_CHANNEL_ROLE_ARN')
    ]
];

$sdk = new Aws\Sdk;

/** @var MediaLiveClient $mediaLiveClient */
$mediaLiveClient = $sdk->createClient('medialive', [
    'region' => $config['credentials']['AWS_MEDIA_LIVE_REGION'],
    'version' => 'latest'
]);

/** @var MediaPackageClient $mediaPackageClient */
$mediaPackageClient = $sdk->createClient('mediapackage', [
    'region' => $config['credentials']['AWS_MEDIA_LIVE_REGION'],
    'version' => 'latest'
]);

$input               = null;
$mediaPackageChannel = null;

try {
    $input = $mediaLiveClient->createInput([
        'Name'    => 'test_media_live',
        'Sources' => [
            [
                'Url' => 'rtmp://wowzaec2demo.streamlock.net/vod/mp4:BigBuckBunny_115k.mov',
            ]
        ],
        'Type'    => 'RTMP_PULL'
    ]);

    $mediaPackageChannel = $mediaPackageClient->createChannel([
        'Description' => 'A RTMP Media Package channel.',
        'Id'          => 'test_media_package'
    ]);

    $mediaLiveChannel = $mediaLiveClient->createChannel(createFromTemplate([
        'Name' => 'test_media_live_channel',
        'LogLevel'           => 'ERROR',
        'RoleArn'            => $config['media_live']['ROLE_ARN'],
        'Destinations' => [
            [
                'Id' => 'test-media-live-channel-destination',
                'MediaPackageSettings' => [
                    'ChannelId' => $mediaPackageChannel['Id']
                ]
            ]
        ],
        'InputAttachments'   => [
            [
                'InputId' => $input[ 'Input' ][ 'Id' ]
            ]
        ],
    ]));


} catch (\Exception $e) {
    if ($input) {
        $mediaLiveClient->deleteInput([
            'InputId' => $input[ 'Input' ][ 'Id' ]
        ]);
    }

    if ($mediaPackageChannel) {
        $mediaPackageClient->deleteChannel([
            'Id' => $mediaPackageChannel[ 'Id' ]
        ]);
    }

    echo $e->getMessage();
    echo $e->getTraceAsString();

}
