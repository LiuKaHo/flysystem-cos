<?php
/**
 * Created by PhpStorm.
 * User: Liu
 * Date: 12/20/2018
 * Time: 9:52 PM
 */

namespace Liukaho\Flysystem\Cos;

use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\Adapter\CanOverwriteFiles;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;
use League\Flysystem\Util;
use Qcloud\Cos\Client;
use Qcloud\Cos\Exception\NoSuchKeyException;

class CosAdapter extends AbstractAdapter implements AdapterInterface
{

    protected $cos_client;

    protected $bucket;

    protected $config;

    protected static $metaOptions = [
        'CacheControl',
        'ContentDisposition',
        'ContentEncoding',
        'ContentLanguage',
        'ContentLength',
        'ContentType',
        'Expires',
        'GrantFullControl',
        'GrantRead',
        'GrantWrite',
        'Metadata',
        'StorageClass'
    ];

    public function __construct(string $secretId, string $secretKey, string $bucket, string $region = '', string $token = '', array $option = array())
    {
        $this->bucket = $bucket;
        $config['region'] = $region;
        $credentials['secretId'] = $secretId;
        $credentials['secretKey'] = $secretKey;
        if(array_key_exists('cdn', $option) && !empty($option['cdn'])) {
            $this->setPathPrefix($option['cdn']);
        }
        if (!empty($token)){
            $credentials['token'] = $token;
        }
        $config['credentials'] = $credentials;
        $this->config = array_merge($config, $option);

    }



    public function setBucket(string $bucket)
    {
        $this->bucket = $bucket;
    }

    public function getBucket() : string
    {
        return $this->bucket;
    }

    public function getRegion()
    {
        return $this->config['region'];
    }

    public function getKey($path) : string
    {
        return $this->applyPathPrefix($path);
    }

    public function getClient()
    {
        return $this->cos_client ?? new Client($this->config);
    }

    public function getConfig(string $key)
    {
        return array_key_exists($key, $this->config) ? $this->config[$key] : null;
    }



    public function read($path)
    {
        try{
            $response = $this->getClient()->getObject([
                'Bucket'=>$this->getBucket(),
                'Key'=>$this->getKey($path)
            ]);
            return ['contents'=>(string)$response['Body']];
        }catch(NoSuchKeyException $exception){
            return null;
        }
    }

    public function getTimestamp($path)
    {
        return $this->getMetadata($path)['LastModified'];
    }

    public function getMetadata($path)
    {
        $param['Bucket'] = $this->getBucket();
        $param['Key'] = $this->getKey($path);
        return $this->getClient()->headObject($param)->toArray();
    }



    public function copy($path, $newpath)
    {
        $source = $this->getSource($this->getKey($path));
        return boolval($this->getClient()->Copy($this->getBucket(), $this->getKey($newpath), $source));
    }

    public function createDir($dirname, Config $config)
    {
        return $this->getClient()->putObject([
            'Bucket' => $this->getBucket(),
            'Key' => $dirname. '/',
            'Body' => ''
        ]);
    }

    public function delete($path)
    {
        $param['Bucket'] = $this->getBucket();
        $param['Key'] = $path;
        $this->getClient()->deleteObject($param);

        return !$this->has($path);
    }

    public function deleteDir($dirname)
    {
        $lists = $this->listContents($dirname, true);
        if (empty($lists)){
            return true;
        }

        $objects = array_map(function ($item){
            return ['Key' => $item['path']];
        }, $lists);


        return (bool) $this->getClient()->deleteObjects([
            'Bucket' => $this->getBucket(),
            'Objects' => $objects
        ]);
    }

    public function getMimetype($path)
    {
        $meta = $this->getMetadata($path);

        return isset($meta['ContentType']) ? $meta['ContentType'] : false;
    }



    public function getSize($path)
    {
       $meta = $this->getMetadata($path);

       return isset($meta['ContentLength']) ? $meta['ContentLength'] : false;
    }

    public function getVisibility($path)
    {
        $acl = $this->getClient()->getObjectAcl([
            'Bucket'=>$this->getBucket(),
            'Key'=>$this->getKey($path)
        ]);

        foreach($acl->get('Grants') as $grant){
            if ($grant['Permission'] !== 'WRITE'){
                return ['visibility' => AdapterInterface::VISIBILITY_PUBLIC];
            }
        }

        return ['visibility' => AdapterInterface::VISIBILITY_PUBLIC];

    }

    public function has($path)
    {
        return $this->getClient()->doesObjectExist($this->getBucket(), $path);
    }

    public function listContents($directory = '', $recursive = false)
    {
        $objects = $this->getObjectList($directory, $recursive);

        $lists = array();

        foreach($objects as $object){
            $lists[] = $this->getFileInfo($object);
        }

        return $lists;
    }

    public function readStream($path)
    {
        try {
            $response = $this->getClient()->getObject([
                'Bucket' => $this->getBucket(),
                'Key' => $this->getKey($path)
            ]);

            return ['stream'=>$response['Body']->getStream()];
        }catch (NoSuchKeyException $exception){
            return false;
        }
    }



    public function rename($path, $newpath)
    {
        if($this->copy($path, $newpath)){
            return $this->delete($path);
        }
        return false;
    }



    public function setVisibility($path, $visibility)
    {
        return (bool)$this->getClient()->putObjectACL([
            'Bucket' => $this->getBucket(),
            'Key' => $this->getKey($path),
            'ACL' => $this->getCosVisibility($visibility)
        ]);
    }

    public function update($path, $contents, Config $config)
    {
        return $this->upload($path, $contents, $config);
    }

    public function updateStream($path, $resource, Config $config)
    {
        return $this->upload($path, $resource, $config);
    }

    /**
     *
     * upload the content
     *
     * @param string $path
     * @param string $contents
     * @param Config $config
     * @return array|false|\Guzzle\Http\Url|string
     */
    public function write($path, $contents, Config $config)
    {
        return $this->upload($path, $contents, $config);
    }

    public function writeStream($path, $resource, Config $config)
    {
        return $this->upload($path, $resource, $config);
    }

    /**
     * @return string
     */
    public function getPathSeparator()
    {
        return $this->pathSeparator;
    }

    /**
     * @param string $pathSeparator
     */
    public function setPathSeparator($pathSeparator)
    {
        $this->pathSeparator = $pathSeparator;
    }

    /**
     *
     * get the source
     *
     * @param $path
     * @return string
     */
    public function getSource($path) : string
    {
        return sprintf("%s.cos.%s.myqcloud.com/%s", $this->getBucket(), $this->getRegion(), $path);
    }

    /**
     *
     * upload the content
     *
     *
     * @param $path
     * @param $contents
     * @param Config $config
     * @return \Guzzle\Http\Url|string
     */

    protected function upload($path, $contents, Config $config)
    {
        $option = $this->getUploadOption($config);
        $key = $this->getKey($path);
        $this->getClient()->upload(
            $this->getBucket(),
            $key,
            $contents,
            $option
        );

        return $this->getClient()->getObjectUrl($this->getBucket(), $key);
    }

    protected function getUploadOption(Config $config) : array
    {
        $options = array();

        if ($config->has('params')){
            $options = $config->get('params');
        }

        if ($config->has('visibility')){
            $options['params']['ACL'] = $this->getCosVisibility($config->get('visibility'));
        }

        return $options;
    }

    protected function getCosVisibility($visibility)
    {
        if ($visibility == AdapterInterface::VISIBILITY_PUBLIC){
            $visibility = 'public-read';
        }

        return $visibility;
    }

    protected function getObjectList($directory = '', $recursive = false)
    {
        $res = $this->getClient()->listObjects([
            'Bucket'=>$this->getBucket(),
            'Prefix' => ((string)$directory === '') ? '' : ($directory.'/'),
            'Delimiter' => $recursive ? '' : '/'
        ]);

        return $res->get('Contents');
    }

    protected function getFileInfo(array $file_info)
    {
        $file = array();
        $file['path'] = $file_info['Key'];
        $file['type'] = substr($file_info['Key'], '-1') === '/' ? 'dir' : 'file';
        $file['size'] = intval($file_info['Size']);
        $file['timestamp'] = strtotime($file_info['LastModified']);

        return $file;
    }
}