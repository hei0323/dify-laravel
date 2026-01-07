<?php

namespace QQChen\Dify;

use QQChen\Dify\Client\DifyClient;
use QQChen\Dify\Resources\DatasetResource;
use QQChen\Dify\Resources\DocumentResource;
use QQChen\Dify\Resources\MetadataResource;
use QQChen\Dify\Resources\TagResource;
use QQChen\Dify\Resources\ChatflowResource;
use RuntimeException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cache;

class DifyService
{
    /** @var DifyClient 默认客户端（单租户/全局配置） */
    protected $client;

    /** @var array|null 当前租户的上下文配置 */
    protected $contextConfig = null;

    // 资源缓存
    protected $dataset;
    protected $document;
    protected $metadata;
    protected $tag;

    // Chatflow 资源缓存池 (针对不同 bot)
    protected $chatflows = [];

    public function __construct(DifyClient $client)
    {
        $this->client = $client;
    }

    /**
     * 切换到指定租户上下文
     */
    public function tenant($identifier)
    {
        if (!Config::get('dify.multi_tenant.enabled')) {
            return $this;
        }


        $driver = Config::get('dify.multi_tenant.driver');
        $resolvedConfig = [];

        if ($driver === 'model') {
            $resolvedConfig = $this->resolveFromModel($identifier);
        } elseif ($driver === 'config') {
            $resolvedConfig = Config::get("dify.multi_tenant.config.{$identifier}", []);
        }

        $instance = clone $this;
        $instance->flushResources();

        if (!empty($resolvedConfig)) {
            $instance->contextConfig = $resolvedConfig;
        }

        return $instance;
    }

    /**
     * 内部方法：从数据库模型解析配置
     */
    protected function resolveFromModel($identifier)
    {
        $settings = Config::get('dify.multi_tenant.model');
        $modelClass = $settings['class'];
        $foreignKey = $settings['foreign_key'];
        $mapping = $settings['mapping'];
        $cacheTtl = $settings['cache_ttl'] ?? 0;

        if (!class_exists($modelClass)) {
            throw new RuntimeException("Dify Tenant Model class [{$modelClass}] not found.");
        }

        $cacheKey = "dify_tenant_config_{$identifier}";
        if ($cacheTtl > 0 && $cached = Cache::get($cacheKey)) {
            return $cached;
        }

        $record = $modelClass::where($foreignKey, $identifier)->first();
        if (!$record) return [];

        $config = [];

        // 1. 解析 Dataset Key (通常是单字段)
        if (isset($mapping['dataset_api_key'])) {
            $config['dataset_api_key'] = $record->{$mapping['dataset_api_key']} ?? null;
        }

        // 2. 解析 Chatflow Keys (可能是单字段，也可能是多字段，也可能是 JSON)
        if (isset($mapping['chatflow_api_key'])) {
            $mapConfig = $mapping['chatflow_api_key'];

            if (is_array($mapConfig)) {
                // 情况 A: 配置文件里直接写了字段映射数组 ['default' => 'col_1', 'bot2' => 'col_2']
                $keys = [];
                foreach ($mapConfig as $botName => $dbColumn) {
                    $val = $record->{$dbColumn} ?? null;
                    if ($val) $keys[$botName] = $val;
                }
                $config['chatflow_api_key'] = $keys;
            } else {
                // 情况 B: 映射的是单个数据库字段名
                $val = $record->{$mapConfig} ?? null;

                // 尝试判断是否为 JSON (存储了多个 key)
                if (is_string($val) && (strpos($val, '{') === 0 || strpos($val, '[') === 0)) {
                    $decoded = json_decode($val, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        // 数据库存的是 JSON: {"default": "key1", "marketing": "key2"}
                        $config['chatflow_api_key'] = $decoded;
                    } else {
                        // 只是普通字符串，视为默认 key
                        $config['chatflow_api_key'] = ['default' => $val];
                    }
                } elseif (is_array($val)) {
                    // 已经是数组 (Eloquent Casts)
                    $config['chatflow_api_key'] = $val;
                } else {
                    // 普通字符串
                    $config['chatflow_api_key'] = ['default' => $val];
                }
            }
        }

        // 3. 解析 Base URL
        if (!empty($mapping['base_url']) && !empty($record->{$mapping['base_url']})) {
            $config['base_url'] = $record->{$mapping['base_url']};
        }

        if ($cacheTtl > 0) Cache::put($cacheKey, $config, $cacheTtl);

        return $config;
    }

    /**
     * 手动设置上下文密钥
     * @param string|array $apiKeys
     */
    public function withKey($apiKeys)
    {
        $instance = clone $this;
        $instance->flushResources();

        if (is_array($apiKeys)) {
            $instance->contextConfig = [
                'dataset_api_key' => $apiKeys['dataset'] ?? null,
                // 支持直接传入数组给 chatflow
                'chatflow_api_key' => is_array($apiKeys['chatflow'] ?? null)
                    ? $apiKeys['chatflow']
                    : ['default' => $apiKeys['chatflow'] ?? null],
            ];
        } else {
            $instance->contextConfig = [
                'dataset_api_key' => $apiKeys,
                'chatflow_api_key' => ['default' => $apiKeys],
            ];
        }

        return $instance;
    }

    /**
     * 内部辅助：根据类型获取合适的客户端
     * @param string $type (dataset | chatflow)
     * @param string $botName (仅用于 chatflow)
     */
    protected function getClientFor($type, $botName = 'default')
    {
        if (!$this->contextConfig) {
            return $this->client;
        }

        $apiKey = null;

        if ($type === 'dataset') {
            $apiKey = $this->contextConfig['dataset_api_key'] ?? null;
        } elseif ($type === 'chatflow') {
            $keys = $this->contextConfig['chatflow_api_key'] ?? [];
            // 尝试获取指定 bot 的 key，如果没有，尝试获取 default
            $apiKey = $keys[$botName] ?? ($keys['default'] ?? null);

            // 如果还是没有，且 $keys 本身就是个字符串 (兼容旧逻辑)
            if (!$apiKey && is_string($keys)) {
                $apiKey = $keys;
            }
        }

        // 回退到通用 Key
        if (!$apiKey && !empty($this->contextConfig['api_key'])) {
            $apiKey = $this->contextConfig['api_key'];
        }

        if (!$apiKey) {
            return $this->client;
        }

        $baseUrl = $this->contextConfig['base_url'] ?? $this->client->getConfig('base_uri');
        $timeout = $this->contextConfig['timeout'] ?? $this->client->getConfig('timeout');

        return new DifyClient($apiKey, (string)$baseUrl, (int)($timeout ?? 60));
    }

    protected function flushResources()
    {
        $this->dataset = null;
        $this->document = null;
        $this->metadata = null;
        $this->tag = null;
        $this->chatflows = []; // 清空数组
    }

    // ==========================================
    // 资源访问器
    // ==========================================

    public function dataset()
    {
        if (!$this->dataset) $this->dataset = new DatasetResource($this->getClientFor('dataset'));
        return $this->dataset;
    }

    public function document()
    {
        if (!$this->document) $this->document = new DocumentResource($this->getClientFor('dataset'));
        return $this->document;
    }

    public function metadata()
    {
        if (!$this->metadata) $this->metadata = new MetadataResource($this->getClientFor('dataset'));
        return $this->metadata;
    }

    public function tag()
    {
        if (!$this->tag) $this->tag = new TagResource($this->getClientFor('dataset'));
        return $this->tag;
    }

    /**
     * 获取工作流/对话资源
     * @param string $botName 工作流标识符 (默认为 'default')
     * @return ChatflowResource
     */
    public function chatflow($botName = 'default')
    {
        if (!isset($this->chatflows[$botName])) {
            $this->chatflows[$botName] = new ChatflowResource($this->getClientFor('chatflow', $botName));
        }
        return $this->chatflows[$botName];
    }

    public function getClient()
    {
        return $this->client;
    }
}