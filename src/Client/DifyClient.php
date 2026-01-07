<?php

namespace QQChen\Dify\Client;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class DifyClient
{
    /** @var Client */
    protected $httpClient;

    /**
     * @param string $apiKey
     * @param string $baseUrl
     * @param int $timeout
     */
    public function __construct($apiKey, $baseUrl, $timeout = 60) // 建议增加超时时间以适应音频/大文件
    {
        $baseUrl = rtrim($baseUrl, '/') . '/';

        $this->httpClient = new Client([
            'base_uri' => $baseUrl,
            'timeout'  => $timeout,
            'headers'  => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
        ]);
    }


    /**
     * 获取当前客户端配置 (用于 Service 层克隆客户端)
     * @param string|null $option
     * @return mixed
     */
    public function getConfig($option = null)
    {
        return $this->httpClient->getConfig($option);
    }

    /**
     * 发送普通请求 (自动解析 JSON)
     */
    public function send($method, $uri, array $data = [])
    {
        try {
            $options = $this->buildOptions($method, $data);
            $response = $this->httpClient->request($method, $uri, $options);
            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            $this->handleException($e);
        }
    }

    /**
     * 发送流式请求 (返回原始 PSR-7 Response)
     * 用于 Chatflow 的 streaming 响应模式
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function sendStream($method, $uri, array $data = [])
    {
        try {
            $options = $this->buildOptions($method, $data);
            $options['stream'] = true; // 开启 Guzzle 流模式

            return $this->httpClient->request($method, $uri, $options);
        } catch (RequestException $e) {
            $this->handleException($e);
        }
    }


    /**
     * 获取原始响应内容 (用于下载音频、图片等二进制数据)
     * 适配 Chatflow 的文件预览和语音合成
     */
    public function requestRaw($method, $uri, array $data = [])
    {
        try {
            $options = [];
            $method = strtoupper($method);

            if (!empty($data)) {
                if ($method === 'GET') {
                    $options['query'] = $data;
                } else {
                    $options['json'] = $data;
                }
            }

            $response = $this->httpClient->request($method, $uri, $options);

            return [
                'content' => $response->getBody()->getContents(),
                'headers' => $response->getHeaders(),
                'status'  => $response->getStatusCode()
            ];

        } catch (RequestException $e) {
            $this->handleException($e);
        }
    }

    /**
     * 辅助方法：构建请求选项
     */
    protected function buildOptions($method, $data)
    {
        $options = [];
        $method = strtoupper($method);
        if (!empty($data)) {
            if ($method === 'GET') {
                $options['query'] = $data;
            } else {
                $options['json'] = $data;
            }
        }
        return $options;
    }

    /**
     * 专用上传: 针对 Knowledge API (参数需打包在 data JSON 字段中)
     * * @param string $uri API 路径
     * @param string $fileSource 本地文件路径 或 网络文件 URL
     * @param array $metadata 其他参数 (JSON Data)
     * @param string|null $filename 强制指定文件名 (非常重要，用于覆盖临时文件名)
     */
    public function upload($uri, $fileSource, array $metadata = [], $filename = null)
    {
        // 调用内部复用逻辑，传入 Metadata 处理器
        return $this->handleUpload($uri, $fileSource, $filename, function($multipart) use ($metadata) {
            if (!empty($metadata)) {
                $multipart[] = [
                    'name'     => 'data',
                    'contents' => json_encode($metadata)
                ];
            }
            return $multipart;
        });
    }

    /**
     * 通用上传: 针对 Chatflow/File API (参数作为独立表单字段)
     * 适配 Chatflow 的文件上传和语音识别
     */
    public function uploadGeneric($uri, $fileSource, array $formData = [], $filename = null)
    {
        //传入表单字段处理器
        return $this->handleUpload($uri, $fileSource, $filename, function($multipart) use ($formData) {
            foreach ($formData as $key => $value) {
                $multipart[] = [
                    'name'     => $key,
                    'contents' => $value
                ];
            }
            return $multipart;
        });
    }

    /**
     * 内部上传处理逻辑
     */
    protected function handleUpload($uri, $fileSource, $filename, callable $multipartBuilder)
    {
        $tempPath = null;
        $handle = null;

        try {
            // 1. 处理网络 URL
            // 使用正则判断，filter_var(..., FILTER_VALIDATE_URL) 会因为中文字符返回 false
            if (preg_match('/^https?:\/\//i', $fileSource)) {
                // 如果是 URL，先尝试推断文件名
                if (!$filename) {
                    // 尝试解析 path，如果包含中文可能需要 urldecode
                    $pathParts = parse_url($fileSource, PHP_URL_PATH);
                    if ($pathParts) {
                        $filename = basename(urldecode($pathParts));
                    } else {
                        // parse_url 失败时的回退
                        $filename = basename(explode('?', $fileSource)[0]);
                    }
                }

                // 处理 URL 编码：如果 URL 含有未编码的中文，copy() 可能会失败
                // 这里做一个简单的处理，把中文 URL 转换成 encoded 格式（如果需要）
                // 实际生产中建议传入的 URL 已经是 encode 过的，或者使用 Guzzle 下载
                $encodedSource = $this->encodeUrl($fileSource);

                // 下载到临时文件
                $tempPath = tempnam(sys_get_temp_dir(), 'dify_download_');

                // 增加错误抑制符 @，并在失败时抛出异常
                // 注意：服务器必须配置 allow_url_fopen = On
                if (!@copy($encodedSource, $tempPath)) {
                    // 尝试使用 curl/guzzle 下载作为备选（更稳健）
                    try {
                        $this->httpClient->request('GET', $encodedSource, ['sink' => $tempPath, 'verify' => false]);
                    } catch (\Exception $e) {
                        throw new \Exception("无法下载远程文件 [{$fileSource}]: " . $e->getMessage());
                    }
                }
                $fileSource = $tempPath;
            }

            // 2. 检查文件是否存在
            if (!file_exists($fileSource)) {
                throw new \Exception("文件不存在: {$fileSource}");
            }

            // 3. 确定文件名 (如果未指定，使用 basename)
            if (!$filename) {
                $filename = basename($fileSource);
            }

            // 4. 打开文件句柄
            $handle = fopen($fileSource, 'r');

            // 手动构造 Content-Disposition Header
            // 解决 Guzzle 默认发送中文文件名可能丢失的问题
            // 使用 filename*=UTF-8'' 编码，这是 Python/Flask 后端最喜欢的格式
            $encodedFilename = rawurlencode($filename);

            // 5. 构建 Multipart
            $multipart = [
                [
                    'name'     => 'file',
                    'contents' => $handle,
                    //'filename' => $filename, // 这里是关键：告诉 Dify 这是什么文件 (如 a.pdf)
                    // 我们不使用 Guzzle 默认的 'filename' 参数，而是直接覆盖 Header
                    'headers'  => [
                        'Content-Disposition' => "form-data; name=\"file\"; filename=\"{$filename}\"; filename*=UTF-8''{$encodedFilename}"
                    ]
                ]
            ];

            // 调用回调添加其他字段 (Data JSON 或 Form Fields)
            $multipart = $multipartBuilder($multipart);

            // 6. 发送请求 (Guzzle 会自动处理 boundary)
            $response = $this->httpClient->request('POST', $uri, [
                'multipart' => $multipart,
                // 只有 multipart 请求不需要 application/json 类型，Guzzle 会自动覆盖
            ]);

            return json_decode($response->getBody()->getContents(), true);

        } catch (RequestException $e) {
            $this->handleException($e);
        } finally {
            // 清理资源
            if (is_resource($handle)) {
                fclose($handle);
            }
            if ($tempPath && file_exists($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    /**
     * 辅助方法：对 URL 中的中文进行编码，但保留 :// 等符号
     */
    protected function encodeUrl($url) {
        // 如果已经包含 %，假设已经编码过
        if (strpos($url, '%') !== false) {
            return $url;
        }
        return preg_replace_callback('/[^\x20-\x7f]/', function($match) {
            return urlencode($match[0]);
        }, $url);
    }

    protected function handleException(RequestException $e)
    {
        $response = $e->getResponse();
        $statusCode = $response ? $response->getStatusCode() : 0;
        $body = $response ? $response->getBody()->getContents() : 'Unknown Error';

        Log::error('Dify API Error', [
            'status'  => $statusCode,
            'uri'     => $e->getRequest()->getUri(),
            'body'    => $body,
            'message' => $e->getMessage()
        ]);

        throw new \Exception("Dify Request Failed [{$statusCode}]: " . $body);
    }
}