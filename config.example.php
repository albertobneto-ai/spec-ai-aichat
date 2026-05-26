<?php
// ============================================================
//  config.php  —  ÚNICO arquivo que você precisa editar
// ============================================================

// --- URL base do projeto (sem barra no final) ---
define('BASE_URL', 'https://albertobottaro.info/aichat');

// --- OpenRouter ---
define('OPENROUTER_KEY', 'SUA_CHAVE_OPENROUTER');

// --- xAI / Grok (direto) ---
// Chave gerada em console.x.ai — deixe vazio para desativar
define('GROK_KEY', 'SUA_CHAVE_GROK');

// --- Banco de Dados ---
define('DB_HOST', 'localhost');
define('DB_NAME', 'NOME_DO_BANCO');
define('DB_USER', 'USUARIO_DO_BANCO');
define('DB_PASS', 'SENHA_DO_BANCO');

// --- Painel Admin (troque!) ---
define('ADMIN_SENHA', 'SENHA_ADMIN_SEGURA');

// --- Modelos OpenRouter (gratuitos) ---
define('MODELOS_OPENROUTER', [
'poolside/laguna-xs.2:free' => 'Laguna',
 // 'meta-llama/llama-3.1-8b-instruct:free'  => 'Llama 3.1 8B',
 // 'deepseek/deepseek-r1:free'               => 'DeepSeek R1',
 //  'google/gemma-3-27b-it:free'              => 'Gemma 3 27B',
  // 'qwen/qwen3-235b-a22b:free'               => 'Qwen3 235B',
]);

// --- Modelos xAI / Grok (direto) ---
define('MODELOS_GROK', [
'grok-4.3'  => 'Grok 4.3',
//'grok-4.20-non-reasoning'   => 'Grok 4.20 Fast'
]);

// Todos os modelos (para o chat.php exibir)
define('MODELOS', array_merge(MODELOS_GROK, MODELOS_OPENROUTER));

// --- Base de Conhecimento ---
define('KB_DIR', __DIR__ . '/knowledge_base');
define('KB_MAX_CHUNKS', 5);
define('KB_CHUNK_SIZE', 50);

// --- Sessão ---
define('SESSION_NAME', 'aichat_sess');
define('SESSION_LIFETIME', 60 * 60 * 8);



