<?php
use DreamCommerce\Client;
use DreamCommerce\Exceptions\ClientException;
use DreamCommerce\Exceptions\ResourceException;

require 'Config.php';

try {
    $client = new Client(
        'http://example.com', Config::APPID, Config::APP_SECRET
    );

    $client->setAccessToken('<INSERT TOKEN HERE>');

    $resource = $client->producers;
    // or
    //$resource = new \DreamCommerce\Resource\Producer($client);

    $insertedId = $resource->put(2, array(
        'name' => 'Awesome Manufacturer!'
    ));

    printf("Object modified");

} catch (ClientException $ex) {
    printf("An error occurred during the Client initialization: %s", Client::getError($ex));
} catch (ResourceException $ex) {
    printf("An error occurred during Resource access: %s", Client::getError($ex));
}