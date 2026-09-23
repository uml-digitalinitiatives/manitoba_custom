# Manitoba Custom

This is a Drupal 10+ module that provides some specific customization/functionality for the 
University of Manitoba Libraries' Islandora instance.

## Installation
Install as a normal Drupal module. This module has the following dependencies:
- Drupal Fields
- [Drupal Views](https://www.drupal.org/project/views)
- [Search API](https://www.drupal.org/project/search_api)
- [Search API Solr](https://www.drupal.org/project/search_api_solr)
- [Islandora](https://www.drupal.org/project/islandora)

This module also adds support for skipping the creation of Pathauto aliases for Islandora content
with a specific content model. To use this feature, you must have the [Pathauto](https://www.drupal.org/project/pathauto) module installed and enabled.

## Features
- Adds a redirect for legacy Islandora (7.x) content.
- Adds a Formatter to format a Linked Data field to redirect to an internal view.
- Adds a way to skip Pathauto Alias creation based on Islandora content types.
- Multiple U of M specific customizations.

### Legacy Islandora Redirect
This feature adds a redirect for legacy Islandora (7.x) content. It will redirect any requests for `/islandora/object/{pid}` to the new path. 
It requires a field on the Islandora content type that stores the legacy PID. The field name must be configured in the module's settings. You can
choose multiple fields which will be checked in order for a match. If a match is found, the user will be redirected to the new path for that content.

### Linked Data Formatter
This feature adds a field formatter to format a Linked Data field as a redirect to an internal view. The formatter is configured
in the display of the content type's fields.

### Skip Pathauto Alias Creation
This feature allows you to skip the creation of Pathauto aliases for Islandora content with a specific content model. 
You can configure the content models for which you want to skip alias creation in the module's settings.
***Note***: This feature requires the Pathauto module to be installed and enabled. If Pathauto is not installed, this feature will not work.
Additionally, this feature makes several assumptions based on a default Islandora installation, namely:
- The node bundle is named `islandora_object`.
- The `islandora_object` bundle has a field named `field_model` that links to a taxonomy vocabulary.

### Additional Customizations
This module also:
- Changes the button text for openId generic login to "Log in with your U Manitoba Account"
- Removes the "close" button and disables the "workspace" from the Mirador IIIF viewer.

## Configuration
The module has a simple configuration page under Admin -> Configuration -> Islandora -> Manitoba Custom.
