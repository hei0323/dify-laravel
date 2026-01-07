<?php

namespace QQChen\Dify;

use Illuminate\Support\ServiceProvider;
use QQChen\Dify\Client\DifyClient;

class DifyServiceProvider extends ServiceProvider
{
    public function register()
    {
        // 自动合并配置文件
        $this->mergeConfigFrom(
            __DIR__ . '/../config/dify.php', 'dify'
        );

        // 注册单例服务
        $this->app->singleton('dify', function ($app) {
            $config = $app['config']->get('dify');

            // 修复：移除强制检查。在多租户模式下，全局 api_key 可能为空，
            // 只要后续通过 tenant() 提供了有效密钥即可。
            $apiKey = isset($config['api_key']) ? $config['api_key'] : '';

            $client = new DifyClient(
                $apiKey,
                isset($config['base_url']) ? $config['base_url'] : 'https://api.dify.ai/v1',
                isset($config['timeout']) ? $config['timeout'] : 60
            );

            // 如果配置了多租户解析器，这里可以进行初始化，但通常在 AppServiceProvider 中注册回调
            // DifyService 会处理 context 切换
            return new DifyService($client);
        });
    }

    public function boot()
    {
        // 发布配置文件
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/dify.php' => config_path('dify.php'),
            ], 'dify-config');
        }
    }
}