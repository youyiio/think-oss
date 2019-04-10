<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/3/31
 * Time: 11:34
 */

namespace think\oss;

use OSS\Core\OssException;
use Qiniu\Auth;
use think\facade\Env;
use \OSS\OssClient;
use think\facade\Log;
use \Qiniu\Storage\UploadManager;

class OSSContext
{
    private $cg;

    public function __construct($vendor)
    {
        if ($vendor === 'aliyun') {
            $this->cg = new Aliyun();
        } else if ($vendor === 'qiniuyun') {
            $this->cg = new Qiniuyun();
        }
    }

    public function doUpload($savePath = '', $category = '', $isUnlink = false)
    {
        return $this->cg->upload($savePath, $category, $isUnlink);
    }
}

interface ImageUpload
{
    function upload();
}

//具体策略角色
class Aliyun implements ImageUpload
{
    public function upload($savePath = '', $category = '', $isUnlink = false)
    {
        //阿里云配置参数
        $aliKeyId = get_config('ali_key_id');//去阿里云后台获取秘钥id
        $aliKeySecret = get_config('ali_key_secret');//获取oss秘钥
        $aliEndpoint = get_config('ali_endpoint');//OSS地址
        $aliBucket = get_config('ali_bucket');//bucket
        $aliOss = get_config('ali_url');

        $ossClient = new OssClient($aliKeyId, $aliKeySecret, $aliEndpoint);//实例化
        if (!$ossClient->doesBucketExist($aliBucket)) {    //判断bucketname是否存在，不存在就去创建
            $ossClient->createBucket($aliBucket);
        }

        $category = empty($category) ? $aliBucket : $category;//cms
        $savePath = str_replace("\\", "/", $savePath);
        $object = $category . '/' . $savePath; //
        $path = Env::get('root_path') . 'public' . DIRECTORY_SEPARATOR . 'upload';
        $filePath = $path . DIRECTORY_SEPARATOR . $savePath; //文件路径，必须是本地的。

        try {
            $ossClient->uploadFile($aliBucket, $object, $filePath);
            if ($isUnlink == true) {
                unlink($filePath);
            }
        } catch (OssException $e) {
            Log::error($e->getErrorMessage());
            $e->getErrorMessage();
        }

        return "//" . $aliOss . "/" . $object;
    }
}

class Qiniuyun implements ImageUpload
{
    public function upload($savePath = '', $category = '', $isUnlink = false)
    {
        //七牛云配置参数
        $qiuniuKeyId = get_config('qiniu_key_id');
        $qiniuKeySecret = get_config('qiniu_key_secret');
        $qiniuBucket = get_config('qiniu_bucket');
        $qiniuUrl = get_config('qiniu_url');

        $category = empty($category) ? $qiniuBucket : $category;
        $savePath = str_replace("\\", "/", $savePath);
        $object = $category . '/' . $savePath;
        $path = Env::get('root_path') . 'public' . DIRECTORY_SEPARATOR . 'upload';
        $filePath = $path . DIRECTORY_SEPARATOR . $savePath;

        // 构建鉴权对象
        $auth = new Auth($qiuniuKeyId, $qiniuKeySecret);
        $token = $auth->uploadToken($qiniuBucket);
        if ($isUnlink == true) {
            unlink($filePath);
        }

        $uploadMgr = new UploadManager();
        list($ret, $err) = $uploadMgr->putFile($token, $object, $filePath);
        if ($err !== null) {
            $this->error($err);
        } else {
            return "//" . $qiniuUrl . "/" . $ret['key'];
        }
    }
}




