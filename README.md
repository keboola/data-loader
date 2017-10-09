# Data Loader

Application which loads data from Storage API and stores them
in data folder. 

## Usage
The following environment variables are used for configuration:

- KBC_EXPORT_CONFIG - Serialized JSON configuration of input mapping, 
    see [description](https://developers.keboola.com/extend/common-interface/config-file/).
- KBC_TOKEN - Storage API token.
- KBC_DATADIR - Optional target directory, defaults to `/data/`
- KBC_RUNID - Optional RunID, that appends to the log
- KBC_STORAGEAPI_URL - Optional Storage API URL, if it's different from `https://connection.keboola.com`
- KBC_CONFIG_ID - Id of the transformation configuration.
- KBC_CONFIG_VERSION - Version of the KBC_CONFIG_ID transformation.
- KBC_ROW_ID - Id of a row of the KBC_CONFIG_ID transformation. 

Either KBC_EXPORT_CONFIG or the combination of KBC_CONFIG_ID, KBC_CONFIG_VERSION, KBC_ROW_ID is required.

Run the loader with `php main.php`.
