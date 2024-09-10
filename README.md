CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Installation


INTRODUCTION
------------

Provides a drush command to quickly initialize Drupal for Neo.


REQUIREMENTS
------------

This module requires Drupal core.


INSTALLATION
------------

If using DDEV:

```bash
composer require jacerider/neo_create && ddev drush en neo_create && ddev drush neo:create
```

If local:

```bash
composer require jacerider/neo_create && drush en neo_create && drush neo:create
```
