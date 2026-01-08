<?php

namespace QQChen\Dify;

use QQChen\Dify\Client\DifyClient;
use QQChen\Dify\Resources\DatasetResource;
use QQChen\Dify\Resources\DocumentResource;
use QQChen\Dify\Resources\MetadataResource;
use QQChen\Dify\Resources\TagResource;
use QQChen\Dify\Resources\SegmentResource;
use QQChen\Dify\Resources\ModelsResource;
use QQChen\Dify\Resources\ChatflowResource;
use QQChen\Dify\Resources\WorkflowResource;
use RuntimeException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cache;

class DifyService
{
    /** @var DifyClient 默认客户端 */
    protected $client;

    /** @var array|null 当前租户的上下文配置 */
    protected $contextConfig = null;

    // 资源缓存
    protected $dataset;
    protected $document;
    protected $segment;
    protected $metadata;
    protected $tag;
    protected $models;

    // 资源缓存池 (针对不同 bot)
    protected $chatflows = [];
    protected $workflows = [];

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

        // 1. Dataset Key
        if (isset($mapping['dataset_api_key'])) {
            $config['dataset_api_key'] = $record->{$mapping['dataset_api_key']} ?? null;
        }

        // 2. Chatflow Keys (JSON or Array)
        if (isset($mapping['chatflow_api_key'])) {
            $config['chatflow_api_key'] = $this->parseKeyMap($record, $mapping['chatflow_api_key']);
        }

        // 3. Workflow Keys (JSON or Array)
        if (isset($mapping['workflow_api_key'])) {
            $config['workflow_api_key'] = $this->parseKeyMap($record, $mapping['workflow_api_key']);
        }

        // 4. Base URL
        if (!empty($mapping['base_url']) && !empty($record->{$mapping['base_url']})) {
            $config['base_url'] = $record->{$mapping['base_url']};
        }

        if ($cacheTtl > 0) Cache::put($cacheKey, $config, $cacheTtl);

        return $config;
    }

    /**
     * 辅助方法：解析 Key 映射 (JSON/Array/String)
     */
    protected function parseKeyMap($record, $mapConfig)
    {
        if (is_array($mapConfig)) {
            // 字段映射数组 ['default' => 'col1']
            $keys = [];
            foreach ($mapConfig as $botName => $dbColumn) {
                $val = $record->{$dbColumn} ?? null;
                if ($val) $keys[$botName] = $val;
            }
            return $keys;
        } else {
            // 单个字段
            $val = $record->{$mapConfig} ?? null;

            if (is_string($val) && (strpos($val, '{') === 0 || strpos($val, '[') === 0)) {
                $decoded = json_decode($val, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    return $decoded; // 数据库存的是 JSON
                }
            } elseif (is_array($val)) {
                return $val; // Eloquent Casts
            }

            // 普通字符串，视为默认 key
            return ['default' => $val];
        }
    }

    /**
     * 手动设置上下文密钥
     */
    public function withKey($apiKeys)
    {
        $instance = clone $this;
        $instance->flushResources();

        $config = [];

        if (is_array($apiKeys)) {
            $config['dataset_api_key'] = $apiKeys['dataset'] ?? null;

            // Chatflow keys
            $config['chatflow_api_key'] = is_array($apiKeys['chatflow'] ?? null)
                ? $apiKeys['chatflow']
                : ['default' => $apiKeys['chatflow'] ?? null];

            // Workflow keys
            $config['workflow_api_key'] = is_array($apiKeys['workflow'] ?? null)
                ? $apiKeys['workflow']
                : ['default' => $apiKeys['workflow'] ?? null];
        } else {
            // 字符串则全部设为同一个 key (不太常见)
            $config = [
                'dataset_api_key' => $apiKeys,
                'chatflow_api_key' => ['default' => $apiKeys],
                'workflow_api_key' => ['default' => $apiKeys],
            ];
        }

        $instance->contextConfig = $config;
        return $instance;
    }

    /**
     * 内部辅助：根据类型获取合适的客户端
     * 统一处理单租户和多租户的密钥获取逻辑
     */
    protected function getClientFor($type, $botName = 'default')
    {
        // 获取配置源：如果有上下文(多租户)则用上下文，否则读取 Laravel 全局配置(单租户)
        $config = $this->contextConfig ?? Config::get('dify');

        $apiKey = null;

        // 1. 尝试获取专用 Key (Dataset / Chatflow / Workflow)
        if ($type === 'dataset') {
            $apiKey = $config['dataset_api_key'] ?? null;
        } elseif ($type === 'chatflow') {
            $keys = $config['chatflow_api_key'] ?? [];
            if (is_array($keys)) {
                $apiKey = $keys[$botName] ?? ($keys['default'] ?? null);
            } else {
                $apiKey = $keys; // 兼容直接配置字符串的情况
            }
        } elseif ($type === 'workflow') {
            $keys = $config['workflow_api_key'] ?? [];
            if (is_array($keys)) {
                $apiKey = $keys[$botName] ?? ($keys['default'] ?? null);
            } else {
                $apiKey = $keys;
            }
        }

        // 2. 如果没找到专用 Key，回退到通用 api_key
        if (empty($apiKey) && !empty($config['api_key'])) {
            $apiKey = $config['api_key'];
        }

        // 3. 如果找到了有效的 Key，创建一个新的 Client
        // 这样即使在单租户模式下，也能正确使用 dataset_api_key 而不是空的默认 api_key
        if ($apiKey) {
            $baseUrl = $config['base_url'] ?? $this->client->getConfig('base_uri');
            $timeout = $config['timeout'] ?? $this->client->getConfig('timeout');

            return new DifyClient($apiKey, (string)$baseUrl, (int)($timeout ?? 60));
        }

        // 4. 如果实在找不到，返回默认 Client (可能报错，取决于 ServiceProvider 初始化时是否有 api_key)
        return $this->client;
    }

    protected function flushResources()
    {
        $this->dataset = null;
        $this->document = null;
        $this->segment = null;
        $this->metadata = null;
        $this->tag = null;
        $this->models = null;
        $this->chatflows = [];
        $this->workflows = [];
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

    public function segment()
    {
        if (!$this->segment) $this->segment = new SegmentResource($this->getClientFor('dataset'));
        return $this->segment;
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

    public function model()
    {
        if (!$this->models) {
            $this->models = new ModelsResource($this->getClientFor('dataset'));
        }
        return $this->models;
    }

    public function chatflow($botName = 'default')
    {
        if (!isset($this->chatflows[$botName])) {
            $this->chatflows[$botName] = new ChatflowResource($this->getClientFor('chatflow', $botName));
        }
        return $this->chatflows[$botName];
    }

    public function workflow($botName = 'default')
    {
        if (!isset($this->workflows[$botName])) {
            $this->workflows[$botName] = new WorkflowResource($this->getClientFor('workflow', $botName));
        }
        return $this->workflows[$botName];
    }

    public function getClient()
    {
        return $this->client;
    }
}