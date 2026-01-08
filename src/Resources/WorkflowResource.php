<?php

namespace QQChen\Dify\Resources;

use QQChen\Dify\Client\DifyClient;
use InvalidArgumentException;

class WorkflowResource
{
    /** @var DifyClient */
    protected $client;

    public function __construct(DifyClient $client)
    {
        $this->client = $client;
    }

    /**
     * 1. 执行 workflow
     * POST /workflows/run
     *
     * @param array $inputs (必填) 输入变量
     * @param string $user (必填) 用户标识
     * @param string $responseMode (可选) streaming 或 blocking
     * @param array $files (可选) 文件列表
     * @return array|\Psr\Http\Message\ResponseInterface
     */
    public function run(array $inputs, $user, $responseMode = 'streaming', array $files = [])
    {
        if (empty($user)) {
            throw new InvalidArgumentException("User identifier cannot be empty.");
        }
        if (!in_array($responseMode, ['streaming', 'blocking'])) {
            throw new InvalidArgumentException("Response mode must be 'streaming' or 'blocking'.");
        }

        $data = [
            'inputs' => (object)$inputs,
            'response_mode' => $responseMode,
            'user' => $user,
        ];

        if (!empty($files)) {
            $data['files'] = $files;
        }

        // 根据模式选择发送方式
        if ($responseMode === 'streaming') {
            return $this->client->sendStream('POST', 'workflows/run', $data);
        }

        return $this->client->send('POST', 'workflows/run', $data);
    }

    /**
     * 2. 获取 workflow 执行情况
     * GET /workflows/run/{workflow_run_id}
     */
    public function getRun($workflowRunId)
    {
        return $this->client->send('GET', "workflows/run/{$workflowRunId}");
    }

    /**
     * 4. 获取 workflow 日志
     * GET /workflows/logs
     */
    public function getLogs(array $params = [])
    {
        // params: keyword, status, page, limit
        return $this->client->send('GET', 'workflows/logs', $params);
    }

    /**
     * 5. 上传文件 (Workflow)
     * POST /files/upload
     */
    public function uploadFile($fileSource, $user)
    {
        return $this->client->uploadGeneric('files/upload', $fileSource, ['user' => $user]);
    }

    /**
     * 6. 获取应用基本信息 (Workflow)
     * GET /info
     */
    public function getInfo()
    {
        // Workflow 的 info 接口不需要 user 参数
        return $this->client->send('GET', 'info');
    }

    /**
     * 8. 获取应用 WebApp 设置 (Workflow)
     * GET /site
     */
    public function getSiteConfig()
    {
        return $this->client->send('GET', 'site');
    }
}