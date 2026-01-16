<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Dify API Configuration
    |--------------------------------------------------------------------------
    |
    | api_key: 你的 Dify 应用 API Key (Knowledge API Key 或 Service API Key)
    | base_url: Dify API 的基础地址，私有部署请填写自己的域名
    | timeout: 请求超时时间
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Global Configuration (Single Tenant Default)
    |--------------------------------------------------------------------------
    |
    | 如果不使用多租户模式，或者租户未配置特定密钥，将回退使用此处的配置。
    |
    */
    'api_key' => env('DIFY_API_KEY', ''), // 通用默认 Key

    // 如果你的知识库和工作流使用不同的默认 Key，可在此分开配置
    'dataset_api_key' => env('DIFY_DATASET_API_KEY'),
    'chatflow_api_key' => env('DIFY_CHATFLOW_API_KEY'),
    'workflow_api_key' => env('DIFY_WORKFLOW_API_KEY'),

    'base_url' => env('DIFY_BASE_URL', 'https://api.dify.ai/v1'),

    'timeout' => 60,

    /*
    |--------------------------------------------------------------------------
    | Multi-Tenant Configuration
    |--------------------------------------------------------------------------
    |
    | 在此处配置如何根据 tenant($id) 动态获取密钥。
    |
    */
    'multi_tenant' => [
        // 是否启用多租户模式
        'enabled' => env('DIFY_MULTI_TENANT', false),

        // 驱动模式: 'model' (数据库自动查询) 或 'config' (静态数组映射)
        'driver' => 'config',

        /*
         |--------------------------------------------------------------------------
         | Model Driver Settings (Database)
         |--------------------------------------------------------------------------
         | 适用于 SaaS 系统，根据 ID 自动查询数据库模型获取密钥。
         */
        'model' => [
            // 你的租户配置模型类名 (例如: \App\Models\StoreConfig::class)
            'class' => 'App\Models\StoreConfig',

            // 查询字段：用于匹配 Dify::tenant($id) 传入的 ID 的数据库列名
            'foreign_key' => 'store_id',

            // 字段映射：告诉 SDK 哪个数据库字段对应哪个 Key
            'mapping' => [
                'dataset_api_key'  => 'dify_dataset_key', // 知识库通常是通用的，映射到单一字段

                // Chatflow 应用密钥 (JSON)
                'chatflow_api_key' => 'dify_chatflow_keys',

                //Workflow 应用密钥 (JSON)
                'workflow_api_key' => 'dify_workflow_keys',

                'base_url'         => 'dify_base_url',    // (可选) 如果租户有独立部署地址
            ],

            // 缓存配置 (单位: 秒)，避免每次请求都查数据库，设为 0 关闭
            'cache_ttl' => 3600,
        ],

        /*
         |--------------------------------------------------------------------------
         | Config Driver Settings (Static Array)
         |--------------------------------------------------------------------------
         | 适用于租户数量较少且固定的场景。
         */
        'config' => [
            // 'store_id_1001' => [ ... ]
            '1001' => [
                'dataset_api_key' => 'dataset-key-001',
                // 支持多工作流/聊天流配置
                'chatflow_api_key' => [
                    'default' => 'chat-key-001',
                    'writer'  => 'writer-key-001',
                ],
                //支持多 Workflow 应用配置
                'workflow_api_key' => [
                    'default' => 'workflow-key-001',
                    'translator' => 'translator-key-001',
                ],
                // === 独立部署地址 ===
                // 如果该租户使用独立的 Dify 实例，可在此配置，SDK 会自动识别并覆盖全局 base_url
                'base_url' => 'https://dify.private-deployment.com/v1',
            ],
        ],
    ],
];