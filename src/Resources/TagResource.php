<?php

namespace QQChen\Dify\Resources;

use QQChen\Dify\Client\DifyClient;
use InvalidArgumentException;

class TagResource
{
    /** @var DifyClient */
    protected $client;

    public function __construct(DifyClient $client)
    {
        $this->client = $client;
    }

    /**
     * 1. 获取知识库类型标签列表
     * GET /datasets/tags
     */
    public function list()
    {
        return $this->client->send('GET', 'datasets/tags');
    }

    /**
     * 2. 创建新的知识库类型标签
     * POST /datasets/tags
     *
     * @param string $name 标签名称
     * @return array
     */
    public function create($name)
    {
        if (empty($name)) {
            throw new InvalidArgumentException("Tag name cannot be empty.");
        }

        return $this->client->send('POST', 'datasets/tags', [
            'name' => $name
        ]);
    }

    /**
     * 3. 删除知识库类型标签
     * DELETE /datasets/tags (注意：这里是通过 Body 传 tag_id，非常规 DELETE)
     *
     * @param string $tagId 标签ID
     * @return array
     */
    public function delete($tagId)
    {
        return $this->client->send('DELETE', 'datasets/tags', [
            'tag_id' => $tagId
        ]);
    }

    /**
     * 4. 修改知识库类型标签名称
     * PATCH /datasets/tags
     *
     * @param string $tagId 标签ID
     * @param string $name 新名称
     * @return array
     */
    public function update($tagId, $name)
    {
        if (empty($name)) {
            throw new InvalidArgumentException("Tag name cannot be empty.");
        }

        return $this->client->send('PATCH', 'datasets/tags', [
            'tag_id' => $tagId,
            'name'   => $name
        ]);
    }

    /**
     * 5. 将数据集绑定到知识库类型标签
     * POST /datasets/tags/binding
     *
     * @param string $datasetId 知识库ID (target_id)
     * @param array $tagIds 标签ID列表
     * @return array
     */
    public function bind($datasetId, array $tagIds)
    {
        if (empty($tagIds)) {
            throw new InvalidArgumentException("Tag IDs cannot be empty.");
        }

        return $this->client->send('POST', 'datasets/tags/binding', [
            'target_id' => $datasetId,
            'tag_ids'   => $tagIds
        ]);
    }

    /**
     * 6. 解绑数据集和知识库类型标签
     * POST /datasets/tags/unbinding
     *
     * @param string $datasetId 知识库ID (target_id)
     * @param string $tagId 标签ID
     * @return array
     */
    public function unbind($datasetId, $tagId)
    {
        return $this->client->send('POST', 'datasets/tags/unbinding', [
            'target_id' => $datasetId,
            'tag_id'    => $tagId
        ]);
    }

    /**
     * 7. 查询绑定到数据集的标签
     * GET /datasets/{dataset_id}/tags
     *
     * @param string $datasetId
     * @return array
     */
    public function listByDataset($datasetId)
    {
        return $this->client->send('GET', "datasets/{$datasetId}/tags");
    }
}