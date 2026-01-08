<?php

namespace QQChen\Dify\Resources;

use QQChen\Dify\Client\DifyClient;

class ModelsResource
{
    /** @var DifyClient */
    protected $client;

    public function __construct(DifyClient $client)
    {
        $this->client = $client;
    }

    /**
     * 1. 获取可用的嵌入模型
     * GET /workspaces/current/models/model-types/text-embedding
     *
     * @return array
     */
    public function getEmbeddingModels()
    {
        return $this->client->send('GET', 'workspaces/current/models/model-types/text-embedding');
    }
}