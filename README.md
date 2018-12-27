<h1 align="center"> flysystem-cos </h1>

<p align="center"> QCloud COS storage Flysystem Adapter</p>


## Installing

```shell
$ composer require liukaho/flysystem-cos
```

## Usage
```
use Liukaho\Flysystem\Cos\CosAdapter;
use League\Flysystem\Filesystem;

$adapter = new CosAdapter('secretId', 'secretKey', 'bucket', 'region');
$flysystem = new Filesystem($adapter);
```
详细请参考 [Flysystem](http://flysystem.thephpleague.com/docs/)

## Contributing

## License

MIT