<?php

namespace QQChen\Dify\Resources;

use QQChen\Dify\Client\DifyClient;

class DocumentResource
{
    /** @var DifyClient */
    protected $client;

    public function __construct(DifyClient $client)
    {
        $this->client = $client;
    }

    /**
     * 上传文件创建文档
     * * @param string $datasetId 知识库 ID
     * @param string $fileSource 本地文件路径 或 网络 URL
     * @param array $config 配置 (包含 mode, process_rule, file_name 等)
     */
    public function createByFile($datasetId, $fileSource, array $config = [])
    {
        // 1. 提取自定义文件名
        $customFileName = isset($config['file_name']) ? $config['file_name'] : null;

        // 2. 构造 Metadata
        $metadata = $this->buildMetadata($config);

        // 3. 调用上传
        return $this->client->upload(
            "datasets/{$datasetId}/document/create-by-file",
            $fileSource,
            $metadata,
            $customFileName
        );
    }

    /**
     * 上传文件更新文档
     * 文档: https://docs.dify.ai/v/zh-hans/guides/knowledge-base/update-document-via-api
     *
     * @param string $datasetId 知识库 ID
     * @param string $documentId 文档 ID
     * @param string $fileSource 本地文件路径 或 网络 URL
     * @param array $config 配置 (包含 process_rule, file_name 等)
     */
    public function updateByFile($datasetId, $documentId, $fileSource, array $config = [])
    {
        // 1. 提取自定义文件名
        $customFileName = isset($config['file_name']) ? $config['file_name'] : null;

        // 2. 构造 Metadata (逻辑与创建相同)
        $metadata = $this->buildMetadata($config);

        // 3. 调用上传 (注意 URL 差异: documents 复数, 结尾是 update-by-file)
        return $this->client->upload(
            "datasets/{$datasetId}/documents/{$documentId}/update-by-file",
            $fileSource,
            $metadata,
            $customFileName
        );
    }


    /**
     * 获取文档详情
     * 接口: GET datasets/{dataset_id}/documents/{document_id}
     *
     * @param string $datasetId 知识库 ID
     * @param string $documentId 文档 ID
     * @return array
     */
    public function get($datasetId, $documentId)
    {
        return $this->client->send('GET', "datasets/{$datasetId}/documents/{$documentId}");
    }


    /**
     * 获取文档嵌入状态（进度）
     * 文档: https://docs.dify.ai/v/zh-hans/guides/knowledge-base/get-document-embedding-status
     *
     * @param string $datasetId 知识库 ID
     * @param string $batchId 批次 ID (上传文件接口返回的 batch 字段)
     * @return array
     */
    public function getIndexingStatus($datasetId, $batchId)
    {
        return $this->client->send('GET', "datasets/{$datasetId}/documents/{$batchId}/indexing-status");
    }


    /**
     * 批量更新文档状态
     * 接口: POST datasets/{dataset_id}/documents/status/{action}
     *
     * @param string $datasetId 知识库 ID
     * @param string $action 动作: enable, disable, archive,un_archive
     * @param array $documentIds 文档 ID 列表
     * @return array
     */
    public function updateStatus($datasetId, $action, array $documentIds)
    {
        // Dify 接口需要的 body 格式为 { "document_ids": ["id1", "id2"] }
        return $this->client->send('PATCH', "datasets/{$datasetId}/documents/status/{$action}", ['document_ids'=> $documentIds]);
    }


    /**
     * 辅助方法：构建 metadata，避免代码重复
     */
    protected function buildMetadata(array $config)
    {
        // 构造处理规则 (Process Rule)
        $processRule = [
            'mode' => isset($config['mode']) ? $config['mode'] : 'automatic',
            'rules' => [
                'pre_processing_rules' => [
                    ['id' => 'remove_extra_spaces', 'enabled' => true],
                    ['id' => 'remove_urls_emails', 'enabled' => false],
                ],
                'segmentation' => [
                    'separator' => "\n\n",
                    'max_tokens' => 4000,
                    'chunk_overlap' => 0
                ]
            ]
        ];

        // 针对父子分段模式 (Hierarchical)
        if (isset($config['mode']) && $config['mode'] === 'hierarchical') {
            $userRules = isset($config['process_rule']['rules']) ? $config['process_rule']['rules'] : [];

            $processRule['rules']['parent_mode'] = isset($userRules['parent_mode']) ? $userRules['parent_mode'] : 'paragraph';

            // 合并 segmentation 配置
            if (isset($userRules['segmentation'])) {
                $processRule['rules']['segmentation'] = array_merge(
                    $processRule['rules']['segmentation'],
                    $userRules['segmentation']
                );
            }

            // 合并 subchunk_segmentation
            if (isset($userRules['subchunk_segmentation'])) {
                $processRule['rules']['subchunk_segmentation'] = $userRules['subchunk_segmentation'];
            } else {
                $processRule['rules']['subchunk_segmentation'] = [
                    'separator' => "\n",
                    'max_tokens' => 2000,
                    'chunk_overlap' => 0
                ];
            }
        }

        return [
            'indexing_technique' => isset($config['indexing_technique']) ? $config['indexing_technique'] : 'high_quality',
            'process_rule'       => $processRule,
            "doc_form"           => isset($config['doc_form']) ? $config['doc_form'] : 'text_model',
            "doc_language"       => isset($config['doc_language']) ? $config['doc_language'] : 'Chinese',
        ];
    }

    /**
     * 获取文档列表
     */
    public function list($datasetId, $page = 1, $limit = 20, $keyword = '')
    {
        return $this->client->send('GET', "datasets/{$datasetId}/documents", [
            'page' => $page,
            'limit' => $limit,
            'keyword' => $keyword
        ]);
    }

    /**
     * 删除文档
     */
    public function delete($datasetId, $documentId)
    {
        return $this->client->send('DELETE', "datasets/{$datasetId}/documents/{$documentId}");
    }
}