# Data Loader

Application which loads data from Storage API and stores them in a data folder. It is used to load data 
to Docker Sandboxes. There are two modes of operation:

- A transformation sandbox is being created - `KBC_CONFIG_ID` and related variables need to be provided.
- A plain sandbox is being created - `KBC_EXPORT_CONFIG` variable needs to be provided. 

## Usage
The following environment variables are used for configuration (see .env.template):

- `KBC_EXPORT_CONFIG` - Serialized JSON configuration of input mapping, 
    see [description](https://developers.keboola.com/extend/common-interface/config-file/).
- `KBC_TOKEN` - Storage API token.
- `KBC_DATADIR` - Optional target directory, defaults to `/data/`
- `KBC_RUNID` - Optional RunID, that appends to the log
- `KBC_STORAGEAPI_URL` - Optional Storage API URL, if it's different from `https://connection.keboola.com`
- `KBC_COMPONENT_ID` - optional Id of the transformation component (only for V2 transformations).
- `KBC_CONFIG_ID` - Id of the transformation configuration.
- `KBC_CONFIG_VERSION` - Version of the `KBC_CONFIG_ID` transformation.
- `KBC_ROW_ID` - optional Id of a row of the `KBC_CONFIG_ID` transformation. (only for legacy transformations)
- `KBC_VARIABLE_VALUES_ID` - Optional, id of the variable values (only for v2 transformations)
- `KBC_VARIABLE_VALUES_DATA` - Optional, array of variable values data (only for v2 transforamtions) 


Either KBC_EXPORT_CONFIG or the combination of `KBC_CONFIG_ID`, `KBC_CONFIG_VERSION`, `KBC_ROW_ID` is required.

Run the loader with `php src/run.php`.

## Development

### Init

```
git clone https://github.com/keboola/data-loader
cd data-loader
docker-compose build
docker-compose run --rm dev composer install
```

### Tests
Create `.env` file:
```
KBC_TEST_URL=https://connection.keboola.com/
KBC_TEST_TOKEN=
```

Run tests:
```
docker-compose run --rm dev composer ci
```

## License

MIT licensed, see [LICENSE](./LICENSE) file.
