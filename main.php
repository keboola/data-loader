<?php

require "vendor/autoload.php";
require "Adapter.php";
require "ApplicationException.php";
require "InvalidConfigurationException.php";
require "Reader.php";
require "UserException.php";
require "Configuration.php";
require "Input/File/Manifest.php";
require "Input/File/Manifest/Adapter.php";
require "Input/Table/Manifest.php";
require "Input/Table/Manifest/Adapter.php";
require "Input/File.php";
require "Input/Table.php";

$config = "
{
    \"storage\": {
        \"input\": {
            \"tables\": [
                {
                    \"source\": \"in.c-dg-main.profiles\",
                    \"destination\": \"source.csv\",
                    \"limit\": 50
                },
                {
                    \"source\": \"in.c-main.anvil-history\",
                    \"destination\": \"source1.csv\",
                    \"columns\": [\"id\", \"mpg\", \"cylinders\", \"origin\"],
                    \"where_column\": \"origin\",
                    \"where_values\": [1, 3],
                    \"where_operator\": \"eq\"
                }
            ],
            \"files\": []
        }
    }
}";

$config = getenv('KBC_EXPORT_CONFIG');
var_dump($config);
$client = new \Keboola\StorageApi\Client(['token' => getenv('KBC_TOKEN')]);
$reader = new \Keboola\DockerBundle\Docker\StorageApi\Reader($client);
$configData = json_decode($config, true);
echo json_last_error_msg();
var_dump($configData);

$reader->downloadFiles($configData['storage']['input']['files'], '/data');
$reader->downloadTables($configData['storage']['input']['tables'], '/data');

//"{\"storage\":{\"input\":{\"tables\":[{\"source\":\"in.c-dg-main.profiles\",\"destination\":\"source.csv\",\"limit\":50},{\"source\":\"in.c-main.anvil-history\",\"destination\":\"source1.csv\",\"columns\":[\"id\",\"mpg\",\"cylinders\",\"origin\"],\"where_column\":\"origin\",\"where_values\":[1,3],\"where_operator\":\"eq\"}],\"files\":[]}}}"
