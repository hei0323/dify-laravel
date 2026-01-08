<?php

namespace QQChen\Dify\Resources;

use QQChen\Dify\Client\DifyClient;
use InvalidArgumentException;

class SegmentResource
{
    /** @var DifyClient */
    protected $client;

    public function __construct(DifyClient $client)
    {
        $this->client = $client;
    }

    /**
     * 1. 从文档获取块 (列表)
     * GET /datasets/{dataset_id}/documents/{document_id}/segments
     *
     * @param string $datasetId
     * @param string $documentId
     * @param array $params (keyword, status, page, limit)
     * @return array
     */
    public function list($datasetId, $documentId, array $params = [])
    {
        return $this->client->send('GET', "datasets/{$datasetId}/documents/{$documentId}/segments", $params);
    }

    /**
     * 2. 向文档添加块
     * POST /datasets/{dataset_id}/documents/{document_id}/segments
     *
     * @param string $datasetId
     * @param string $documentId
     * @param array $segments 块列表，示例: [['content' => '...', 'answer' => '...', 'keywords' => []]]
     * @return array
     */
    public function create($datasetId, $documentId, array $segments)
    {
        if (empty($segments)) {
            throw new InvalidArgumentException("Segments list cannot be empty.");
        }

        // 简单的结构校验
        foreach ($segments as $segment) {
            if (!isset($segment['content'])) {
                throw new InvalidArgumentException("Each segment must contain 'content'.");
            }
        }

        return $this->client->send('POST', "datasets/{$datasetId}/documents/{$documentId}/segments", [
            'segments' => $segments
        ]);
    }

    /**
     * 3. 获取文档中的块详情
     * GET /datasets/{dataset_id}/documents/{document_id}/segments/{segment_id}
     */
    public function get($datasetId, $documentId, $segmentId)
    {
        return $this->client->send('GET', "datasets/{$datasetId}/documents/{$documentId}/segments/{$segmentId}");
    }

    /**
     * 4. 更新文档中的块
     * POST /datasets/{dataset_id}/documents/{document_id}/segments/{segment_id}
     *
     * @param string $datasetId
     * @param string $documentId
     * @param string $segmentId
     * @param array $data 包含 content(必填), answer, keywords, enabled, regenerate_child_chunks
     */
    public function update($datasetId, $documentId, $segmentId, array $data)
    {
        if (empty($data['content'])) {
            throw new InvalidArgumentException("Content is required for updating a segment.");
        }

        return $this->client->send('POST', "datasets/{$datasetId}/documents/{$documentId}/segments/{$segmentId}", [
            'segment' => $data
        ]);
    }

    /**
     * 5. 删除文档中的块
     * DELETE /datasets/{dataset_id}/documents/{document_id}/segments/{segment_id}
     */
    public function delete($datasetId, $documentId, $segmentId)
    {
        return $this->client->send('DELETE', "datasets/{$datasetId}/documents/{$documentId}/segments/{$segmentId}");
    }

    /**
     * 7. 创建子块 (分层模式)
     * POST .../child_chunks
     */
    public function createChildChunk($datasetId, $documentId, $segmentId, $content)
    {
        if (empty($content)) {
            throw new InvalidArgumentException("Content is required for child chunk.");
        }

        return $this->client->send('POST', "datasets/{$datasetId}/documents/{$documentId}/segments/{$segmentId}/child_chunks", [
            'content' => $content
        ]);
    }

    /**
     * 9. 更新子块 (分层模式)
     * PATCH .../child_chunks/{child_chunk_id}
     */
    public function updateChildChunk($datasetId, $documentId, $segmentId, $childChunkId, $content)
    {
        if (empty($content)) {
            throw new InvalidArgumentException("Content is required for updating child chunk.");
        }

        return $this->client->send('PATCH', "datasets/{$datasetId}/documents/{$documentId}/segments/{$segmentId}/child_chunks/{$childChunkId}", [
            'content' => $content
        ]);
    }
}