<?php

namespace QQChen\Dify\Resources;

use QQChen\Dify\Client\DifyClient;
use InvalidArgumentException;

class MetadataResource
{
    /** @var DifyClient */
    protected $client;

    public function __construct(DifyClient $client)
    {
        $this->client = $client;
    }

    /**
     * 5. 获取数据集的元数据列表
     */
    public function list($datasetId)
    {
        return $this->client->send('GET', "datasets/{$datasetId}/metadata");
    }

    /**
     * 1. 新增知识库元数据字段
     * * @param string $datasetId
     * @param string $name 字段名称
     * @param string $fieldType 字段类型 (text, paragraph, tag 等)
     * @return array
     */
    public function createField($datasetId, $name, $fieldType)
    {
        // 校验字段类型
        if (!in_array($fieldType, ['string', 'number', 'time'])) {
            throw new InvalidArgumentException("Field type must be one of: string, number, time.");
        }

        // 构造请求体，屏蔽底层 API 字段名的细节
        // Dify API 此接口不接收 description 参数
        $data = [
            'name' => $name,
            'type' => $fieldType,
        ];

        return $this->client->send('POST', "datasets/{$datasetId}/metadata", $data);
    }


    /**
     * 修改知识库元数据字段
     * 实际上只会接收 name 修改，因此参数显式化
     * * @param string $datasetId
     * @param string $metadataId
     * @param string $name 新的字段名称
     */
    public function updateField($datasetId, $metadataId, $name)
    {
        if (empty($name)) {
            throw new InvalidArgumentException("Name cannot be empty.");
        }

        $data = ['name' => $name];

        return $this->client->send('PATCH', "datasets/{$datasetId}/metadata/{$metadataId}", $data);
    }

    /**
     * 2. 删除知识库元数据字段
     */
    public function deleteField($datasetId, $metadataId)
    {
        return $this->client->send('DELETE', "datasets/{$datasetId}/metadata/{$metadataId}");
    }

    /**
     * 3. 启用/禁用内置字段
     * * @param string $datasetId
     * @param string $action 操作类型: enable, disable
     */
    public function enableBuiltInField($datasetId, $action)
    {
        if (!in_array($action, ['enable', 'disable'])) {
            throw new InvalidArgumentException("Action must be 'enable' or 'disable'.");
        }
        return $this->client->send('POST', "datasets/{$datasetId}/metadata/built-in/{$action}");
    }

    /**
     * 4. 批量给文档赋元数据值
     * 增加了结构校验，降低调用方传错数据的风险
     */
    public function updateDocumentsMetadata($datasetId, array $operationData)
    {
        if (empty($operationData)) {
            throw new InvalidArgumentException("Operation data cannot be empty.");
        }

        // 简单的结构校验：检查第一项是否包含必要的 key
        // 虽然不能保证 100% 每一项都对，但能拦截大部分格式错误
        $firstItem = reset($operationData);
        if (!isset($firstItem['document_id']) || !isset($firstItem['metadata_list'])) {
            throw new InvalidArgumentException("Invalid data structure. Each item must contain 'document_id' and 'metadata_list'.");
        }

        return $this->client->send('POST', "datasets/{$datasetId}/documents/metadata", [
            'operation_data' => $operationData
        ]);
    }
}