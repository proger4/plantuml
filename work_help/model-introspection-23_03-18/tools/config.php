<?php

declare(strict_types=1);

/*
ROO SELF-FILL REQUEST (edit this same file only):

Ты настраиваешь только tools/config.php для pipeline model introspection.
Не меняй ключи и структуру массива, меняй только значения.

Нужно заполнить:
1) model_paths: реальные директории с Yii моделями.
2) source_paths: директории, где искать usage.
3) yii_model_namespaces: FQCN namespace(s) моделей Yii.
4) sql_dump_path: путь к SQL dump/schema.
5) uml_path: путь к исходному PlantUML.
6) trimmed_uml_dir: путь для auto-trimmed UML графа.
7) analysis_dir: корневая директория analysis output.
8) openai_compatible.base_url: локальный OpenAI-compatible URL.
9) openai_compatible.token: возьми токен из ENV TOOLS_OPENAI_TOKEN
   (не коммить секрет в явном виде).

Правила:
- Используй относительные пути от корня проекта.
- Не добавляй новые ключи без необходимости.
- Если путь не существует, оставь TODO в этом комментарии.
*/

return [
    'model_paths' => [
        './app/models',
        './src',
    ],
    'source_paths' => [
        './app',
        './src',
    ],
    'yii_model_namespaces' => [
        'App\\Model',
        'Legacy\\Model',
    ],
    'uml_path' => './docs/domain.puml',
    'sql_dump_path' => './var/schema.sql',
    'analysis_dir' => './var/analysis',
    'trimmed_uml_dir' => './var/analysis/snapshots/_uml',
    'yii_bootstrap' => './protected/yii.php',
    'llm_timeout_seconds' => 60,
    'openai_compatible' => [
        // Local OpenAI-compatible endpoint.
        'base_url' => 'http://127.0.0.1:11434/v1',
        // Token is taken from environment by default.
        'token' => (string) (getenv('TOOLS_OPENAI_TOKEN') ?: ''),
    ],
    // Model registry for this pipeline.
    'model_map' => [
        'retrieval' => 'qwen3-embedding:0.6b',
        'fast' => 'Qwen3-Coder-Next',
        'adjudicator' => 'gpt-oss-120b',
    ],
    // Simple role policy.
    'model_policy' => [
        'default_role' => 'fast',
        'adjudicator_case_types' => ['INHERITANCE_CASE', 'DISCRIMINATOR_CASE'],
        'adjudicator_statuses' => ['CONFLICT', 'MANUAL', 'UNSUPPORTED'],
    ],
];
