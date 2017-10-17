<?php

include(__DIR__ . "/../vendor/autoload.php");

echo "Uploading fixtures to File Uploads\n";

$client = new \Keboola\StorageApi\Client([
    "token" => getenv("KBC_TOKEN"),
    "url" => getenv("KBC_STORAGEAPI_URL")
]);

$fileUploadOptions = new \Keboola\StorageApi\Options\FileUploadOptions();
$fileUploadOptions->setTags(["my-file"]);
$client->uploadFile(__DIR__ . "/files/dummy", $fileUploadOptions);
