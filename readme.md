﻿# Assets for integration of json-editor and WMS identity management## DescriptionThis repository consists of javascript and php scripts for integration of the forms generator [json-editor](https://github.com/json-editor/json-editor) andOCLC's [identity management API](https://www.oclc.org/developer/develop/web-services/worldshare-identity-management-api.en.html).The forms generator uses [json schema](https://json-schema.org/) to build forms.This repository must be installed as a subdirectory in a stand alone environment or inside a Wordpress theme directory. ## DependenciesTwig is used. See [https://twig.symfony.com/](https://twig.symfony.com/). If Twig is not installed (e.g. by a plugin)the goto the directory *.../je_assets* and follow the instructions on [https://twig.symfony.com/doc/2.x/installation.html](https://twig.symfony.com/doc/2.x/installation.html).## ConfigurationAll configuration for PHP scripts is in *.../je_assets/php/settings.php*. You might need to change some settings when copying to a production environment.All configuration for Javascript files is in *.../je_assets/jsoneditor/settings.js*. You might need to change some settings when copying to a production environment.## Dependencies* OCLC's authorization* OCLC's IDM API* mysqli in PHP* template engine Twig