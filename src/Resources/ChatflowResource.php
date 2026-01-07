<?php

namespace QQChen\Dify\Resources;

use QQChen\Dify\Client\DifyClient;
use InvalidArgumentException;

class ChatflowResource
{
    /** @var DifyClient */
    protected $client;

    public function __construct(DifyClient $client)
    {
        $this->client = $client;
    }

    // ==========================================
    // 1. 对话相关 (Messages & Conversations)
    // ==========================================


    /**
     * 1. 发送对话消息
     *
     * @param string $query (必填) 用户输入/提问内容
     * @param string $user (必填) 用户标识
     * @param array $inputs (可选) 允许传入 App 定义的各变量值
     * @param string $responseMode (可选) streaming 或 blocking
     * @param string|null $conversationId (可选) 会话 ID
     * @param array $files (可选) 上传的文件列表
     * @param bool $autoGenerateName (可选) 是否自动生成标题
     * * @return array|\Psr\Http\Message\ResponseInterface
     * blocking模式返回数组，streaming模式返回 Guzzle Response 对象
     */
    public function sendMessage(
        $query,
        $user,
        array $inputs = [],
        $responseMode = 'streaming',
        $conversationId = null,
        array $files = [],
        $autoGenerateName = true
    ) {
        //基础校验
        if (empty($query)) {
            throw new InvalidArgumentException("Query cannot be empty.");
        }
        if (empty($user)) {
            throw new InvalidArgumentException("User identifier cannot be empty.");
        }
        if (!in_array($responseMode, ['streaming', 'blocking'])) {
            throw new InvalidArgumentException("Response mode must be 'streaming' or 'blocking'.");
        }

        //构造请求体
        $data = [
            'query' => $query,
            'user' => $user,
            // 关键修正: Dify 要求 inputs 为 Object，PHP 空数组 [] json_encode 后是 []，
            // 强制转 (object) 确保 json_encode 后是 {}
            'inputs' => (object)$inputs,
            'response_mode' => $responseMode,
            'auto_generate_name' => $autoGenerateName
        ];

        //处理选填参数
        if (!empty($conversationId)) {
            $data['conversation_id'] = $conversationId;
        }

        if (!empty($files)) {
            // 这里可以加一层简单的结构校验，防止 files 格式传错
            foreach ($files as $file) {
                if (!isset($file['type']) || !isset($file['transfer_method'])) {
                    throw new InvalidArgumentException("Each file item must contain 'type' and 'transfer_method'.");
                }
            }
            $data['files'] = $files;
        }

        //根据模式选择不同的发送方法
        if ($responseMode === 'streaming') {
            return $this->client->sendStream('POST', 'chat-messages', $data);
        }

        return $this->client->send('POST', 'chat-messages', $data);
    }

    /**
     * 2. 停止响应
     * POST /chat-messages/{task_id}/stop
     */
    public function stopMessage($taskId, $user)
    {
        return $this->client->send('POST', "chat-messages/{$taskId}/stop", ['user' => $user]);
    }

    /**
     * 3. 获取下一轮建议问题列表
     * GET /messages/{message_id}/suggested
     */
    public function getSuggestedMessages($messageId, $user)
    {
        return $this->client->send('GET', "messages/{$messageId}/suggested", ['user' => $user]);
    }

    /**
     * 6. 消息反馈（点赞）
     * POST /messages/{message_id}/feedbacks
     * rating: like / dislike
     */
    public function feedbackMessage($messageId, $user, $rating, $content = null)
    {
        $data = [
            'user' => $user,
            'rating' => $rating
        ];
        if ($content) $data['content'] = $content;

        return $this->client->send('POST', "messages/{$messageId}/feedbacks", $data);
    }

    /**
     * 7. 获取APP的消息点赞和反馈
     * GET /app/feedbacks
     */
    public function getAppFeedbacks($user, $page = 1, $limit = 20)
    {
        return $this->client->send('GET', 'app/feedbacks', [
            'user' => $user,
            'page' => $page,
            'limit' => $limit
        ]);
    }

    /**
     * 8. 获取会话历史消息
     * GET /messages
     */
    public function getHistory($conversationId, $user, $firstId = null, $limit = 20)
    {
        $params = [
            'conversation_id' => $conversationId,
            'user' => $user,
            'limit' => $limit
        ];
        if ($firstId) $params['first_id'] = $firstId;

        return $this->client->send('GET', 'messages', $params);
    }

    /**
     * 9. 获取会话列表
     * GET /conversations
     */
    public function getConversations($user, $lastId = null, $limit = 20, $sortBy = '-updated_at')
    {
        $params = [
            'user' => $user,
            'limit' => $limit,
            'sort_by' => $sortBy
        ];
        if ($lastId) $params['last_id'] = $lastId;

        return $this->client->send('GET', 'conversations', $params);
    }

    /**
     * 10. 删除会话
     * DELETE /conversations/{conversation_id}
     */
    public function deleteConversation($conversationId, $user)
    {
        return $this->client->send('DELETE', "conversations/{$conversationId}", ['user' => $user]);
    }

    /**
     * 11. 会话重命名
     * POST /conversations/{conversation_id}/name
     */
    public function renameConversation($conversationId, $name, $user, $autoGenerate = false)
    {
        return $this->client->send('POST', "conversations/{$conversationId}/name", [
            'name' => $name,
            'user' => $user,
            'auto_generate' => $autoGenerate
        ]);
    }

    /**
     * 12. 获取对话变量
     * GET /conversations/{conversation_id}/variables
     */
    public function getConversationVariables($conversationId, $user)
    {
        return $this->client->send('GET', "conversations/{$conversationId}/variables", ['user' => $user]);
    }

    // ==========================================
    // 2. 文件与音频 (Files & Audio)
    // ==========================================

    /**
     * 4. 上传文件 (用于 Chat)
     * POST /files/upload
     */
    public function uploadFile($fileSource, $user)
    {
        // 使用新加的通用上传方法
        return $this->client->uploadGeneric('files/upload', $fileSource, ['user' => $user]);
    }

    /**
     * 5. 文件预览
     * GET /files/{file_id}/preview
     * 返回二进制数据
     */
    public function previewFile($fileId, $user)
    {
        // 返回原始响应数组 ['content' => ..., 'headers' => ...]
        return $this->client->requestRaw('GET', "files/{$fileId}/preview", ['user' => $user]);
    }

    /**
     * 13. 语音转文字
     * POST /audio-to-text
     */
    public function audioToText($fileSource, $user)
    {
        return $this->client->uploadGeneric('audio-to-text', $fileSource, ['user' => $user]);
    }

    /**
     * 14. 文字转语音
     * POST /text-to-audio
     * 返回二进制音频流
     */
    public function textToAudio($messageId, $text, $user)
    {
        return $this->client->requestRaw('POST', 'text-to-audio', [
            'message_id' => $messageId,
            'text' => $text,
            'user' => $user
        ]);
    }

    // ==========================================
    // 3. 应用信息 (App Info)
    // ==========================================

    /**
     * 15. 获取应用基本信息
     * GET /info
     */
    public function getInfo($user)
    {
        return $this->client->send('GET', 'info', ['user' => $user]);
    }

    /**
     * 16. 获取应用参数
     * GET /parameters
     */
    public function getParameters($user)
    {
        return $this->client->send('GET', 'parameters', ['user' => $user]);
    }

    /**
     * 17. 获取应用Meta信息
     * GET /meta
     */
    public function getMeta($user)
    {
        return $this->client->send('GET', 'meta', ['user' => $user]);
    }

    /**
     * 18. 获取应用 WebApp 设置
     * GET /site
     */
    public function getSiteConfig()
    {
        return $this->client->send('GET', 'site');
    }

    // ==========================================
    // 4. 标注管理 (Annotations)
    // ==========================================

    /**
     * 19. 获取标注列表
     * GET /apps/annotations
     */
    public function getAnnotations($page = 1, $limit = 20)
    {
        return $this->client->send('GET', 'apps/annotations', ['page' => $page, 'limit' => $limit]);
    }

    /**
     * 20. 创建标注
     * POST /apps/annotations
     */
    public function createAnnotation($question, $answer)
    {
        return $this->client->send('POST', 'apps/annotations', [
            'question' => $question,
            'answer' => $answer
        ]);
    }

    /**
     * 21. 更新标注
     * PUT /apps/annotations/{annotation_id}
     */
    public function updateAnnotation($annotationId, $question, $answer)
    {
        return $this->client->send('PUT', "apps/annotations/{$annotationId}", [
            'question' => $question,
            'answer' => $answer
        ]);
    }

    /**
     * 22. 删除标注
     * DELETE /apps/annotations/{annotation_id}
     */
    public function deleteAnnotation($annotationId)
    {
        return $this->client->send('DELETE', "apps/annotations/{$annotationId}");
    }

    /**
     * 23. 标注回复初始设置
     * POST /apps/annotation-reply/{action}
     */
    public function setAnnotationReply($action, array $config = [])
    {
        return $this->client->send('POST', "apps/annotation-reply/{$action}", $config);
    }

    /**
     * 24. 查询标注回复初始设置任务状态
     * GET /apps/annotation-reply/{action}/status/{job_id}
     */
    public function getAnnotationReplyStatus($action, $jobId)
    {
        return $this->client->send('GET', "apps/annotation-reply/{$action}/status/{$jobId}");
    }
}