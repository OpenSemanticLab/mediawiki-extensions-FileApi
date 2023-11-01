# mediawiki-extensions-FileApi

Implements file downloads via the action api for consumption by any client, including api-only clients (e. g. via bot password or OAuth)


## Endpoints

action=download
- title: The title of the file page
- pageid: The pageid of the file page
- format: The result format if an error occurs (ApiBase error message)

## Example

https://my.wiki.com/w/api.php?action=download&format=json&title=File:SomeFile.png

