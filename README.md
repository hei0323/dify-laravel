
# Dify SDK for Laravel

A powerful, feature-rich Laravel SDK for Dify.ai, supporting Knowledge Base management, Chatflow (Workflows), Multi-tenancy, and Streaming responses.

这是一个功能强大的 Dify.ai Laravel SDK，支持知识库管理、工作流（Chatflow）、多租户架构以及流式对话响应。

## 🌟 Features (特性)

* **Multi-Tenancy Support (多租户支持):** Easily manage different API keys for different tenants (Stores/Users) via configuration or database.

* **Separated Keys (密钥分离):** Support distinct keys for Knowledge Base (Dataset) and Chatflow (Workflow) within the same tenant.

* **Multi-App Support (多应用支持):** Configure multiple Chatflow Apps (e.g., Marketing Bot, Support Bot) for a single tenant.

* **Knowledge Base Management (知识库管理):** Create datasets, upload documents (local/URL), manage metadata, and tags.

* **Chatflow & Streaming (工作流与流式对话):** Send messages, handle streaming responses (SSE), upload files/audio, and text-to-speech.

* **Metadata & Tags (元数据与标签):** Comprehensive management for dataset metadata and tagging system.

## 📦 Installation (安装)

Install the package via Composer:

``` composer require qqchen/dify-laravel ```


Publish the configuration file:

` php artisan vendor:publish --tag=dify-config`


## ⚙️ Configuration (配置)

### 1. Basic Configuration (单租户模式)

If you are building a single-tenant application, simply add your keys to your .env file:

```
DIFY_API_KEY=your-common-api-key
# OR separate keys if needed
DIFY_DATASET_API_KEY=your-dataset-key
DIFY_CHATFLOW_API_KEY=your-chatflow-key

DIFY_BASE_URL=[https://api.dify.ai/v1](https://api.dify.ai/v1)
```

### 2. Multi-Tenant Configuration (多租户模式)

This SDK shines in SaaS applications. You can configure it to fetch API keys dynamically based on a tenant ID (e.g., `store_id`).

Edit `config/dify.php` to enable multi-tenancy and choose a driver (`model` or `config`).

### Option A: Database Model Driver (Recommended for SaaS)

Map your database columns to Dify keys.
```
// config/dify.php
'multi_tenant' => [
'enabled' => true,
'driver' => 'model',
'model' => [
'class' => \App\Models\StoreConfig::class, // Your Tenant Model
'foreign_key' => 'store_id',               // The column to match tenant($id)
'mapping' => [
'dataset_api_key'  => 'dify_dataset_key', // DB column for Dataset Key

            // Map multiple bots to a JSON column (e.g. {"default": "key1", "marketing": "key2"})
            'chatflow_api_key' => 'dify_chatflow_keys', // DB column (JSON)
            
            'base_url'         => 'dify_base_url',    // DB column for private deployment URL
        ],
    ],
],
```

### Option B: Config Array Driver

Define tenants directly in the config file.
```
// config/dify.php
'multi_tenant' => [
    'enabled' => true,
    'driver' => 'config',
    'config' => [
        '1001' => [
            'dataset_api_key' => 'kb-key-1001',
            'chatflow_api_key' => [
                'default' => 'chat-key-1001',
                'marketing' => 'marketing-key-1001'
                ],
            'base_url' => 'https://private-dify.com/v1',
    ],
],
```

## 🚀 Usage (使用方法)

### Initialization (初始化)
````
use QQChen\Dify\Facades\Dify;

// 1. Single Tenant (Default)
Dify::dataset()->list();

// 2. Multi-Tenant (Switch Context)
// Automatically fetches keys for store_id 1001 from DB/Config
Dify::tenant(1001)->dataset()->list();

// 3. Manual Keys (On-the-fly)
Dify::withKey('your-api-key')->chatflow()->sendMessage(...);
````

### 📚 Knowledge Base (知识库)

### Create Dataset (创建知识库)
```
$response = Dify::dataset()->create('My Knowledge Base', 'Description');
$datasetId = $response['id'];
```

### Upload Document (上传文档)

Supports local files and remote URLs.
````
// Upload local file
Dify::document()->createByFile($datasetId, '/path/to/file.pdf', [
'mode' => 'automatic',
'file_name' => 'manual.pdf' // Optional override
]);

// Upload from URL
Dify::document()->createByFile($datasetId, '[https://example.com/doc.pdf](https://example.com/doc.pdf)');
````

### Retrieve (测试检索)

Use the SDK's built-in retrieval presets (vector, full_text, hybrid_rerank).
````
// Simple usage with presets
Dify::dataset()->retrieve($datasetId, 'How to use this?', 'hybrid_rerank');

// Override specific parameters
Dify::dataset()->retrieve($datasetId, 'How to use this?', 'vector', ['top_k' => 10]);
````

### 💬 Chatflow / Agent (工作流对话)

### Send Message (发送消息)

### Blocking Mode (Wait for full response):
````
$response = Dify::chatflow()->sendMessage(
'Hello, who are you?',
'user-123',
[], // inputs
'blocking'
);
echo $response['data']['answer'];
````

### Streaming Mode (Real-time SSE):

Note: In your Controller, return a StreamedResponse to stream data to the frontend.
````
// Controller
public function chat(Request $request)
{
$response = Dify::chatflow()->sendMessage(
$request->input('query'),
'user-123',
[],
'streaming'
);

    // Stream the raw Guzzle response body
    return response()->stream(function () use ($response) {
        $body = $response->getBody();
        while (!$body->eof()) {
            echo $body->read(1024);
            ob_flush();
            flush();
        }
    }, 200, [
        'Content-Type' => 'text/event-stream',
        'X-Accel-Buffering' => 'no',
    ]);
}
````

### Multiple Bots (多应用调用)

If you configured multiple chatflow keys (e.g., `default`, `marketing`):
````
// Use the 'default' bot
Dify::chatflow()->sendMessage(...);

// Use the 'marketing' bot
Dify::chatflow('marketing')->sendMessage(...);
````

### 🏷️ Tags & Metadata (标签与元数据)
````
// Create a tag
Dify::tag()->create('HR Documents');

// Bind tag to dataset
Dify::tag()->bind($datasetId, [$tagId]);

// Create metadata field
Dify::metadata()->createField($datasetId, 'department', 'string');
````

## 📜 License

This project is licensed under the **GNU Affero General Public License v3.0 (AGPL-3.0).**
See the LICENSE file for details.