# Garagist.ImageDirectory

[![Latest stable version]][packagist] [![GitHub stars]][stargazers] [![GitHub watchers]][subscription] [![GitHub license]][license] [![GitHub issues]][issues] [![GitHub forks]][network]

With Garagist.ImageDirectory (based on [Flowpack.Neos.AssetUsage]) you can create an image directory for all used images and videos on a [Neos CMS] website.

## Installation

Add the package, and the storage as dependency in your site package:

```bash
composer require --no-update garagist/imagedirectory flowpack/entity-usage-databasestorage
```

The run composer update in your project root.

Finally you need to run the command to build the initial usage index:

```bash
./flow assetusage:update
```

## Fusion prototypes

This package provides two main Fusion prototypes, which returns a `Neos.Fusion:DataStructure` with all the needed data.

- `Garagist.ImageDirectory:ByAsset`: All used images and videos, sort by asset (One asset can have multiple documents)
- `Garagist.ImageDirectory:ByDocument` All used images and videos, sort by document (One document can have multiple asssts)

With this data you can create your own view to output the assets. It is recomended to create a dedicated page and put it next to your imprint, etc.

## Node Types

This package provide one mixin: `Garagist.ImageDirectory:Mixin.Defaults`. This sets the defaults, as the image width, the prefix for the copyright text as well as the value if no `copyrightNotice` is set on the asset.


[packagist]: https://packagist.org/packages/garagist/imagedirectory
[latest stable version]: https://poser.pugx.org/garagist/imagedirectory/v/stable
[github issues]: https://img.shields.io/github/issues/Garagist/Garagist.ImageDirectory
[issues]: https://github.com/Garagist/Garagist.ImageDirectory/issues
[github forks]: https://img.shields.io/github/forks/Garagist/Garagist.ImageDirectory
[network]: https://github.com/Garagist/Garagist.ImageDirectory/network
[github stars]: https://img.shields.io/github/stars/Garagist/Garagist.ImageDirectory
[stargazers]: https://github.com/Garagist/Garagist.ImageDirectory/stargazers
[github license]: https://img.shields.io/github/license/Garagist/Garagist.ImageDirectory
[license]: LICENSE
[github watchers]: https://img.shields.io/github/watchers/Garagist/Garagist.ImageDirectory.svg
[subscription]: https://github.com/Garagist/Garagist.ImageDirectory/subscription
[neos cms]: https://www.neos.io
[flowpack.neos.assetusage]: https://github.com/Flowpack/Flowpack.Neos.AssetUsage
