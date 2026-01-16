<?php

namespace QQChen\Dify\Resources;

use QQChen\Dify\Client\DifyClient;

class DatasetResource
{
    /** @var DifyClient */
    protected $client;

    public function __construct(DifyClient $client)
    {
        $this->client = $client;
    }

    /**
     * 1. 获取知识库列表
     */
    public function list($page = 1, $limit = 20)
    {
        return $this->client->send('GET', 'datasets', [
            'page' => $page,
            'limit' => $limit
        ]);
    }

    /**
     * 2. 创建空知识库
     */
    public function create($name, $description = '', $permission = 'only_me', $provider = 'vendor')
    {
        return $this->client->send('POST', 'datasets', [
            'name' => $name,
            'description' => $description,
            'permission' => $permission,
            'provider' => $provider
        ]);
    }

    /**
     * 3. 获取知识库详情
     */
    public function get($datasetId)
    {
        return $this->client->send('GET', "datasets/{$datasetId}");
    }

    /**
     * 4. 删除知识库
     */
    public function delete($datasetId)
    {
        return $this->client->send('DELETE', "datasets/{$datasetId}");
    }

    /**
     * 5. 更新知识库
     */
    public function update($datasetId, array $data)
    {
        return $this->client->send('PATCH', "datasets/{$datasetId}", $data);
    }

    /**
     * 6. 从知识库检索块 / 测试检索
     * * 用法 A (简单模式): retrieve($id, 'iphone', 'hybrid_rerank', ['top_k' => 3])
     * 用法 B (原始模式): retrieve($id, 'iphone', ['search_method' => 'keyword_search', ...])
     *
     * @param string $datasetId
     * @param string $query 搜索关键词
     * @param array|string $retrievalConfigOrType 配置数组 或 预设模式名称(vector, full_text, hybrid_rerank, hybrid_weighted, keyword)
     * @param array $overrides 当参数3为模式名称时，此参数用于覆盖默认值
     */
    public function retrieve($datasetId, $query, $retrievalConfigOrType = [], array $overrides = [])
    {
        $retrievalModel = [];

        // 判断第3个参数是 "配置数组" 还是 "模式名称"
        if (is_string($retrievalConfigOrType)) {
            // === 智能模式 ===
            // 1. 获取预设
            $defaults = $this->getRetrievalDefaults($retrievalConfigOrType);
            // 2. 深度合并用户覆盖参数 (用户传的参数优先级更高)
            $retrievalModel = array_replace_recursive($defaults, $overrides);
        } else {
            // === 原始模式 ===
            // 直接使用用户传入的完整配置
            $retrievalModel = $retrievalConfigOrType;
        }

        $data = [
            'query' => $query,
        ];

        if (!empty($retrievalModel)) {
            $data['retrieval_model'] = $retrievalModel;
        }

        return $this->client->send('POST', "datasets/{$datasetId}/retrieve", $data);
    }

    /**
     * 获取不同检索模式的默认参数配置 (SDK 内置预设)
     * @param string $type
     * @return array
     */
    protected function getRetrievalDefaults($type)
    {
        $defaults = [
            // 1. 向量检索
            'vector' => [
                "search_method" => "semantic_search",
                "reranking_enable" => true,
                "reranking_mode" => "weighted_score",
                "reranking_model" => [
                    "reranking_provider_name" => "langgenius/xinference/xinference",
                    "reranking_model_name" => "bge-reranker-base"
                ],
                "weights" => [
                    "weight_type" => "customized",
                    "vector_setting" => [
                        "vector_weight" => 0.5,
                        "embedding_provider_name" => "",
                        "embedding_model_name" => ""
                    ],
                    "keyword_setting" => [
                        "keyword_weight" => 0.5
                    ]
                ],
                "top_k" => 5,
                "score_threshold_enabled" => true,
                "score_threshold" => 0.3
            ],

            // 2. 全文检索
            'full_text' => [
                "search_method" => "full_text_search",
                "reranking_enable" => true,
                "reranking_mode" => "reranking_model",
                "reranking_model" => [
                    "reranking_provider_name" => "langgenius/xinference/xinference",
                    "reranking_model_name" => "bge-reranker-base"
                ],
                "weights" => [
                    "weight_type" => "customized",
                    "vector_setting" => [
                        "vector_weight" => 0.5,
                        "embedding_provider_name" => "",
                        "embedding_model_name" => ""
                    ],
                    "keyword_setting" => [
                        "keyword_weight" => 0.5
                    ]
                ],
                "top_k" => 5,
                "score_threshold_enabled" => true,
                "score_threshold" => 0.3
            ],

            // 3. 混合重排序检索
            'hybrid_rerank' => [
                "search_method" => "hybrid_search",
                "reranking_enable" => true,
                "reranking_mode" => "reranking_model",
                "reranking_model" => [
                    "reranking_provider_name" => "langgenius/xinference/xinference",
                    "reranking_model_name" => "bge-reranker-base"
                ],
                "weights" => [
                    "weight_type" => "customized",
                    "vector_setting" => [
                        "vector_weight" => 0.7,
                        "embedding_provider_name" => "",
                        "embedding_model_name" => ""
                    ],
                    "keyword_setting" => [
                        "keyword_weight" => 0.3
                    ]
                ],
                "top_k" => 4,
                "score_threshold_enabled" => true,
                "score_threshold" => 0.2
            ],

            // 4. 混合权重检索
            //{
            //  "query": "问题测试？",
            //  "retrieval_model": {
            //    "search_method": "hybrid_search",
            //    "reranking_enable": true,
            //    "reranking_mode": "weighted_score",
            //    "reranking_model": {
            //      "reranking_provider_name": "langgenius/xinference/xinference",
            //      "reranking_model_name": "bge-reranker-base"
            //    },
            //    "top_k": 10,
            //    "score_threshold_enabled": true,
            //    "score_threshold": 0.2,
            //    "weights": 0.5,
            //    "metadata_filtering_conditions": {
            //      "logical_operator": "or",
            //      "conditions": [
            //        {
            //          "name": "cate",
            //          "comparison_operator": "=",
            //          "value": "enable"
            //        }
            //      ]
            //    }
            //  }
            'hybrid_weighted' => [
                "search_method" => "hybrid_search",
                "reranking_enable" => true,
                "reranking_mode" => "weighted_score",
                "reranking_model" => [
                    "reranking_provider_name" => "langgenius/xinference/xinference",
                    "reranking_model_name" => "bge-reranker-base"
                ],
                "top_k" => 10,
                "score_threshold_enabled" => true,
                "score_threshold" => 0.2,
                "weights" => [
                    "weight_type" => "customized",
                    "vector_setting" => [
                        "vector_weight" => 0.6,
                        "embedding_provider_name" => "",
                        "embedding_model_name" => ""
                    ],
                    "keyword_setting" => [
                        "keyword_weight" => 0.4
                    ]
                ],
                "metadata_filtering_conditions"=>[
                    "logical_operator"=>"or",
                    "conditions"=>[
                        ["name"=>"cate",'comparison_operator'=>"=","value"=>"enable"],
                    ],
                ]



            ],

            // 5. 关键词检索
            'keyword' => [
                "search_method" => "keyword_search",
                "top_k" => 5,
                "score_threshold_enabled" => true,
                "score_threshold" => 0.2
            ]
        ];

        return isset($defaults[$type]) ? $defaults[$type] : $defaults['hybrid_rerank'];
    }
}