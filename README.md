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

Run the loader with `php main.php`.
