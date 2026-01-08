<?php

namespace QQChen\Dify\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \QQChen\Dify\DifyService tenant($identifier) 切换到指定租户上下文
 * @method static \QQChen\Dify\DifyService withKey($apiKeys) 手动指定当前操作的 API Key
 * @method static \QQChen\Dify\Resources\DatasetResource dataset() 获取知识库资源
 * @method static \QQChen\Dify\Resources\DocumentResource document() 获取文档资源
 * @method static \QQChen\Dify\Resources\SegmentResource segment() 获取文档分段资源
 * @method static \QQChen\Dify\Resources\MetadataResource metadata() 获取元数据资源
 * @method static \QQChen\Dify\Resources\TagResource tag() 获取标签资源
 * @method static \QQChen\Dify\Resources\ModelsResource model() 获取模型资源
 * @method static \QQChen\Dify\Resources\ChatflowResource chatflow(string $botName = 'default') 获取对话流资源
 * @method static \QQChen\Dify\Resources\WorkflowResource workflow(string $botName = 'default') 获取工作流资源
 * @method static \QQChen\Dify\Client\DifyClient getClient() 获取底层 HTTP 客户端
 * @see \QQChen\Dify\DifyService
 */
class Dify extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'dify';
    }
}