# DB Exporter

This plugin handles serialized strings as well.

- Required PHP version `>=7.4.1`. Tested and works fine up to `8.1.9`.

## Filters
#### - `ldbe/permission`
*Parameters*
`apply_filters('ldbe/permission', $args);`
- $args (string) User permission (default: administrator).

## Actions
#### - `ldbe/exported`
- Fires on DB export.
